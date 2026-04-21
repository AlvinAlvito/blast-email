<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\SenderAccount;
use App\Support\MailFailureClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    protected const REQUEUE_COOLDOWN_HOURS = 24;

    public function targetStats(Request $request): JsonResponse
    {
        $data = $request->validate([
            'segment' => ['nullable', 'string', 'max:255'],
            'import_batch_id' => ['nullable', 'integer', 'exists:import_batches,id'],
            'ignore_cooldown' => ['nullable', 'boolean'],
        ]);

        $ignoreCooldown = (bool) ($data['ignore_cooldown'] ?? false);
        $targetedContactsQuery = $this->targetedContactsQuery($data);

        $stats = [
            'total_target_contacts' => (clone $targetedContactsQuery)->count(),
            'with_email' => (clone $targetedContactsQuery)->whereNotNull('email')->count(),
            'opted_out' => (clone $targetedContactsQuery)->where('email_opt_out', true)->count(),
            'invalid_or_blocked' => (clone $targetedContactsQuery)->whereIn('status', ['invalid_email', 'blocked'])->count(),
            'cooldown_blocked' => $ignoreCooldown ? 0 : (clone $targetedContactsQuery)
                ->whereNotNull('email')
                ->where('email_opt_out', false)
                ->whereNotIn('status', ['invalid_email', 'blocked'])
                ->whereHas('campaignRecipients', function ($recipientQuery) {
                    $recipientQuery->whereIn('status', ['queued', 'sent'])
                        ->where('created_at', '>=', Carbon::now()->subHours(self::REQUEUE_COOLDOWN_HOURS));
                })
                ->count(),
            'emailable_now' => $this->emailableContactsQuery($data, $ignoreCooldown)->count(),
        ];

        return response()->json($stats);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'segment' => ['nullable', 'string', 'max:255'],
            'import_batch_id' => ['nullable', 'integer', 'exists:import_batches,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:600'],
            'ignore_cooldown' => ['nullable', 'boolean'],
            'form_nonce' => ['required', 'string'],
        ]);

        if (! $this->hasActiveSender()) {
            return redirect()
                ->route('admin.campaigns')
                ->withErrors(['subject' => 'Tidak ada sender aktif yang tersedia saat ini. Aktifkan minimal satu sender sebelum membuat campaign.'])
                ->withInput();
        }

        $sessionNonce = (string) $request->session()->pull('campaign_form_nonce');
        if ($sessionNonce === '' || ! hash_equals($sessionNonce, $data['form_nonce'])) {
            return redirect()
                ->route('admin.campaigns')
                ->withErrors(['form_nonce' => 'Form pengiriman sudah diproses. Muat ulang halaman lalu coba lagi.']);
        }

        $ignoreCooldown = (bool) ($data['ignore_cooldown'] ?? false);

        $segmentLabel = 'all';
        if (! empty($data['import_batch_id'])) {
            $batch = ImportBatch::find($data['import_batch_id']);
            $segmentLabel = 'batch:'.$batch?->title;
        } elseif (! empty($data['segment'])) {
            $segmentLabel = $data['segment'];
        }

        $contactsQuery = $this->selectedContactsQuery($data, $ignoreCooldown);

        $targetCount = (clone $contactsQuery)->count();

        if ($targetCount < 1) {
            return redirect()
                ->route('admin.campaigns')
                ->withErrors(['subject' => 'Tidak ada kontak yang cocok dengan target yang dipilih.'])
                ->withInput();
        }

        $campaign = Campaign::create([
            ...$data,
            'segment' => $segmentLabel,
            'channel' => 'email',
            'status' => 'queued',
            'batch_size' => $targetCount,
            'started_at' => now(),
        ]);

        $dispatched = 0;

        $contactsQuery
            ->orderBy('id')
            ->chunkById(500, function ($contacts) use ($campaign, $data, &$dispatched) {
                foreach ($contacts as $contact) {
                    $recipient = CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'status' => 'queued',
                        'queued_at' => now(),
                    ]);

                    SendCampaignEmailJob::dispatch($recipient->id)
                        ->delay(now()->addSeconds($dispatched * $data['delay_seconds']));

                    $dispatched++;
                }
            });

        return redirect()->route('admin.campaigns')->with('status', "Campaign {$campaign->name} di-queue untuk {$targetCount} kontak.");
    }

    public function pause(Campaign $campaign): RedirectResponse
    {
        if ($campaign->status === 'queued') {
            $campaign->update(['status' => 'paused']);
        }

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('status', "Pengiriman {$campaign->name} dijeda.");
    }

    public function resume(Campaign $campaign): RedirectResponse
    {
        if (in_array($campaign->status, ['paused', 'draft'], true)) {
            $campaign->update([
                'status' => 'queued',
                'started_at' => $campaign->started_at ?: now(),
                'finished_at' => null,
            ]);

            $queuedRecipients = $campaign->recipients()
                ->where('status', 'queued')
                ->orderBy('id')
                ->get();

            foreach ($queuedRecipients as $index => $recipient) {
                SendCampaignEmailJob::dispatch($recipient->id)->delay(now()->addSeconds($index * max(1, $campaign->delay_seconds)));
            }
        }

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('status', "Pengiriman {$campaign->name} dilanjutkan kembali.");
    }

    public function stop(Campaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($campaign) {
            $campaign->update([
                'status' => 'stopped',
                'finished_at' => now(),
            ]);

            $campaign->recipients()
                ->where('status', 'queued')
                ->update([
                    'status' => 'cancelled',
                    'failed_at' => now(),
                    'error_message' => 'Pengiriman dihentikan manual.',
                ]);
        });

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('status', "Pengiriman {$campaign->name} dihentikan.");
    }

    public function retryFailed(Campaign $campaign): RedirectResponse
    {
        $failedRecipients = $campaign->recipients()
            ->with(['contact.campaignRecipients'])
            ->where('status', 'failed')
            ->get();

        foreach ($failedRecipients as $index => $recipient) {
            if ($recipient->contact && $recipient->contact->status === 'blocked' && $this->canRestoreBlockedContact($recipient->contact)) {
                $recipient->contact->forceFill([
                    'status' => 'active',
                ])->save();
            }

            $recipient->update([
                'status' => 'queued',
                'queued_at' => now(),
                'failed_at' => null,
                'sender_account_id' => null,
            ]);

            SendCampaignEmailJob::dispatch($recipient->id)->delay(now()->addSeconds($index * max(1, $campaign->delay_seconds)));
        }

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('status', "Retry di-queue untuk {$failedRecipients->count()} recipient gagal.");
    }

    protected function canRestoreBlockedContact(Contact $contact): bool
    {
        $failures = $contact->campaignRecipients
            ->pluck('error_message')
            ->filter(fn ($message) => is_string($message) && $message !== '');

        if ($failures->contains(fn ($message) => MailFailureClassifier::isPermanentContactFailure($message))) {
            return false;
        }

        return $failures->contains(fn ($message) => MailFailureClassifier::isRetryableSenderFailure($message));
    }

    protected function availableQuotaNow(): int
    {
        $activeSenders = SenderAccount::query()
            ->where('is_active', true)
            ->get();

        $dailyRemaining = $activeSenders->sum(fn (SenderAccount $sender) => $sender->remainingDailyQuota());
        $hourlyRemaining = $activeSenders->sum(fn (SenderAccount $sender) => $sender->remainingHourlyQuota());

        return max(0, min($dailyRemaining, $hourlyRemaining));
    }

    protected function hasActiveSender(): bool
    {
        return SenderAccount::query()
            ->where('is_active', true)
            ->exists();
    }

    protected function targetedContactsQuery(array $data)
    {
        return Contact::query()
            ->when(
                ! empty($data['import_batch_id']),
                fn ($query) => $query->where('import_batch_id', $data['import_batch_id']),
                fn ($query) => $query->when($data['segment'] ?? null, fn ($subQuery, $segment) => $subQuery->where('segment', $segment))
            );
    }

    protected function selectedContactsQuery(array $data, bool $ignoreCooldown)
    {
        if (! empty($data['import_batch_id'])) {
            return $this->targetedContactsQuery($data);
        }

        return $this->emailableContactsQuery($data, $ignoreCooldown);
    }

    protected function emailableContactsQuery(array $data, bool $ignoreCooldown)
    {
        return $this->targetedContactsQuery($data)
            ->whereNotNull('email')
            ->where('email_opt_out', false)
            ->whereNotIn('status', ['invalid_email', 'blocked'])
            ->when(
                ! $ignoreCooldown,
                fn ($query) => $query->whereDoesntHave('campaignRecipients', function ($recipientQuery) {
                    $recipientQuery->whereIn('status', ['queued', 'sent'])
                        ->where('created_at', '>=', Carbon::now()->subHours(self::REQUEUE_COOLDOWN_HOURS));
                })
            );
    }
}
