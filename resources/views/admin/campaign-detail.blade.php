@extends('layouts.admin')

@php
    $pageTitle = $campaignDetail->name;
    $pageDescription = 'Detail isi email, statistik pengiriman, dan daftar penerima untuk pengiriman ini.';
    $shouldPoll = in_array($campaignDetail->status, ['queued', 'paused'], true);
@endphp

@section('content')
    <div class="stack">
        <div class="metrics">
            <div class="card">
                <div class="card-top"><div><div class="card-label">Sent</div><div class="card-value">{{ number_format($campaignDetail->recipients->where('status', 'sent')->count()) }}</div></div><div class="card-note">OK</div></div>
                <div class="kpi-pill">Recipient yang berhasil dikirim.</div>
            </div>
            <div class="card">
                <div class="card-top"><div><div class="card-label">Antre</div><div class="card-value" data-summary="queued">{{ number_format($campaignDetail->recipients->where('status', 'queued')->count()) }}</div></div><div class="card-note">Q</div></div>
                <div class="kpi-pill">Penerima yang masih menunggu diproses worker.</div>
            </div>
            <div class="card">
                <div class="card-top"><div><div class="card-label">Gagal</div><div class="card-value" data-summary="failed">{{ number_format($campaignDetail->recipients->whereIn('status', ['failed', 'cancelled'])->count()) }}</div></div><div class="card-note">ERR</div></div>
                <div class="kpi-pill">Penerima yang gagal atau dibatalkan.</div>
            </div>
            <div class="card">
                <div class="card-top"><div><div class="card-label">Target</div><div class="card-value" style="font-size:24px;">{{ $campaignDetail->importBatch?->title ?: ($campaignDetail->segment ?: 'all') }}</div></div><div class="card-note">SEG</div></div>
                <div class="kpi-pill">Batch import atau segment target pengiriman ini.</div>
            </div>
        </div>

        <div class="two-col">
            <div class="panel">
                <div class="panel-header">
                    <div><h3>Preview Email</h3><p>Isi email yang dikirim untuk pengiriman ini.</p></div>
                    <div class="topbar-actions">
                        <button class="button-secondary" type="button" id="campaign-refresh-button">Refresh</button>
                        @if ($campaignDetail->status === 'queued')
                            <form action="{{ route('campaigns.pause', $campaignDetail) }}" method="post">
                                @csrf
                                <button class="button-secondary" type="submit">Jeda</button>
                            </form>
                            <form action="{{ route('campaigns.stop', $campaignDetail) }}" method="post">
                                @csrf
                                <button class="button-danger" type="submit">Hentikan</button>
                            </form>
                        @elseif ($campaignDetail->status === 'paused')
                            <form action="{{ route('campaigns.resume', $campaignDetail) }}" method="post">
                                @csrf
                                <button class="button" type="submit">Lanjutkan</button>
                            </form>
                            <form action="{{ route('campaigns.stop', $campaignDetail) }}" method="post">
                                @csrf
                                <button class="button-danger" type="submit">Hentikan</button>
                            </form>
                        @endif
                        @if ($campaignDetail->recipients->where('status', 'failed')->count() > 0)
                            <form action="{{ route('campaigns.retry-failed', $campaignDetail) }}" method="post">
                                @csrf
                                <button class="button" type="submit">Coba Ulang yang Gagal</button>
                            </form>
                        @endif
                        <a class="button-secondary" href="{{ route('admin.campaigns') }}">Kembali</a>
                    </div>
                </div>
                <div class="mini-grid">
                    <div class="kpi-pill"><strong>Subject:</strong> {{ $campaignDetail->subject }}</div>
                    <div class="kpi-pill"><strong>Nama pengiriman:</strong> {{ $campaignDetail->name }}</div>
                </div>
                <div class="panel" style="margin-top:16px; box-shadow:none; padding:20px; border-radius:18px;">
                    <div style="font-size:15px; line-height:1.8; white-space:pre-wrap;">{{ $campaignDetail->body }}</div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><div><h3>Ringkasan Pengiriman</h3><p>Status keseluruhan dan waktu proses.</p></div></div>
                <div class="mini-grid">
                    <div class="kpi-pill">Status: <span data-campaign-status>{{ $campaignDetail->status }}</span></div>
                    <div class="kpi-pill">Target file: {{ $campaignDetail->importBatch?->file_name ?: '-' }}</div>
                    <div class="kpi-pill">Batch size: {{ $campaignDetail->batch_size }}</div>
                    <div class="kpi-pill">Delay: {{ $campaignDetail->delay_seconds }} detik</div>
                    <div class="kpi-pill">Started: {{ $campaignDetail->started_at?->format('d M Y H:i:s') ?: '-' }}</div>
                    <div class="kpi-pill">Finished: {{ $campaignDetail->finished_at?->format('d M Y H:i:s') ?: '-' }}</div>
                    <div class="kpi-pill">Created: {{ $campaignDetail->created_at?->format('d M Y H:i:s') ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><div><h3>Daftar Penerima</h3><p>Menampilkan kontak, status kirim, sender yang digunakan, dan error jika gagal.</p></div></div>
            @if ($campaignRecipients->isEmpty())
                <div class="empty">Belum ada penerima untuk pengiriman ini.</div>
            @else
                <table>
                    <thead>
                    <tr><th>Kontak</th><th>Email</th><th>Sender</th><th>Status</th><th>Waktu</th><th>Error</th></tr>
                    </thead>
                    <tbody id="campaign-recipient-tbody">
                    @foreach ($campaignRecipients as $recipient)
                        <tr>
                            <td>{{ $recipient->contact?->name ?: '-' }}</td>
                            <td>{{ $recipient->contact?->email ?: '-' }}</td>
                            <td>{{ $recipient->senderAccount?->from_address ?: '-' }}</td>
                            <td><span class="status {{ $recipient->status }}">{{ $recipient->status }}</span></td>
                            <td>{{ optional($recipient->sent_at ?? $recipient->queued_at ?? $recipient->failed_at)?->format('d M Y H:i:s') ?: '-' }}</td>
                            <td>{{ $recipient->error_message ?: '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div style="margin-top:16px;">{{ $campaignRecipients->links('vendor.pagination.dashboard') }}</div>
            @endif
        </div>
    </div>
    <script>
        (() => {
            const refreshButton = document.getElementById('campaign-refresh-button');
            const shouldPoll = @json($shouldPoll);

            if (refreshButton) {
                refreshButton.addEventListener('click', () => window.location.reload());
            }

            if (shouldPoll) {
                window.setInterval(() => window.location.reload(), 10000);
            }
        })();
    </script>
@endsection
