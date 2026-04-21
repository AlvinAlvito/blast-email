<?php

namespace App\Jobs;

use App\Mail\CampaignEmail;
use App\Models\CampaignRecipient;
use App\Models\SenderAccount;
use App\Services\SenderAccountResolver;
use App\Support\MailFailureClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendCampaignEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $campaignRecipientId)
    {
    }

    public function handle(SenderAccountResolver $resolver): void
    {
        $lock = Cache::lock("campaign-recipient:{$this->campaignRecipientId}", 30);

        if (! $lock->get()) {
            return;
        }

        try {
            $recipient = CampaignRecipient::query()
                ->with(['campaign', 'contact'])
                ->findOrFail($this->campaignRecipientId);

            if (in_array($recipient->status, ['sent', 'cancelled'], true)) {
                return;
            }

            if (! $recipient->campaign || $recipient->campaign->status === 'stopped') {
                $recipient->update([
                    'status' => 'cancelled',
                    'failed_at' => now(),
                    'error_message' => 'Pengiriman dihentikan manual.',
                ]);

                $this->finalizeCampaignIfFinished($recipient);

                return;
            }

            if ($recipient->campaign->status === 'paused') {
                return;
            }

            if (! $recipient->contact?->email) {
                $recipient->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => 'Kontak tidak memiliki email yang valid.',
                ]);

                $this->finalizeCampaignIfFinished($recipient);

                return;
            }

            if (in_array($recipient->contact->status, ['invalid_email', 'blocked'], true)) {
                if ($recipient->contact->status === 'blocked' && $this->canRestoreBlockedContact($recipient)) {
                    $recipient->contact->forceFill([
                        'status' => 'active',
                    ])->save();

                    $recipient->contact->refresh();
                } else {
                    $recipient->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'error_message' => 'Kontak ditandai bermasalah dan tidak dikirim ulang otomatis.',
                    ]);

                    $this->finalizeCampaignIfFinished($recipient);

                    return;
                }
            }

            $sender = $resolver->next();

            if (! $sender) {
                if (! $resolver->hasActiveSender()) {
                    $recipient->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'error_message' => 'Tidak ada sender account aktif yang tersedia.',
                    ]);

                    $this->finalizeCampaignIfFinished($recipient);

                    return;
                }

                $retryAt = $resolver->nextAvailableAt() ?? now()->addMinutes(15);

                $recipient->update([
                    'status' => 'queued',
                    'queued_at' => $retryAt,
                    'error_message' => 'Menunggu kuota sender tersedia kembali.',
                ]);

                self::dispatch($recipient->id)->delay($retryAt);

                return;
            }

            $this->resetDynamicMailer();

            config([
                'mail.default' => 'dynamic',
                'mail.mailers.dynamic' => [
                    'transport' => $sender->mailer,
                    'host' => $sender->host,
                    'port' => $sender->port,
                    'encryption' => $sender->encryption ?: null,
                    'username' => $sender->username,
                    'password' => $sender->password,
                    'timeout' => 20,
                ],
            ]);

            $recipient->forceFill([
                'sender_account_id' => $sender->id,
            ])->save();

            try {
                Mail::mailer('dynamic')
                    ->to($recipient->contact->email)
                    ->send(
                        (new CampaignEmail($recipient->campaign, $recipient->contact))
                            ->from($sender->from_address, $sender->from_name)
                            ->replyTo($sender->reply_to_address ?: $sender->from_address, $sender->from_name)
                    );
            } catch (\Throwable $exception) {
                $this->handleTransportFailure($recipient, $sender, $exception);

                return;
            } finally {
                $this->resetDynamicMailer();
            }

            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);
            $this->clearTransientRetryCounter();

            $this->finalizeCampaignIfFinished($recipient);

            $sentAt = Carbon::now();

            $sender->forceFill([
                'sent_today' => $sender->effectiveSentToday($sentAt) + 1,
                'last_sent_at' => $sentAt,
            ])->save();
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $recipient = CampaignRecipient::query()
            ->with('contact')
            ->find($this->campaignRecipientId);

        if (! $recipient) {
            return;
        }

        $this->clearTransientRetryCounter();

        $message = $exception->getMessage();
        [$contactStatus, $shouldOptOut] = MailFailureClassifier::classify($message);

        $recipient->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $message,
        ]);

        if ($recipient->contact && $contactStatus !== null) {
            $recipient->contact->forceFill([
                'status' => $contactStatus,
                'email_opt_out' => $shouldOptOut ? true : $recipient->contact->email_opt_out,
            ])->save();
        }

        $this->finalizeCampaignIfFinished($recipient);
    }

    protected function classifyFailure(string $message): array
    {
        return MailFailureClassifier::classify($message);
    }

    protected function handleTransportFailure(CampaignRecipient $recipient, SenderAccount $sender, \Throwable $exception): void
    {
        $message = $exception->getMessage();
        [$contactStatus, $shouldOptOut] = $this->classifyFailure($message);

        if ($contactStatus !== null) {
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $message,
            ]);

            if ($recipient->contact) {
                $recipient->contact->forceFill([
                    'status' => $contactStatus,
                    'email_opt_out' => $shouldOptOut ? true : $recipient->contact->email_opt_out,
                ])->save();
            }

            $this->clearTransientRetryCounter();
            $this->finalizeCampaignIfFinished($recipient);

            return;
        }

        $retryCount = $this->incrementTransientRetryCounter();
        $maxRetries = max(3, SenderAccount::query()->where('is_active', true)->count() + 1);

        // Push a temporarily failing sender to the back of the rotation.
        $sender->forceFill([
            'last_sent_at' => now(),
        ])->save();

        if ($retryCount > $maxRetries) {
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $message,
            ]);

            $this->clearTransientRetryCounter();
            $this->finalizeCampaignIfFinished($recipient);

            return;
        }

        $retryAt = now()->addSeconds(20);

        $recipient->update([
            'status' => 'queued',
            'queued_at' => $retryAt,
            'failed_at' => null,
            'error_message' => 'Retry via sender lain: '.$message,
        ]);

        self::dispatch($recipient->id)->delay($retryAt);
    }

    protected function canRestoreBlockedContact(CampaignRecipient $recipient): bool
    {
        if (! $recipient->contact) {
            return false;
        }

        if (MailFailureClassifier::isRetryableSenderFailure($recipient->error_message)) {
            return true;
        }

        return $recipient->contact->campaignRecipients()
            ->whereNotNull('error_message')
            ->latest('updated_at')
            ->get()
            ->contains(fn (CampaignRecipient $history) => MailFailureClassifier::isRetryableSenderFailure($history->error_message));
    }

    protected function resetDynamicMailer(): void
    {
        $mailManager = app('mail.manager');

        if (method_exists($mailManager, 'forgetMailers')) {
            $mailManager->forgetMailers();
        }

        if (method_exists($mailManager, 'purge')) {
            $mailManager->purge('dynamic');
        }
    }

    protected function incrementTransientRetryCounter(): int
    {
        $key = $this->transientRetryCacheKey();
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addHours(6));

        return $attempts;
    }

    protected function clearTransientRetryCounter(): void
    {
        Cache::forget($this->transientRetryCacheKey());
    }

    protected function transientRetryCacheKey(): string
    {
        return "campaign-recipient:{$this->campaignRecipientId}:transient-retries";
    }

    protected function finalizeCampaignIfFinished(CampaignRecipient $recipient): void
    {
        if (! $recipient->campaign || $recipient->campaign->status === 'stopped') {
            return;
        }

        if ($recipient->campaign->recipients()->where('status', 'queued')->exists()) {
            return;
        }

        $hasFailures = $recipient->campaign->recipients()->whereIn('status', ['failed', 'cancelled'])->exists();

        $recipient->campaign->update([
            'status' => $hasFailures ? 'completed_with_issues' : 'completed',
            'finished_at' => now(),
        ]);
    }
}
