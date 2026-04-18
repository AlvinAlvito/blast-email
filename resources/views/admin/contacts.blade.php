@extends('layouts.admin')

@php
    $pageTitle = 'Contacts';
    $pageDescription = 'Impor dan audit database kontak berdasarkan format Excel resmi.';
    $maxProvince = max(1, (int) ($contactsByProvince->max('total') ?? 1));
@endphp

@section('content')
    <div class="content-grid">
        <div class="stack">
            <div class="panel">
                <div class="panel-header"><div><h3>Import Kontak</h3><p>Gunakan format resmi agar mapping kolom sesuai database.</p></div><a class="button-secondary" href="{{ route('imports.template') }}">Download Format CSV</a></div>
                <form action="{{ route('imports.store') }}" method="post" enctype="multipart/form-data" class="form-grid">
                    @csrf
                    <label>File kontak<input type="file" name="contacts_file" required></label>
                    <div class="kpi-strip">
                        <div class="kpi-pill">Header wajib: `no, nama, email, no hp, provinsi, kota, jenjang`</div>
                        <div class="kpi-pill">Header opsional: `sekolah, bidang, no peserta, link kartu peserta`</div>
                        <div class="kpi-pill">Mendukung `.xlsx`, `.xls`, `.csv`</div>
                        <div class="kpi-pill">Sistem akan merapikan otomatis nilai seperti `SMA kelas 1`, `jabar`, atau `kab. bandung`.</div>
                    </div>
                    <button class="button" type="submit">Upload dan Import</button>
                </form>
            </div>
            <div class="panel">
                <div class="panel-header"><div><h3>Batch Import</h3><p>Setiap file upload ditampilkan sebagai satu grup agar lebih hemat dan mudah ditelusuri.</p></div></div>
                @if ($importBatches->isEmpty())
                    <div class="empty">Belum ada file yang diimport.</div>
                @else
                    <div class="contacts-grid">
                        @foreach ($importBatches as $batch)
                            <div class="panel" style="padding:18px; border-radius:18px; box-shadow:none;">
                                <div class="bar-meta">
                                    <div>
                                        <div class="contact-name">{{ $batch->title }}</div>
                                        <div class="contact-sub">{{ $batch->file_name }}</div>
                                    </div>
                                    <a class="button-ghost" href="{{ route('admin.contacts.batch', $batch) }}">Lihat Kontak</a>
                                </div>
                                <div class="kpi-strip" style="margin-top:12px;">
                                    <div class="kpi-pill">{{ number_format($batch->contacts_count) }} kontak terkait batch ini</div>
                                    <div class="kpi-pill">{{ number_format($batch->contacts_created) }} baru</div>
                                    <div class="kpi-pill">{{ number_format($batch->contacts_updated) }} update</div>
                                    <div class="kpi-pill">{{ number_format($batch->skipped) }} dilewati</div>
                                    <div class="kpi-pill">{{ $batch->imported_at?->format('d M Y H:i:s') ?: '-' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div style="margin-top:16px;">{{ $importBatches->links('vendor.pagination.dashboard') }}</div>
                @endif
            </div>
        </div>
        <div class="stack">
            <div class="panel">
                <div class="panel-header"><div><h3>Sebaran Provinsi</h3><p>Wilayah dominan berdasarkan data kontak.</p></div></div>
                @if ($contactsByProvince->isEmpty())
                    <div class="empty">Chart akan muncul setelah ada data provinsi.</div>
                @else
                    <div class="bar-list">
                        @foreach ($contactsByProvince as $row)
                            <div class="bar-item"><div class="bar-meta"><strong>{{ $row->province }}</strong><span>{{ number_format($row->total) }}</span></div><div class="bar-track"><div class="bar-fill" style="width: {{ max(8, (int) round(($row->total / $maxProvince) * 100)) }}%"></div></div></div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="panel">
                <div class="panel-header"><div><h3>Checklist Import</h3><p>Gunakan ini sebelum upload file besar.</p></div></div>
                <div class="mini-grid">
                    <div class="kpi-pill">Pastikan header sesuai format resmi.</div>
                    <div class="kpi-pill">Kolom `sekolah`, `bidang`, `no peserta`, dan `link kartu peserta` boleh kosong atau tidak ada.</div>
                    <div class="kpi-pill">Nomor HP sebaiknya konsisten dan aktif.</div>
                    <div class="kpi-pill">Jenjang akan dinormalisasi dan dipakai sebagai segment campaign.</div>
                    <div class="kpi-pill">Email tidak valid akan diabaikan dari blast email.</div>
                </div>
            </div>
        </div>
    </div>
@endsection
