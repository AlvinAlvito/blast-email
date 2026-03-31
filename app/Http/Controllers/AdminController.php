<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\SenderAccount;
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
