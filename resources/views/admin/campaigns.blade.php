@extends('layouts.admin')

@php
    $pageTitle = 'Pengiriman Email';
    $pageDescription = 'Susun email, pilih target, dan masukkan ke antrean pengiriman secara bertahap.';
@endphp

@section('content')
    @php $availableQuotaNow = min($senderQuotaPool['daily_remaining'], $senderQuotaPool['hourly_remaining']); @endphp
    <div class="header-badges">
        <div class="quota-badge pool">Pool aktif <strong>{{ $senderQuotaPool['active_senders'] }}</strong> akun</div>
        <div class="quota-badge daily">Sisa harian <strong>{{ number_format($senderQuotaPool['daily_remaining']) }}/{{ number_format($senderQuotaPool['daily_total_limit']) }}</strong></div>
        <div class="quota-badge hourly">Sisa per jam <strong>{{ number_format($senderQuotaPool['hourly_remaining']) }}/{{ number_format($senderQuotaPool['hourly_total_limit']) }}</strong></div>
        <div class="quota-badge capacity">Kapasitas kirim langsung <strong>{{ number_format($availableQuotaNow) }}</strong> email</div>
    </div>
    <div class="two-col">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>Buat Pengiriman Email</h3>
                    <p>Seluruh kontak pada target yang dipilih akan otomatis diantrekan dan dilanjutkan sampai selesai mengikuti kuota sender.</p>
                </div>
                <button type="button" class="button-secondary" id="campaign-info-button">Info Target & Aturan</button>
            </div>
            <form action="{{ route('campaigns.store') }}" method="post" class="form-grid" id="campaign-create-form">
                @csrf
                <input type="hidden" name="form_nonce" value="{{ $formNonce }}">
                <label>Nama pengiriman<input name="name" placeholder="Promo POSI SMA Batch 1" required></label>
                <div class="form-row">
                    <label>Target file import
                        <select name="import_batch_id" id="campaign-import-batch-id">
                            <option value="">Semua kontak emailable</option>
                            @foreach ($importBatchTargets as $batch)
                                <option value="{{ $batch->id }}">{{ $batch->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Subject<input name="subject" placeholder="Mau ikut Olimpiade POSI lagi?" required></label>
                </div>
                <label>Filter jenjang opsional
                    <select name="segment" id="campaign-segment">
                        <option value="">Tanpa filter jenjang</option>
                        @foreach ($segments as $segment)
                            <option value="{{ $segment }}">{{ $segment }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Isi email<textarea name="body" required>Halo @{{nama}},

Sekolah: @{{sekolah}}
Bidang: @{{bidang}}
No Peserta: @{{peserta}}
Link Kartu Peserta: @{{link}}

Pendaftaran Olimpiade POSI terbaru sudah dibuka. Kalau kamu tertarik ikut lagi, balas email ini atau masuk ke landing page pendaftaran yang kamu siapkan.

Salam,
Tim POSI</textarea></label>
                <label style="display:flex; gap:12px; align-items:flex-start; border:1px solid var(--line); border-radius:18px; padding:16px 18px;">
                    <input type="checkbox" name="ignore_cooldown" value="1" id="campaign-ignore-cooldown" style="width:auto; margin-top:4px;">
                    <span>
                        <strong>Kirim ulang meski masih dalam cooldown</strong><br>
                        Gunakan ini kalau Anda memang ingin memilih ulang kontak yang sudah menerima email dalam 24 jam terakhir.
                    </span>
                </label>
                <div class="kpi-strip">
                    <div class="kpi-pill">Semua kontak emailable pada target terpilih akan masuk antrean otomatis.</div>
                    <div class="kpi-pill">Kapasitas kirim langsung saat ini {{ number_format($availableQuotaNow) }} email, sisanya akan menunggu kuota sender berikutnya.</div>
                </div>
                <label>Jeda antar email (detik)
                    <input type="number" name="delay_seconds" value="10" min="0" max="600" required>
                    <span class="field-hint">Jeda dasar antar email saat campaign mulai diantrekan. Kalau kuota sender penuh, sistem akan menunggu slot berikutnya dan lanjut otomatis.</span>
                </label>
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
    <div class="modal-backdrop" id="campaign-info-modal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="campaign-info-title">
            <div class="modal-head">
                <div>
                    <h3 id="campaign-info-title">Info Target & Aturan Pengiriman</h3>
                    <p>Ringkasan placeholder, cooldown, dan preview target realtime untuk campaign yang sedang Anda susun.</p>
                </div>
                <button type="button" class="modal-close" id="campaign-info-close" aria-label="Tutup modal">×</button>
            </div>
            <div class="modal-body">
                <div class="kpi-strip">
                    <div class="kpi-pill">Placeholder `@{{nama}}`, `@{{sekolah}}`, `@{{bidang}}`, `@{{peserta}}`, dan `@{{link}}` akan otomatis diganti dari data kontak.</div>
                    <div class="kpi-pill">Secara default, kontak yang menerima email dalam 24 jam terakhir tidak dipilih lagi.</div>
                </div>
                <div class="mini-grid" id="campaign-target-preview">
                    <div class="kpi-pill">Total kontak target: <strong data-target-total>-</strong></div>
                    <div class="kpi-pill">Punya email: <strong data-target-with-email>-</strong></div>
                    <div class="kpi-pill">Opt-out email: <strong data-target-opted-out>-</strong></div>
                    <div class="kpi-pill">Invalid / blocked: <strong data-target-invalid>-</strong></div>
                    <div class="kpi-pill">Terkena cooldown 24 jam: <strong data-target-cooldown>-</strong></div>
                    <div class="kpi-pill">Siap dikirim sekarang: <strong data-target-emailable>-</strong></div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (() => {
            const form = document.getElementById('campaign-create-form');
            const submitButton = document.getElementById('campaign-submit-button');
            const importBatchSelect = document.getElementById('campaign-import-batch-id');
            const segmentSelect = document.getElementById('campaign-segment');
            const ignoreCooldownInput = document.getElementById('campaign-ignore-cooldown');
            const infoButton = document.getElementById('campaign-info-button');
            const infoModal = document.getElementById('campaign-info-modal');
            const infoCloseButton = document.getElementById('campaign-info-close');
            const previewRoot = document.getElementById('campaign-target-preview');
            const previewFields = {
                total: previewRoot?.querySelector('[data-target-total]'),
                withEmail: previewRoot?.querySelector('[data-target-with-email]'),
                optedOut: previewRoot?.querySelector('[data-target-opted-out]'),
                invalid: previewRoot?.querySelector('[data-target-invalid]'),
                cooldown: previewRoot?.querySelector('[data-target-cooldown]'),
                emailable: previewRoot?.querySelector('[data-target-emailable]'),
            };

            if (!form || !submitButton) {
                return;
            }

            const openInfoModal = () => {
                if (!infoModal) {
                    return;
                }

                infoModal.classList.add('active');
                infoModal.setAttribute('aria-hidden', 'false');
            };

            const closeInfoModal = () => {
                if (!infoModal) {
                    return;
                }

                infoModal.classList.remove('active');
                infoModal.setAttribute('aria-hidden', 'true');
            };

            const updatePreviewValue = (element, value) => {
                if (element) {
                    element.textContent = new Intl.NumberFormat('id-ID').format(value ?? 0);
                }
            };

            const refreshTargetPreview = async () => {
                if (!previewRoot) {
                    return;
                }

                const params = new URLSearchParams();

                if (importBatchSelect?.value) {
                    params.set('import_batch_id', importBatchSelect.value);
                }

                if (segmentSelect?.value) {
                    params.set('segment', segmentSelect.value);
                }

                if (ignoreCooldownInput?.checked) {
                    params.set('ignore_cooldown', '1');
                }

                try {
                    const response = await fetch(`{{ route('campaigns.target-stats') }}?${params.toString()}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const stats = await response.json();
                    updatePreviewValue(previewFields.total, stats.total_target_contacts);
                    updatePreviewValue(previewFields.withEmail, stats.with_email);
                    updatePreviewValue(previewFields.optedOut, stats.opted_out);
                    updatePreviewValue(previewFields.invalid, stats.invalid_or_blocked);
                    updatePreviewValue(previewFields.cooldown, stats.cooldown_blocked);
                    updatePreviewValue(previewFields.emailable, stats.emailable_now);
                } catch (error) {
                    console.error('Gagal memuat preview target campaign.', error);
                }
            };

            importBatchSelect?.addEventListener('change', refreshTargetPreview);
            segmentSelect?.addEventListener('change', refreshTargetPreview);
            ignoreCooldownInput?.addEventListener('change', refreshTargetPreview);
            infoButton?.addEventListener('click', openInfoModal);
            infoCloseButton?.addEventListener('click', closeInfoModal);
            infoModal?.addEventListener('click', (event) => {
                if (event.target === infoModal) {
                    closeInfoModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeInfoModal();
                }
            });
            refreshTargetPreview();

            form.addEventListener('submit', () => {
                submitButton.disabled = true;
                submitButton.textContent = 'Memproses...';
                submitButton.style.opacity = '0.7';
            });
        })();
    </script>
@endsection
