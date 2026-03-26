<?php

namespace App\Http\Controllers;

use App\Services\ContactImportService;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function downloadTemplate(): Response
    {
        $headers = implode(',', ContactImportService::REQUIRED_HEADERS);
        $sample = implode(',', ['1', 'Budi Santoso', 'budi@example.com', '081234567890', 'Jawa Barat', 'Bandung', 'SMA']);
        $content = $headers."\r\n".$sample."\r\n";

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="format-import-kontak-posi.csv"',
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
