<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\SenderAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function overview(): View
    {
        return view('admin.overview', $this->sharedData());
    }

    public function contacts(): View
    {
        return view('admin.contacts', $this->sharedData([
            'importBatches' => ImportBatch::query()
                ->withCount('contacts')
                ->latest()
                ->paginate(3),
        ]));
    }

    public function contactBatch(ImportBatch $importBatch): View
    {
        return view('admin.contact-batch', $this->sharedData([
            'pageTitle' => 'Detail Import',
            'pageDescription' => 'Kontak yang berasal dari satu file import tertentu.',
            'importBatch' => $importBatch,
            'contacts' => $importBatch->contacts()->latest()->paginate(25),
        ]));
    }

    public function senders(): View
    {
        return view('admin.senders', $this->sharedData());
    }

    public function campaigns(): View
    {
        $formNonce = (string) Str::uuid();
        session()->put('campaign_form_nonce', $formNonce);

        return view('admin.campaigns', $this->sharedData([
            'formNonce' => $formNonce,
            'campaigns' => Campaign::query()
                ->with('importBatch:id,title')
                ->withCount([
                    'recipients as sent_count' => fn ($query) => $query->where('status', 'sent'),
                    'recipients as queued_count' => fn ($query) => $query->where('status', 'queued'),
                    'recipients as failed_count' => fn ($query) => $query->where('status', 'failed'),
                ])
                ->latest()
                ->paginate(5),
        ]));
    }

    public function campaignDetail(Campaign $campaign): View
    {
        $campaign->load([
            'recipients.contact',
            'recipients.senderAccount',
            'importBatch',
        ]);

        return view('admin.campaign-detail', $this->sharedData([
            'pageTitle' => 'Detail Pengiriman',
            'pageDescription' => 'Ringkasan isi email, statistik pengiriman, dan daftar penerima untuk pengiriman ini.',
            'campaignDetail' => $campaign,
            'campaignRecipients' => $campaign->recipients()->with(['contact', 'senderAccount'])->latest()->paginate(25),
        ]));
    }

    public function analytics(): View
    {
        return view('admin.analytics', $this->sharedData());
    }

    protected function sharedData(array $extra = []): array
    {
        $stats = [
            'contacts' => Contact::count(),
            'emailable_contacts' => Contact::whereNotNull('email')->where('email_opt_out', false)->count(),
            'senders' => SenderAccount::count(),
            'campaigns' => Campaign::count(),
            'queued_recipients' => CampaignRecipient::where('status', 'queued')->count(),
            'sent_recipients' => CampaignRecipient::where('status', 'sent')->count(),
        ];

        $contactsByLevel = Contact::query()
            ->select('education_level', DB::raw('count(*) as total'))
            ->groupBy('education_level')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $contactsByProvince = Contact::query()
            ->select('province', DB::raw('count(*) as total'))
            ->whereNotNull('province')
            ->groupBy('province')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $campaignStatus = CampaignRecipient::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $contactHealth = Contact::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $campaigns = Campaign::query()
            ->withCount([
                'recipients as sent_count' => fn ($query) => $query->where('status', 'sent'),
                'recipients as queued_count' => fn ($query) => $query->where('status', 'queued'),
                'recipients as failed_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->latest()
            ->limit(8)
            ->get();

        $recentContacts = Contact::latest()->limit(8)->get();
        $senderAccounts = SenderAccount::latest()->get();
        $segments = Contact::query()->select('segment')->whereNotNull('segment')->distinct()->pluck('segment');
        $importBatchTargets = ImportBatch::query()
            ->latest()
            ->get(['id', 'title', 'file_name']);

        return array_merge([
            'pageTitle' => 'Overview',
            'stats' => $stats,
            'recentContacts' => $recentContacts,
            'senderAccounts' => $senderAccounts,
            'campaigns' => $campaigns,
            'segments' => $segments,
            'importBatchTargets' => $importBatchTargets,
            'contactsByLevel' => $contactsByLevel,
            'contactsByProvince' => $contactsByProvince,
            'campaignStatus' => $campaignStatus,
            'contactHealth' => $contactHealth,
        ], $extra);
    }
}
