<?php

namespace App\Jobs;

use App\Mail\CampaignEmail;
use App\Models\Contact;
use App\Models\CampaignRecipient;
use App\Services\SenderAccountResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
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

            return;
        }

        if ($recipient->campaign->status === 'paused') {
            self::dispatch($recipient->id)->delay(now()->addSeconds(15));

            return;
        }

        if (! $recipient->contact?->email) {
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => 'Kontak tidak memiliki email yang valid.',
            ]);

            return;
        }

        if (in_array($recipient->contact->status, ['invalid_email', 'blocked'], true)) {
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => 'Kontak ditandai bermasalah dan tidak dikirim ulang otomatis.',
            ]);

            return;
        }

        $sender = $resolver->next();

        if (! $sender) {
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => 'Tidak ada sender account aktif yang tersedia.',
            ]);

            return;
        }

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

        Mail::mailer('dynamic')
            ->to($recipient->contact->email)
            ->send(
                (new CampaignEmail($recipient->campaign, $recipient->contact))
                    ->from($sender->from_address, $sender->from_name)
                    ->replyTo($sender->reply_to_address ?: $sender->from_address, $sender->from_name)
            );

        $recipient->update([
            'sender_account_id' => $sender->id,
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ]);

        if ($recipient->campaign && ! $recipient->campaign->recipients()->where('status', 'queued')->exists()) {
            $hasFailures = $recipient->campaign->recipients()->whereIn('status', ['failed', 'cancelled'])->exists();
            $recipient->campaign->update([
                'status' => $hasFailures ? 'completed_with_issues' : 'completed',
                'finished_at' => now(),
            ]);
        }

        $sender->forceFill([
            'sent_today' => $sender->sent_today + 1,
            'last_sent_at' => Carbon::now(),
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        $recipient = CampaignRecipient::query()
            ->with('contact')
            ->find($this->campaignRecipientId);

        if (! $recipient) {
            return;
        }

        $message = $exception->getMessage();
        [$contactStatus, $shouldOptOut] = $this->classifyFailure($message);

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
    }

    protected function classifyFailure(string $message): array
    {
        $normalized = strtolower($message);

        $invalidSignals = [
            'recipient address rejected',
            'invalid recipient',
            'bad recipient',
            'user unknown',
            'mailbox unavailable',
            'no such user',
            'unknown user',
            'unknown mailbox',
            'domain not found',
            'invalid address',
            'address rejected',
        ];

        foreach ($invalidSignals as $signal) {
            if (str_contains($normalized, $signal)) {
                return ['invalid_email', true];
            }
        }

        $blockedSignals = [
            'spam',
            'blocked',
            'blacklist',
            'rate limit',
            'too many',
            'suspend',
            'policy rejection',
        ];

        foreach ($blockedSignals as $signal) {
            if (str_contains($normalized, $signal)) {
                return ['blocked', false];
            }
        }

        return [null, false];
    }
}
