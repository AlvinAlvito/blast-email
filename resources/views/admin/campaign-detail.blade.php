@extends('layouts.admin')

@php
    $pageTitle = $campaignDetail->name;
    $pageDescription = 'Detail isi email, statistik pengiriman, dan daftar penerima untuk pengiriman ini.';
    $shouldPoll = in_array($campaignDetail->status, ['queued', 'paused'], true);
    $availableQuotaNow = min($senderQuotaPool['daily_remaining'], $senderQuotaPool['hourly_remaining']);
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
                        <button class="button-secondary" type="button" id="campaign-rules-button">Aturan Pengiriman</button>
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
                    <div class="kpi-pill"><strong>Placeholder didukung:</strong> `@{{nama}}`, `@{{sekolah}}`, `@{{bidang}}`, `@{{peserta}}`, `@{{link}}`</div>
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
                    <div class="kpi-pill">Total target: {{ number_format($campaignDetail->batch_size) }}</div>
                    <div class="kpi-pill">Delay: {{ $campaignDetail->delay_seconds }} detik</div>
                    <div class="kpi-pill">Started: {{ $campaignDetail->started_at?->format('d M Y H:i:s') ?: '-' }}</div>
                    <div class="kpi-pill">Finished: {{ $campaignDetail->finished_at?->format('d M Y H:i:s') ?: '-' }}</div>
                    <div class="kpi-pill">Created: {{ $campaignDetail->created_at?->format('d M Y H:i:s') ?: '-' }}</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div><h3>Daftar Penerima</h3><p>Menampilkan kontak, status kirim, sender yang digunakan, dan error jika gagal.</p></div>
                <a class="button-secondary" href="{{ route('admin.campaigns.export-recipients', $campaignDetail) }}">Export Excel</a>
            </div>
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

    <div class="modal-backdrop" id="campaign-rules-modal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="campaign-rules-title">
            <div class="modal-head">
                <div>
                    <h3 id="campaign-rules-title">Aturan Pengiriman</h3>
                    <p>Ringkasan rule operasional yang dipakai campaign ini secara realtime.</p>
                </div>
                <button class="modal-close" type="button" id="campaign-rules-close" aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div class="kpi-pill"><strong>Email kosong / tidak valid:</strong> recipient langsung ditandai gagal dan tidak dikirim.</div>
                <div class="kpi-pill"><strong>Kontak berstatus `invalid_email` atau `blocked`:</strong> otomatis dilewati dan ditandai gagal.</div>
                <div class="kpi-pill"><strong>Sender penuh kuota:</strong> recipient tidak gagal permanen, tetap `queued` dan dijadwalkan ulang saat slot sender tersedia.</div>
                <div class="kpi-pill"><strong>Tidak ada sender aktif:</strong> recipient gagal karena sistem memang tidak punya akun aktif untuk mengirim.</div>
                <div class="kpi-pill"><strong>Campaign dijeda:</strong> job akan menunggu dan mencoba lagi sampai campaign dilanjutkan.</div>
                <div class="kpi-pill"><strong>Campaign dihentikan:</strong> semua recipient yang masih antre akan dibatalkan (`cancelled`).</div>
                <div class="kpi-pill"><strong>Cooldown kontak:</strong> secara default kontak yang sudah menerima email dalam 24 jam terakhir tidak ikut dipilih lagi saat campaign dibuat.</div>
                <div class="kpi-pill"><strong>Reset kuota:</strong> limit harian reset saat hari berganti, limit per jam reset saat jam berganti.</div>
                <div class="kpi-pill"><strong>Pool sender aktif:</strong> {{ $senderQuotaPool['active_senders'] }} akun dengan kapasitas langsung saat ini {{ number_format($availableQuotaNow) }} email.</div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const refreshButton = document.getElementById('campaign-refresh-button');
            const rulesButton = document.getElementById('campaign-rules-button');
            const rulesModal = document.getElementById('campaign-rules-modal');
            const rulesClose = document.getElementById('campaign-rules-close');
            const shouldPoll = @json($shouldPoll);

            if (refreshButton) {
                refreshButton.addEventListener('click', () => window.location.reload());
            }

            const closeRulesModal = () => {
                if (!rulesModal) {
                    return;
                }

                rulesModal.classList.remove('active');
                rulesModal.setAttribute('aria-hidden', 'true');
            };

            if (rulesButton && rulesModal) {
                rulesButton.addEventListener('click', () => {
                    rulesModal.classList.add('active');
                    rulesModal.setAttribute('aria-hidden', 'false');
                });
            }

            if (rulesClose) {
                rulesClose.addEventListener('click', closeRulesModal);
            }

            if (rulesModal) {
                rulesModal.addEventListener('click', (event) => {
                    if (event.target === rulesModal) {
                        closeRulesModal();
                    }
                });
            }

            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeRulesModal();
                }
            });

            if (shouldPoll) {
                window.setInterval(() => window.location.reload(), 10000);
            }
        })();
    </script>
@endsection
