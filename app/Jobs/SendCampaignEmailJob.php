<?php

namespace App\Jobs;

use App\Mail\CampaignEmail;
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

        if ($recipient->status === 'sent' || ! $recipient->contact?->email) {
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

        $sender->forceFill([
            'sent_today' => $sender->sent_today + 1,
            'last_sent_at' => Carbon::now(),
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        CampaignRecipient::query()
            ->whereKey($this->campaignRecipientId)
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);
    }
}
