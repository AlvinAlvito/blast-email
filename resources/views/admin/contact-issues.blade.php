@extends('layouts.admin')

@php
    $pageTitle = 'Kontak Bermasalah';
    $pageDescription = 'Audit kontak yang pernah gagal keras, diblokir sistem, atau perlu diaktifkan kembali setelah masalah sender diselesaikan.';
@endphp

@section('content')
    <div class="stack">
        <div class="metrics">
            <div class="card">
                <div class="card-top"><div><div class="card-label">Blocked</div><div class="card-value">{{ number_format($problemSummary['blocked']) }}</div></div><div class="card-note">BLK</div></div>
                <div class="kpi-pill">Kontak yang sedang ditahan sistem dan dilewati saat retry.</div>
            </div>
            <div class="card">
                <div class="card-top"><div><div class="card-label">Invalid Email</div><div class="card-value">{{ number_format($problemSummary['invalid_email']) }}</div></div><div class="card-note">INV</div></div>
                <div class="kpi-pill">Kontak yang pernah dianggap email tidak valid atau mailbox tidak ditemukan.</div>
            </div>
            <div class="card">
                <div class="card-top"><div><div class="card-label">Sender-Side</div><div class="card-value">{{ number_format($problemSummary['sender_side']) }}</div></div><div class="card-note">SMTP</div></div>
                <div class="kpi-pill">Kontak yang kemungkinan diblokir karena auth, quota, atau sender suspended.</div>
            </div>
            <div class="card">
                <div class="card-top"><div><div class="card-label">Terfilter</div><div class="card-value">{{ number_format($problemContacts->total()) }}</div></div><div class="card-note">QRY</div></div>
                <div class="kpi-pill">Total kontak yang cocok dengan search dan filter saat ini.</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>Filter Kontak Error</h3>
                    <p>Cari cepat kontak yang terblokir, lihat error terakhir, lalu unblock satuan atau sekaligus.</p>
                </div>
                <form action="{{ route('admin.contacts.unblock-filtered') }}" method="post" class="topbar-actions">
                    @csrf
                    <input type="hidden" name="q" value="{{ $filters['q'] }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <input type="hidden" name="error_scope" value="{{ $filters['error_scope'] }}">
                    <button class="button" type="submit">Unblock Hasil Filter</button>
                </form>
            </div>

            <form method="get" action="{{ route('admin.contacts.issues') }}" class="form-grid">
                <div class="form-row">
                    <label>
                        Cari nama / email / sekolah / error
                        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Mis. gmail, Budi, si-pental, mailbox unavailable">
                    </label>
                    <label>
                        Status kontak
                        <select name="status">
                            <option value="blocked" @selected($filters['status'] === 'blocked')>Blocked saja</option>
                            <option value="invalid_email" @selected($filters['status'] === 'invalid_email')>Invalid email saja</option>
                            <option value="all" @selected($filters['status'] === 'all')>Semua status error</option>
                        </select>
                    </label>
                </div>
                <div class="form-row">
                    <label>
                        Scope error terakhir
                        <select name="error_scope">
                            <option value="all" @selected($filters['error_scope'] === 'all')>Semua jenis error</option>
                            <option value="sender" @selected($filters['error_scope'] === 'sender')>Masalah sender / SMTP</option>
                            <option value="recipient" @selected($filters['error_scope'] === 'recipient')>Masalah recipient / mailbox</option>
                            <option value="other" @selected($filters['error_scope'] === 'other')>Lainnya</option>
                        </select>
                    </label>
                    <label>
                        Aksi
                        <div class="topbar-actions">
                            <button class="button" type="submit">Terapkan Filter</button>
                            <a class="button-secondary" href="{{ route('admin.contacts.issues') }}">Reset</a>
                        </div>
                    </label>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>Daftar Kontak Bermasalah</h3>
                    <p>Menampilkan status kontak, sender terakhir yang terlibat, campaign terakhir, dan error paling baru.</p>
                </div>
            </div>
            @if ($problemContacts->isEmpty())
                <div class="empty">Tidak ada kontak bermasalah yang cocok dengan filter saat ini.</div>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Kontak</th>
                        <th>Status</th>
                        <th>Sender Terakhir</th>
                        <th>Campaign</th>
                        <th>Error Terakhir</th>
                        <th>Waktu</th>
                        <th>Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($problemContacts as $contact)
                        <tr>
                            <td>
                                <strong>{{ $contact->name ?: '-' }}</strong><br>
                                <span class="contact-sub">{{ $contact->email ?: '-' }}</span>
                                @if ($contact->school)
                                    <br><span class="contact-sub">{{ $contact->school }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="status {{ $contact->status === 'invalid_email' ? 'failed' : 'queued' }}">{{ $contact->status }}</span>
                            </td>
                            <td>{{ $contact->latest_sender_address ?: '-' }}</td>
                            <td>{{ $contact->latest_campaign_id ? '#'.$contact->latest_campaign_id : '-' }}</td>
                            <td>{{ $contact->latest_error_message ?: '-' }}</td>
                            <td>{{ optional($contact->latest_error_at ?? $contact->updated_at)->format('d M Y H:i:s') ?: '-' }}</td>
                            <td>
                                <form method="post" action="{{ route('admin.contacts.unblock', $contact->id) }}">
                                    @csrf
                                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                    <button class="button-ghost" type="submit">Unblock</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div style="margin-top:16px;">{{ $problemContacts->links('vendor.pagination.dashboard') }}</div>
            @endif
        </div>
    </div>
@endsection
