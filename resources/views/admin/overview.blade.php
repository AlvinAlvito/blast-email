@extends('layouts.admin')

@php
    $pageTitle = 'Overview';
    $pageDescription = 'Ringkasan performa data kontak, kesiapan pengiriman, dan snapshot pengiriman terbaru.';
    $maxLevel = max(1, (int) ($contactsByLevel->max('total') ?? 1));
@endphp

@section('content')
    <div class="metrics">
        <div class="card"><div class="card-top"><div><div class="card-label">Total Contacts</div><div class="card-value">{{ number_format($stats['contacts']) }}</div></div><div class="card-note">DB</div></div><div class="kpi-pill">Semua data kontak di sistem.</div></div>
        <div class="card"><div class="card-top"><div><div class="card-label">Ready For Email</div><div class="card-value">{{ number_format($stats['emailable_contacts']) }}</div></div><div class="card-note">EM</div></div><div class="kpi-pill">Kontak dengan email dan belum opt-out.</div></div>
        <div class="card"><div class="card-top"><div><div class="card-label">Queued Emails</div><div class="card-value">{{ number_format($stats['queued_recipients']) }}</div></div><div class="card-note">Q</div></div><div class="kpi-pill">Recipient yang sudah masuk antrean.</div></div>
        <div class="card"><div class="card-top"><div><div class="card-label">Sent Emails</div><div class="card-value">{{ number_format($stats['sent_recipients']) }}</div></div><div class="card-note">OK</div></div><div class="kpi-pill">Recipient yang berhasil dikirim.</div></div>
    </div>

    <div class="content-grid">
        <div class="stack">
            <div class="panel">
                <div class="panel-header"><div><h3>Distribusi Kontak per Jenjang</h3><p>Komposisi audiens utama berdasarkan data import.</p></div></div>
                @if ($contactsByLevel->isEmpty())
                    <div class="empty">Belum ada data kontak untuk divisualisasikan.</div>
                @else
                    <div class="chart-shell">
                        @foreach ($contactsByLevel as $row)
                            <div class="chart-col">
                                <div class="chart-value">{{ number_format($row->total) }}</div>
                                <div class="chart-bar" style="height: {{ max(16, (int) round(($row->total / $maxLevel) * 180)) }}px;"></div>
                                <div class="chart-label">{{ $row->education_level ?: 'Unknown' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="panel">
                <div class="panel-header"><div><h3>Pengiriman Terbaru</h3><p>Ringkasan status antrean dan hasil kirim terbaru.</p></div><a class="button-secondary" href="{{ route('admin.campaigns') }}">Kelola</a></div>
                @if ($campaigns->isEmpty())
                    <div class="empty">Belum ada pengiriman.</div>
                @else
                    <table>
                        <thead><tr><th>Nama</th><th>Target</th><th>Terkirim</th><th>Antre</th><th>Gagal</th></tr></thead>
                        <tbody>
                        @foreach ($campaigns as $campaign)
                            <tr><td>{{ $campaign->name }}</td><td>{{ $campaign->segment ?: 'all' }}</td><td>{{ number_format($campaign->sent_count) }}</td><td>{{ number_format($campaign->queued_count) }}</td><td>{{ number_format($campaign->failed_count) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
        <div class="stack">
            <div class="panel">
                <div class="panel-header"><div><h3>Sender Health</h3><p>Utilisasi harian sender SMTP yang tersedia.</p></div></div>
                @if ($senderAccounts->isEmpty())
                    <div class="empty">Belum ada sender account.</div>
                @else
                    <div class="bar-list">
                        @foreach ($senderAccounts as $sender)
                            @php
                                $sentToday = $sender->effectiveSentToday();
                                $ratio = $sender->daily_limit > 0 ? min(100, (int) round(($sentToday / $sender->daily_limit) * 100)) : 0;
                            @endphp
                            <div class="bar-item"><div class="bar-meta"><strong>{{ $sender->name }}</strong><span>{{ $sentToday }}/{{ $sender->daily_limit }}</span></div><div class="bar-track"><div class="bar-fill" style="width: {{ $ratio }}%"></div></div></div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="panel">
                <div class="panel-header"><div><h3>Kontak Baru</h3><p>Kontak terakhir yang masuk ke database.</p></div><a class="button-secondary" href="{{ route('admin.contacts') }}">Lihat Semua</a></div>
                @if ($recentContacts->isEmpty())
                    <div class="empty">Belum ada data kontak.</div>
                @else
                    <div class="mini-grid">
                        @foreach ($recentContacts as $contact)
                            <div class="contact-row">
                                <div><div class="contact-name">{{ $contact->name ?: '-' }}</div><div class="contact-sub">{{ $contact->email ?: '-' }}</div></div>
                                <div><div class="contact-name">{{ $contact->phone ?: '-' }}</div><div class="contact-sub">{{ $contact->province ?: '-' }}</div></div>
                                <div><div class="contact-name">{{ $contact->city ?: '-' }}</div><div class="contact-sub">Kota</div></div>
                                <div><div class="contact-name">{{ $contact->education_level ?: '-' }}</div><div class="contact-sub">Jenjang</div></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
