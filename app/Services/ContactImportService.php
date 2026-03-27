<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ImportBatch;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;

class ContactImportService
{
    public const REQUIRED_HEADERS = [
        'no',
        'nama',
        'email',
        'no hp',
        'provinsi',
        'kota',
        'jenjang',
    ];

    protected const EDUCATION_LEVEL_KEYWORDS = [
        'sd' => 'SD',
        'mi' => 'SD',
        'sdit' => 'SD',
        'smp' => 'SMP',
        'mts' => 'SMP',
        'mtsn' => 'SMP',
        'sma' => 'SMA',
        'ma' => 'SMA',
        'smk' => 'SMK',
        'smkn' => 'SMK',
        'kuliah' => 'Mahasiswa',
        'mahasiswa' => 'Mahasiswa',
        'kampus' => 'Mahasiswa',
        'guru' => 'Guru',
        'dosen' => 'Dosen',
    ];

    public function import(string $filePath, string $originalFileName): array
    {
        @ini_set('memory_limit', '1024M');

        $batch = ImportBatch::create([
            'title' => pathinfo($originalFileName, PATHINFO_FILENAME).' - '.Carbon::now()->format('d M Y H:i:s'),
            'file_name' => $originalFileName,
            'stored_path' => $filePath,
            'imported_at' => now(),
        ]);

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $summary = [
            'sheets' => 0,
            'rows_scanned' => 0,
            'contacts_created' => 0,
            'contacts_updated' => 0,
            'skipped' => 0,
        ];

        foreach ($reader->listWorksheetInfo($filePath) as $sheetInfo) {
            $summary['sheets']++;
            $sheetName = $sheetInfo['worksheetName'];
            $year = $this->extractYear($sheetName);
            $headers = $this->readHeaders($reader, $filePath, $sheetName);

            if ($headers->isEmpty()) {
                continue;
            }

            $this->assertRequiredHeaders($headers, $sheetName);

            $chunkFilter = new SpreadsheetChunkReadFilter();
            $chunkSize = 250;
            $totalRows = (int) ($sheetInfo['totalRows'] ?? 0);

            for ($startRow = 2; $startRow <= $totalRows; $startRow += $chunkSize) {
                $chunkFilter->setRows($startRow, $chunkSize);
                $chunkReader = IOFactory::createReaderForFile($filePath);
                $chunkReader->setReadDataOnly(true);
                if (method_exists($chunkReader, 'setReadEmptyCells')) {
                    $chunkReader->setReadEmptyCells(false);
                }
                $chunkReader->setLoadSheetsOnly($sheetName);
                $chunkReader->setReadFilter($chunkFilter);

                $spreadsheet = $chunkReader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestColumn = $worksheet->getHighestColumn();
                $endRow = min($startRow + $chunkSize - 1, $totalRows);
                $rows = $worksheet->rangeToArray("A{$startRow}:{$highestColumn}{$endRow}", null, true, true, false);

                foreach ($rows as $row) {
                    if (collect($row)->filter(fn ($value) => filled($value))->isEmpty()) {
                        continue;
                    }

                    $summary['rows_scanned']++;
                    $payload = $this->mapRow($headers, $row, $sheetName, $year);

                    if (! $payload['email'] && ! $payload['phone'] && ! $payload['telegram']) {
                        $summary['skipped']++;
                        continue;
                    }

                        $existing = Contact::query()
                        ->when(
                            $payload['email'],
                            fn ($query) => $query->where('email', $payload['email']),
                            fn ($query) => $query->where('phone', $payload['phone'])
                        )
                        ->first();

                    if ($existing) {
                        $existing->fill(array_filter($payload, fn ($value) => $value !== null && $value !== ''));
                        $existing->import_batch_id = $batch->id;
                        $existing->save();
                        $summary['contacts_updated']++;
                        continue;
                    }

                    Contact::create([
                        ...$payload,
                        'import_batch_id' => $batch->id,
                    ]);
                    $summary['contacts_created']++;
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
        }

        $batch->update($summary);

        return [
            ...$summary,
            'batch_id' => $batch->id,
            'batch_title' => $batch->title,
        ];
    }

    protected function readHeaders(IReader $reader, string $filePath, string $sheetName): Collection
    {
        $headerFilter = new SpreadsheetChunkReadFilter(1, 1);
        $headerFilter->setRows(1, 1);
        $reader->setLoadSheetsOnly($sheetName);
        $reader->setReadFilter($headerFilter);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestColumn = $worksheet->getHighestColumn();
        $headerRow = $worksheet->rangeToArray("A1:{$highestColumn}1", null, true, true, false)[0] ?? [];
        $spreadsheet->disconnectWorksheets();

        return $this->normalizeHeaders($headerRow)->filter();
    }

    protected function normalizeHeaders(array $headers): Collection
    {
        return collect($headers)->map(function ($header) {
            $header = Str::lower(trim((string) $header));

            return Str::of($header)
                ->replace(['/', '\\', '-', '.', '(', ')'], ' ')
                ->squish()
                ->value();
        });
    }

    protected function assertRequiredHeaders(Collection $headers, string $sheetName): void
    {
        $normalized = $headers->filter()->values()->all();
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $normalized));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'contacts_file' => [
                    'Header sheet "'.$sheetName.'" tidak sesuai format. Header wajib: '.implode(', ', self::REQUIRED_HEADERS).'.',
                    'Kolom yang belum ditemukan: '.implode(', ', $missing).'.',
                ],
            ]);
        }
    }

    protected function mapRow(Collection $headers, array $row, string $sheetTitle, ?string $year): array
    {
        $pairs = $headers->mapWithKeys(fn ($header, $index) => [$header => trim((string) ($row[$index] ?? ''))]);

        $email = $this->extractByKeywords($pairs, ['email', 'e mail', 'mail']);
        $phone = $this->normalizePhone($this->extractByKeywords($pairs, ['no hp', 'nomor hp', 'hp', 'no telepon', 'telepon', 'telp', 'phone']));
        $name = $this->normalizeName($this->extractByKeywords($pairs, ['nama', 'nama lengkap', 'name']));
        $importNo = $this->extractByKeywords($pairs, ['no', 'nomor']);
        $province = $this->normalizeProvince($this->extractByKeywords($pairs, ['provinsi']));
        $city = $this->normalizeCity($this->extractByKeywords($pairs, ['kota', 'kabupaten']));
        $educationLevel = $this->normalizeEducationLevel($this->extractByKeywords($pairs, ['jenjang']));

        return [
            'import_no' => $importNo ?: null,
            'name' => $name ?: null,
            'email' => ($normalizedEmail = $this->normalizeEmail($email)) && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) ? $normalizedEmail : null,
            'phone' => $phone ?: null,
            'province' => $province ?: null,
            'city' => $city ?: null,
            'education_level' => $educationLevel ?: null,
            'telegram' => null,
            'source_sheet' => $sheetTitle,
            'source_year' => $year,
            'segment' => $educationLevel ? Str::slug($educationLevel) : ($year ? 'alumni-'.$year : null),
            'status' => 'active',
            'meta' => $pairs->toArray(),
        ];
    }

    protected function extractByKeywords(Collection $pairs, array $keywords): ?string
    {
        foreach ($keywords as $keyword) {
            $match = $pairs->first(fn ($value, $header) => Str::contains($header, $keyword));

            if (filled($match)) {
                return trim((string) $match);
            }
        }

        return null;
    }

    protected function normalizePhone(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value ?? '');

        if (! $digits) {
            return null;
        }

        if (Str::startsWith($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (! Str::startsWith($digits, '62')) {
            return '62'.$digits;
        }

        return $digits;
    }

    protected function normalizeEmail(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return Str::lower(trim((string) $value));
    }

    protected function normalizeName(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return Str::title(Str::lower($value));
    }

    protected function normalizeProvince(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = $this->normalizeLabel($value);
        $compact = Str::lower($normalized);

        $provinceMap = [
            'dki jakarta' => 'DKI Jakarta',
            'jakarta' => 'DKI Jakarta',
            'jawa barat' => 'Jawa Barat',
            'jabar' => 'Jawa Barat',
            'jawa tengah' => 'Jawa Tengah',
            'jateng' => 'Jawa Tengah',
            'jawa timur' => 'Jawa Timur',
            'jatim' => 'Jawa Timur',
            'di yogyakarta' => 'DI Yogyakarta',
            'diy' => 'DI Yogyakarta',
            'yogyakarta' => 'DI Yogyakarta',
            'banten' => 'Banten',
            'bali' => 'Bali',
            'sumatera utara' => 'Sumatera Utara',
            'sumut' => 'Sumatera Utara',
            'sumatera barat' => 'Sumatera Barat',
            'sumbar' => 'Sumatera Barat',
            'sumatera selatan' => 'Sumatera Selatan',
            'sumsel' => 'Sumatera Selatan',
            'kalimantan timur' => 'Kalimantan Timur',
            'kaltim' => 'Kalimantan Timur',
            'kalimantan barat' => 'Kalimantan Barat',
            'kalbar' => 'Kalimantan Barat',
            'kalimantan tengah' => 'Kalimantan Tengah',
            'kalteng' => 'Kalimantan Tengah',
            'kalimantan selatan' => 'Kalimantan Selatan',
            'kalsel' => 'Kalimantan Selatan',
            'sulawesi selatan' => 'Sulawesi Selatan',
            'sulsel' => 'Sulawesi Selatan',
            'sulawesi utara' => 'Sulawesi Utara',
            'sulut' => 'Sulawesi Utara',
            'lampung' => 'Lampung',
            'riau' => 'Riau',
        ];

        return $provinceMap[$compact] ?? $normalized;
    }

    protected function normalizeCity(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = $this->normalizeLabel($value);

        $normalized = preg_replace('/^(kota|kabupaten)\s+/i', '', $normalized) ?: $normalized;

        return $normalized;
    }

    protected function normalizeEducationLevel(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = Str::lower($this->normalizeLabel($value));

        foreach (self::EDUCATION_LEVEL_KEYWORDS as $keyword => $result) {
            if (str_contains($normalized, $keyword)) {
                return $result;
            }
        }

        return Str::upper($normalized);
    }

    protected function normalizeLabel(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim((string) $value);
        $value = Str::of($value)
            ->replace(['_', '-', '.'], ' ')
            ->squish()
            ->value();

        return Str::title(Str::lower($value));
    }

    protected function extractYear(string $text): ?string
    {
        preg_match('/(20\d{2})/', $text, $matches);

        return $matches[1] ?? null;
    }
}
