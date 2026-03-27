<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaignEmailJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ImportBatch;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    protected const REQUEUE_COOLDOWN_HOURS = 24;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'segment' => ['nullable', 'string', 'max:255'],
            'import_batch_id' => ['nullable', 'integer', 'exists:import_batches,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'batch_size' => ['required', 'integer', 'min:1', 'max:500'],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:600'],
            'ignore_cooldown' => ['nullable', 'boolean'],
            'form_nonce' => ['required', 'string'],
        ]);

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

        $campaign = Campaign::create([
            ...$data,
            'segment' => $segmentLabel,
            'channel' => 'email',
            'status' => 'queued',
            'started_at' => now(),
        ]);

        $contactsQuery = Contact::query()
            ->whereNotNull('email')
            ->where('email_opt_out', false)
            ->whereNotIn('status', ['invalid_email', 'blocked'])
            ->when(
                ! $ignoreCooldown,
                fn ($query) => $query->whereDoesntHave('campaignRecipients', function ($recipientQuery) {
                    $recipientQuery->whereIn('status', ['queued', 'sent'])
                        ->where('created_at', '>=', Carbon::now()->subHours(self::REQUEUE_COOLDOWN_HOURS));
                })
            )
            ->when(
                ! empty($data['import_batch_id']),
                fn ($query) => $query->where('import_batch_id', $data['import_batch_id']),
                fn ($query) => $query->when($data['segment'] ?? null, fn ($subQuery, $segment) => $subQuery->where('segment', $segment))
            );

        $contacts = $contactsQuery
            ->limit($data['batch_size'])
            ->get();

        foreach ($contacts as $index => $contact) {
            $recipient = CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'status' => 'queued',
                'queued_at' => now(),
            ]);

            SendCampaignEmailJob::dispatch($recipient->id)->delay(now()->addSeconds($index * $data['delay_seconds']));
        }

        return redirect()->route('admin.campaigns')->with('status', "Campaign {$campaign->name} di-queue untuk {$contacts->count()} kontak.");
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
            ->where('status', 'failed')
            ->get();

        foreach ($failedRecipients as $index => $recipient) {
            $recipient->update([
                'status' => 'queued',
                'queued_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);

            SendCampaignEmailJob::dispatch($recipient->id)->delay(now()->addSeconds($index * max(1, $campaign->delay_seconds)));
        }

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('status', "Retry di-queue untuk {$failedRecipients->count()} recipient gagal.");
    }
}
