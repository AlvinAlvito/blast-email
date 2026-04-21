<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\SenderAccount;
use App\Support\MailFailureClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

    public function contactIssues(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:all,blocked,invalid_email'],
            'error_scope' => ['nullable', 'string', 'in:all,sender,recipient,other'],
        ]);

        $statusFilter = $filters['status'] ?? 'blocked';
        $errorScope = $filters['error_scope'] ?? 'all';
        $search = trim((string) ($filters['q'] ?? ''));

        $latestFailureSubquery = CampaignRecipient::query()
            ->select('contact_id', DB::raw('MAX(updated_at) as latest_updated_at'))
            ->where('status', 'failed')
            ->groupBy('contact_id');

        $problemContactsQuery = Contact::query()
            ->leftJoinSub($latestFailureSubquery, 'latest_failures', function ($join) {
                $join->on('latest_failures.contact_id', '=', 'contacts.id');
            })
            ->leftJoin('campaign_recipients as latest_recipient', function ($join) {
                $join->on('latest_recipient.contact_id', '=', 'contacts.id')
                    ->on('latest_recipient.updated_at', '=', 'latest_failures.latest_updated_at');
            })
            ->leftJoin('sender_accounts as latest_sender', 'latest_sender.id', '=', 'latest_recipient.sender_account_id')
            ->select([
                'contacts.*',
                'latest_recipient.id as latest_recipient_id',
                'latest_recipient.campaign_id as latest_campaign_id',
                'latest_recipient.error_message as latest_error_message',
                'latest_recipient.failed_at as latest_failed_at',
                'latest_recipient.updated_at as latest_error_at',
                'latest_sender.from_address as latest_sender_address',
            ])
            ->whereIn('contacts.status', ['blocked', 'invalid_email']);

        if ($statusFilter !== 'all') {
            $problemContactsQuery->where('contacts.status', $statusFilter);
        }

        if ($search !== '') {
            $problemContactsQuery->where(function ($query) use ($search) {
                $query
                    ->where('contacts.name', 'like', "%{$search}%")
                    ->orWhere('contacts.email', 'like', "%{$search}%")
                    ->orWhere('contacts.school', 'like', "%{$search}%")
                    ->orWhere('contacts.field', 'like', "%{$search}%")
                    ->orWhere('latest_recipient.error_message', 'like', "%{$search}%");
            });
        }

        if ($errorScope !== 'all') {
            $problemContactsQuery->where(function ($query) use ($errorScope) {
                $senderSignals = [
                    'retry via sender lain',
                    'failed to authenticate',
                    'too many login attempts',
                    'sending limit exceeded',
                    'daily user sending limit exceeded',
                    'outgoing mail from',
                    'has been suspended',
                    'gsmtp',
                ];

                $recipientSignals = [
                    'recipient address rejected',
                    'invalid recipient',
                    'user unknown',
                    'mailbox unavailable',
                    'no such user',
                    'unknown mailbox',
                    'invalid address',
                    'address rejected',
                ];

                if ($errorScope === 'sender') {
                    foreach ($senderSignals as $index => $signal) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->{$method}('latest_recipient.error_message', 'like', '%'.$signal.'%');
                    }

                    return;
                }

                if ($errorScope === 'recipient') {
                    foreach ($recipientSignals as $index => $signal) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->{$method}('latest_recipient.error_message', 'like', '%'.$signal.'%');
                    }

                    return;
                }

                foreach (array_merge($senderSignals, $recipientSignals) as $index => $signal) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('latest_recipient.error_message', 'not like', '%'.$signal.'%');
                }
            });
        }

        $problemContacts = $problemContactsQuery
            ->orderByDesc(DB::raw('COALESCE(latest_recipient.updated_at, contacts.updated_at)'))
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'blocked' => Contact::query()->where('status', 'blocked')->count(),
            'invalid_email' => Contact::query()->where('status', 'invalid_email')->count(),
            'sender_side' => Contact::query()
                ->whereIn('status', ['blocked', 'invalid_email'])
                ->get()
                ->filter(fn (Contact $contact) => MailFailureClassifier::isRetryableSenderFailure(
                    $contact->campaignRecipients()->latest('updated_at')->value('error_message')
                ))
                ->count(),
        ];

        return view('admin.contact-issues', $this->sharedData([
            'pageTitle' => 'Kontak Bermasalah',
            'pageDescription' => 'Audit kontak yang pernah diblokir atau ditandai invalid, lengkap dengan error terakhir dan aksi unblock.',
            'problemContacts' => $problemContacts,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'error_scope' => $errorScope,
            ],
            'problemSummary' => $summary,
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

    public function exportCampaignRecipients(Campaign $campaign): Response
    {
        $campaign->load([
            'importBatch',
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheetTitle = Str::limit(Str::title(Str::slug($campaign->name, ' ')), 28, '');
        $sheet->setTitle($sheetTitle !== '' ? $sheetTitle : 'Recipients');

        $targetLabel = $campaign->importBatch?->title ?: ($campaign->segment ?: 'all');

        $sheet->setCellValue('A1', 'Export Daftar Penerima Campaign');
        $sheet->setCellValue('A2', 'Campaign');
        $sheet->setCellValue('B2', $campaign->name);
        $sheet->setCellValue('A3', 'Target');
        $sheet->setCellValue('B3', $targetLabel);
        $sheet->setCellValue('A4', 'Diexport Pada');
        $sheet->setCellValue('B4', now()->format('Y-m-d H:i:s'));

        $headers = [
            'No',
            'Campaign',
            'Target',
            'Kontak',
            'Email',
            'Phone',
            'Sender',
            'Status',
            'Queued At',
            'Sent At',
            'Failed At',
            'Error',
        ];

        foreach ($headers as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue("{$column}6", $header);
        }

        $currentRow = 7;
        $rowNumber = 1;

        $campaign->recipients()
            ->with(['contact', 'senderAccount'])
            ->orderBy('id')
            ->chunk(500, function ($recipients) use ($sheet, $campaign, $targetLabel, &$currentRow, &$rowNumber) {
                foreach ($recipients as $recipient) {
                    $sheet->fromArray([
                        $rowNumber,
                        $campaign->name,
                        $targetLabel,
                        $recipient->contact?->name ?: '-',
                        $recipient->contact?->email ?: '-',
                        $recipient->contact?->phone ?: '-',
                        $recipient->senderAccount?->from_address ?: '-',
                        $this->campaignRecipientStatusLabel($recipient->status),
                        $recipient->queued_at?->format('Y-m-d H:i:s') ?: '',
                        $recipient->sent_at?->format('Y-m-d H:i:s') ?: '',
                        $recipient->failed_at?->format('Y-m-d H:i:s') ?: '',
                        $recipient->error_message ?: '',
                    ], null, "A{$currentRow}");

                    $currentRow++;
                    $rowNumber++;
                }
            });

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $lastDataRow = max(6, $currentRow - 1);

        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A6:L6')->getFont()->setBold(true);
        $sheet->getStyle('A6:L6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9EAFD');
        $sheet->getStyle("A6:L{$lastDataRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("D7:L{$lastDataRow}")->getAlignment()->setWrapText(true);
        $sheet->freezePane('A7');
        $sheet->setAutoFilter("A6:L{$lastDataRow}");

        $fileName = 'campaign-recipients-'.$campaign->id.'-'.Str::slug($campaign->name).'.xlsx';

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    protected function campaignRecipientStatusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'Terkirim',
            'queued' => 'Antre',
            'failed' => 'Gagal',
            'cancelled' => 'Dibatalkan',
            default => Str::headline($status),
        };
    }

    public function analytics(): View
    {
        return view('admin.analytics', $this->sharedData());
    }

    public function unblockContact(Contact $contact, Request $request): RedirectResponse
    {
        $contact->forceFill([
            'status' => 'active',
            'email_opt_out' => false,
        ])->save();

        return redirect()
            ->to($request->input('redirect_to', route('admin.contacts.issues')))
            ->with('status', "Kontak {$contact->email} berhasil di-unblock.");
    }

    public function unblockFilteredContacts(Request $request): RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:all,blocked,invalid_email'],
            'error_scope' => ['nullable', 'string', 'in:all,sender,recipient,other'],
        ]);

        $statusFilter = $filters['status'] ?? 'blocked';
        $errorScope = $filters['error_scope'] ?? 'all';
        $search = trim((string) ($filters['q'] ?? ''));

        $query = Contact::query()
            ->whereIn('status', ['blocked', 'invalid_email']);

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('school', 'like', "%{$search}%")
                    ->orWhere('field', 'like', "%{$search}%");
            });
        }

        $contacts = $query->with(['campaignRecipients' => fn ($recipientQuery) => $recipientQuery->latest('updated_at')])->get();

        $contactsToUnblock = $contacts->filter(function (Contact $contact) use ($errorScope) {
            $message = $contact->campaignRecipients->first()?->error_message;

            return match ($errorScope) {
                'sender' => MailFailureClassifier::isRetryableSenderFailure($message),
                'recipient' => MailFailureClassifier::isPermanentContactFailure($message),
                'other' => ! MailFailureClassifier::isRetryableSenderFailure($message) && ! MailFailureClassifier::isPermanentContactFailure($message),
                default => true,
            };
        });

        if ($contactsToUnblock->isEmpty()) {
            return redirect()
                ->route('admin.contacts.issues', $filters)
                ->withErrors(['contacts' => 'Tidak ada kontak yang cocok untuk di-unblock.']);
        }

        Contact::query()
            ->whereIn('id', $contactsToUnblock->pluck('id'))
            ->update([
                'status' => 'active',
                'email_opt_out' => false,
            ]);

        return redirect()
            ->route('admin.contacts.issues', $filters)
            ->with('status', "{$contactsToUnblock->count()} kontak berhasil di-unblock.");
    }

    protected function sharedData(array $extra = []): array
    {
        $senderAccounts = SenderAccount::latest()->get();
        $activeSenderAccounts = $senderAccounts->where('is_active', true)->values();
        $senderQuotaPool = [
            'active_senders' => $activeSenderAccounts->count(),
            'daily_total_limit' => $activeSenderAccounts->sum('daily_limit'),
            'daily_used' => $activeSenderAccounts->sum(fn (SenderAccount $sender) => $sender->effectiveSentToday()),
            'daily_remaining' => $activeSenderAccounts->sum(fn (SenderAccount $sender) => $sender->remainingDailyQuota()),
            'hourly_total_limit' => $activeSenderAccounts->sum('hourly_limit'),
            'hourly_used' => $activeSenderAccounts->sum(fn (SenderAccount $sender) => $sender->effectiveSentThisHour()),
            'hourly_remaining' => $activeSenderAccounts->sum(fn (SenderAccount $sender) => $sender->remainingHourlyQuota()),
        ];

        $stats = [
            'contacts' => Contact::count(),
            'emailable_contacts' => Contact::whereNotNull('email')->where('email_opt_out', false)->count(),
            'senders' => SenderAccount::count(),
            'campaigns' => Campaign::count(),
            'queued_recipients' => CampaignRecipient::where('status', 'queued')->count(),
            'sent_recipients' => CampaignRecipient::where('status', 'sent')->count(),
            'problem_contacts' => Contact::whereIn('status', ['invalid_email', 'blocked'])->count(),
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
            'senderQuotaPool' => $senderQuotaPool,
        ], $extra);
    }
}
