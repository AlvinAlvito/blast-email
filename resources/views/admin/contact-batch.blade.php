@extends('layouts.admin')

@php
    $pageTitle = $importBatch->title;
    $pageDescription = 'Daftar kontak yang berasal dari satu file import.';
@endphp

@section('content')
    <div class="stack">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>{{ $importBatch->title }}</h3>
                    <p>{{ $importBatch->file_name }}</p>
                </div>
                <a class="button-secondary" href="{{ route('admin.contacts') }}">Kembali ke Batch</a>
            </div>
            <div class="kpi-strip">
                <div class="kpi-pill">{{ number_format($importBatch->rows_scanned) }} baris dipindai</div>
                <div class="kpi-pill">{{ number_format($importBatch->contacts_created) }} kontak baru</div>
                <div class="kpi-pill">{{ number_format($importBatch->contacts_updated) }} kontak update</div>
                <div class="kpi-pill">{{ number_format($importBatch->skipped) }} dilewati</div>
                <div class="kpi-pill">{{ $importBatch->imported_at?->format('d M Y H:i:s') ?: '-' }}</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><div><h3>Isi Kontak</h3><p>Kontak yang tersimpan dari file ini.</p></div></div>
            @if ($contacts->isEmpty())
                <div class="empty">Batch ini belum memiliki kontak.</div>
            @else
                <div class="contacts-grid">
                    @foreach ($contacts as $contact)
                        <div class="contact-row">
                            <div><div class="contact-name">{{ $contact->name ?: '-' }}</div><div class="contact-sub">No import: {{ $contact->import_no ?: '-' }}</div></div>
                            <div><div class="contact-name">{{ $contact->email ?: '-' }}</div><div class="contact-sub">{{ $contact->phone ?: '-' }}</div></div>
                            <div><div class="contact-name">{{ $contact->province ?: '-' }}</div><div class="contact-sub">{{ $contact->city ?: '-' }}</div></div>
                            <div><div class="contact-name">{{ $contact->education_level ?: '-' }}</div><div class="contact-sub">Segment: {{ $contact->segment ?: '-' }}</div></div>
                        </div>
                    @endforeach
                </div>
                <div style="margin-top:16px;">{{ $contacts->links() }}</div>
            @endif
        </div>
    </div>
@endsection
