@extends('layouts.admin')

@php
    $pageTitle = 'Senders';
    $pageDescription = 'Manajemen akun SMTP untuk rotasi pengiriman dan kontrol limit.';
@endphp

@section('content')
    <div class="two-col">
        <div class="panel">
            <div class="panel-header"><div><h3>Tambah Sender Account</h3><p>Simpan kredensial SMTP dan limit pengiriman harian.</p></div></div>
            <form action="{{ route('senders.store') }}" method="post" class="form-grid">
                @csrf
                <div class="form-row">
                    <label>Label akun
                        <input name="name" placeholder="Gmail POSI 1" required>
                        <span class="field-hint">Nama bebas untuk membedakan akun, misalnya `Gmail POSI 1` atau `SMTP Alumni`.</span>
                    </label>
                    <label>SMTP host
                        <input name="host" placeholder="smtp.gmail.com" required>
                        <span class="field-hint">Alamat server SMTP. Contoh Gmail: `smtp.gmail.com`, Outlook: `smtp.office365.com`.</span>
                    </label>
                </div>
                <div class="form-row">
                    <label>SMTP port
                        <input type="number" name="port" value="587" required>
                        <span class="field-hint">Biasanya `587` untuk TLS atau `465` untuk SSL, tergantung provider email Anda.</span>
                    </label>
                    <label>Encryption
                        <select name="encryption"><option value="">None</option><option value="tls">TLS</option><option value="ssl">SSL</option></select>
                        <span class="field-hint">Untuk Gmail umumnya pakai `TLS` dengan port `587`.</span>
                    </label>
                </div>
                <div class="form-row">
                    <label>Username
                        <input name="username" placeholder="posi@gmail.com" required>
                        <span class="field-hint">Hampir selalu isi dengan alamat email penuh, bukan `admin`.</span>
                    </label>
                    <label>Password / app password
                        <input type="password" name="password" required>
                        <span class="field-hint">Untuk Gmail atau Google Workspace, gunakan `App Password`, bukan password login biasa.</span>
                    </label>
                </div>
                <div class="form-row">
                    <label>From address
                        <input type="email" name="from_address" placeholder="posi@gmail.com" required>
                        <span class="field-hint">Alamat email yang tampil ke penerima. Biasanya sama dengan akun SMTP.</span>
                    </label>
                    <label>From name
                        <input name="from_name" value="POSI" required>
                        <span class="field-hint">Nama pengirim yang terlihat di inbox, misalnya `POSI` atau `Tim POSI`.</span>
                    </label>
                </div>
                <div class="form-row">
                    <label>Reply-to
                        <input type="email" name="reply_to_address" placeholder="cs@posi.id">
                        <span class="field-hint">Opsional. Kalau diisi, balasan email akan masuk ke alamat ini.</span>
                    </label>
                    <label>Limit harian
                        <input type="number" name="daily_limit" value="150" min="1" required>
                        <span class="field-hint">Batas internal per hari. Untuk awal, lebih aman mulai dari `50` sampai `100`.</span>
                    </label>
                </div>
                <label>Limit per jam
                    <input type="number" name="hourly_limit" value="40" min="1" required>
                    <span class="field-hint">Batas internal per jam agar akun tidak dipakai terlalu agresif. Awal yang aman: `10` sampai `20`.</span>
                </label>
                <div class="mini-grid">
                    <div class="kpi-pill">Contoh Gmail: host `smtp.gmail.com`, port `587`, encryption `TLS`, username `email penuh`.</div>
                    <div class="kpi-pill">Kalau memakai Gmail, aktifkan 2-Step Verification lalu buat `App Password` dari akun Google.</div>
                </div>
                <button class="button" type="submit">Simpan Sender</button>
            </form>
        </div>
        <div class="panel">
            <div class="panel-header"><div><h3>Daftar Sender</h3><p>Pantau utilisasi dan kapasitas pengiriman tiap akun.</p></div></div>
            @if ($senderAccounts->isEmpty())
                <div class="empty">Belum ada sender account yang tersimpan.</div>
            @else
                <div class="bar-list">
                    @foreach ($senderAccounts as $sender)
                        @php $ratio = $sender->daily_limit > 0 ? min(100, (int) round(($sender->sent_today / $sender->daily_limit) * 100)) : 0; @endphp
                        <div class="panel" style="padding:16px; border-radius:18px; box-shadow:none;">
                            <div class="bar-meta"><strong>{{ $sender->name }}</strong><span class="status {{ $sender->is_active ? 'sent' : 'failed' }}">{{ $sender->is_active ? 'aktif' : 'nonaktif' }}</span></div>
                            <div class="contact-sub" style="margin:6px 0 10px;">{{ $sender->from_address }} • {{ $sender->host }}:{{ $sender->port }} • {{ $sender->encryption ?: 'none' }}</div>
                            <div class="bar-track"><div class="bar-fill" style="width: {{ $ratio }}%"></div></div>
                            <div class="bar-meta" style="margin-top:8px;"><span>{{ $sender->sent_today }}/{{ $sender->daily_limit }} per hari</span><span>{{ $sender->hourly_limit }} per jam</span></div>
                            <div class="topbar-actions" style="margin-top:12px;">
                                <a class="button-ghost" href="{{ route('senders.edit', $sender) }}">Edit</a>
                                <form action="{{ route('senders.toggle', $sender) }}" method="post">
                                    @csrf
                                    @method('PATCH')
                                    <button class="button-ghost" type="submit">{{ $sender->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form action="{{ route('senders.test', $sender) }}" method="post">
                                    @csrf
                                    <button class="button-ghost" type="submit">Test SMTP</button>
                                </form>
                                <form action="{{ route('senders.destroy', $sender) }}" method="post" onsubmit="return confirm('Hapus sender ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button-danger" type="submit">Hapus</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
