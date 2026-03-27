@extends('layouts.admin')

@php
    $pageTitle = 'Pengiriman Email';
    $pageDescription = 'Susun email, pilih target, dan masukkan ke antrean pengiriman secara bertahap.';
@endphp

@section('content')
    <div class="two-col">
        <div class="panel">
            <div class="panel-header"><div><h3>Buat Pengiriman Email</h3><p>Mulai dari batch kecil sebelum dikirim ke jumlah yang lebih besar.</p></div></div>
            <form action="{{ route('campaigns.store') }}" method="post" class="form-grid" id="campaign-create-form">
                @csrf
                <input type="hidden" name="form_nonce" value="{{ $formNonce }}">
                <div class="kpi-strip">
                    <div class="kpi-pill">Placeholder `@{{nama}}` sekarang akan otomatis diganti nama kontak.</div>
                    <div class="kpi-pill">Secara default, kontak yang menerima email dalam 24 jam terakhir tidak dipilih lagi.</div>
                </div>
                <label>Nama pengiriman<input name="name" placeholder="Promo POSI SMA Batch 1" required></label>
                <div class="form-row">
                    <label>Target file import
                        <select name="import_batch_id">
                            <option value="">Semua kontak emailable</option>
                            @foreach ($importBatchTargets as $batch)
                                <option value="{{ $batch->id }}">{{ $batch->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Subject<input name="subject" placeholder="Mau ikut Olimpiade POSI lagi?" required></label>
                </div>
                <label>Filter jenjang opsional
                    <select name="segment">
                        <option value="">Tanpa filter jenjang</option>
                        @foreach ($segments as $segment)
                            <option value="{{ $segment }}">{{ $segment }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Isi email<textarea name="body" required>Halo @{{nama}},

Pendaftaran Olimpiade POSI terbaru sudah dibuka. Kalau kamu tertarik ikut lagi, balas email ini atau masuk ke landing page pendaftaran yang kamu siapkan.

Salam,
Tim POSI</textarea></label>
                <label style="display:flex; gap:12px; align-items:flex-start; border:1px solid var(--line); border-radius:18px; padding:16px 18px;">
                    <input type="checkbox" name="ignore_cooldown" value="1" style="width:auto; margin-top:4px;">
                    <span>
                        <strong>Kirim ulang meski masih dalam cooldown</strong><br>
                        Gunakan ini kalau Anda memang ingin memilih ulang kontak yang sudah menerima email dalam 24 jam terakhir.
                    </span>
                </label>
                <div class="form-row"><label>Jumlah kirim awal<input type="number" name="batch_size" value="50" min="1" max="500" required></label><label>Jeda antar email (detik)<input type="number" name="delay_seconds" value="10" min="0" max="600" required></label></div>
                <button class="button" type="submit" id="campaign-submit-button">Masukkan ke Antrean</button>
            </form>
        </div>
        <div class="panel">
            <div class="panel-header"><div><h3>Riwayat Pengiriman</h3><p>Melihat antrean dan hasil kirim email terbaru.</p></div></div>
            @if ($campaigns->isEmpty())
                <div class="empty">Belum ada pengiriman yang dibuat.</div>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th title="Terkirim" style="text-align:center;">✓</th>
                        <th title="Antre" style="text-align:center;">◷</th>
                        <th title="Gagal" style="text-align:center;">!</th>
                        <th>Detail</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($campaigns as $campaign)
                        <tr>
                            <td>{{ $campaign->name }}</td>
                            <td>{{ $campaign->importBatch?->title ?: ($campaign->segment ?: 'Semua') }}</td>
                            <td><span class="status {{ $campaign->status }}">{{ $campaign->status }}</span></td>
                            <td style="text-align:center;">{{ number_format($campaign->sent_count) }}</td>
                            <td style="text-align:center;">{{ number_format($campaign->queued_count) }}</td>
                            <td style="text-align:center;">{{ number_format($campaign->failed_count) }}</td>
                            <td><a class="button-ghost" href="{{ route('admin.campaigns.show', $campaign) }}">Lihat Detail</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div style="margin-top:16px;">{{ $campaigns->links('vendor.pagination.dashboard') }}</div>
            @endif
        </div>
    </div>
    <script>
        (() => {
            const form = document.getElementById('campaign-create-form');
            const submitButton = document.getElementById('campaign-submit-button');

            if (!form || !submitButton) {
                return;
            }

            form.addEventListener('submit', () => {
                submitButton.disabled = true;
                submitButton.textContent = 'Memproses...';
                submitButton.style.opacity = '0.7';
            });
        })();
    </script>
@endsection
