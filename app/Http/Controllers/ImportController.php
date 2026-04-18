<?php

namespace App\Http\Controllers;

use App\Services\ContactImportService;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    public function downloadTemplate(): Response
    {
        $headers = [
            ...ContactImportService::REQUIRED_HEADERS,
            'sekolah',
            'bidang',
            'no peserta',
            'link kartu peserta',
        ];

        $sample = [
            '1',
            'Budi Santoso',
            'budi@example.com',
            '081234567890',
            'Jawa Barat',
            'Bandung',
            'SMA',
            'SMA Negeri 3 Bandung',
            'Matematika',
            'POSI-2026-001',
            'https://contoh.posi.id/kartu/posi-2026-001',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($sample, null, 'A2');

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="format-import-kontak-posi.xlsx"',
        ]);
    }

    public function store(Request $request, ContactImportService $importService): RedirectResponse
    {
        $request->validate([
            'contacts_file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $uploadedFile = $request->file('contacts_file');
        $path = $uploadedFile->store('imports');
        $fullPath = Storage::disk('local')->path($path);
        $summary = $importService->import($fullPath, $uploadedFile->getClientOriginalName());

        return redirect()->route('admin.contacts')
            ->with('status', 'Import selesai.')
            ->with('import_summary', $summary);
    }
}
