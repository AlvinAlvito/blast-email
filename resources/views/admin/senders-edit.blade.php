@extends('layouts.admin')

@php
    $pageTitle = 'Edit Sender';
    $pageDescription = 'Perbarui konfigurasi SMTP, limit, dan identitas pengirim.';
@endphp

@section('content')
    <div class="two-col">
        <div class="panel">
            <div class="panel-header"><div><h3>Edit Sender Account</h3><p>Ubah konfigurasi akun email yang dipakai untuk blast.</p></div><a class="button-secondary" href="{{ route('admin.senders') }}">Kembali</a></div>
            <form action="{{ route('senders.update', $sender) }}" method="post" class="form-grid">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <label>Label akun<input name="name" value="{{ old('name', $sender->name) }}" required><span class="field-hint">Nama internal agar akun mudah dikenali.</span></label>
                    <label>SMTP host<input name="host" value="{{ old('host', $sender->host) }}" required><span class="field-hint">Contoh Gmail: `smtp.gmail.com`.</span></label>
                </div>
                <div class="form-row">
                    <label>SMTP port<input type="number" name="port" value="{{ old('port', $sender->port) }}" required><span class="field-hint">Umumnya `587` untuk TLS atau `465` untuk SSL.</span></label>
                    <label>Encryption<select name="encryption"><option value="" @selected(old('encryption', $sender->encryption) === '')>None</option><option value="tls" @selected(old('encryption', $sender->encryption) === 'tls')>TLS</option><option value="ssl" @selected(old('encryption', $sender->encryption) === 'ssl')>SSL</option></select><span class="field-hint">Samakan dengan setting provider email Anda.</span></label>
                </div>
                <div class="form-row">
                    <label>Username<input name="username" value="{{ old('username', $sender->username) }}" required><span class="field-hint">Biasanya alamat email penuh.</span></label>
                    <label>Password baru (opsional)<input type="password" name="password"><span class="field-hint">Kosongkan jika tidak ingin mengubah password tersimpan.</span></label>
                </div>
                <div class="form-row">
                    <label>From address<input type="email" name="from_address" value="{{ old('from_address', $sender->from_address) }}" required><span class="field-hint">Alamat yang akan dilihat penerima.</span></label>
                    <label>From name<input name="from_name" value="{{ old('from_name', $sender->from_name) }}" required><span class="field-hint">Nama pengirim di inbox, misalnya `POSI`.</span></label>
                </div>
                <div class="form-row">
                    <label>Reply-to<input type="email" name="reply_to_address" value="{{ old('reply_to_address', $sender->reply_to_address) }}"><span class="field-hint">Opsional untuk mengarahkan balasan ke alamat lain.</span></label>
                    <label>Limit harian<input type="number" name="daily_limit" value="{{ old('daily_limit', $sender->daily_limit) }}" min="1" required><span class="field-hint">Batas internal pengiriman harian.</span></label>
                </div>
                <label>Limit per jam<input type="number" name="hourly_limit" value="{{ old('hourly_limit', $sender->hourly_limit) }}" min="1" required><span class="field-hint">Batas internal per jam agar akun tidak terlalu agresif.</span></label>
                <button class="button" type="submit">Simpan Perubahan</button>
            </form>
        </div>
        <div class="panel">
            <div class="panel-header"><div><h3>Info Sender</h3><p>Status operasional akun saat ini.</p></div></div>
            <div class="mini-grid">
                <div class="kpi-pill">Status: {{ $sender->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                <div class="kpi-pill">Dikirim hari ini: {{ $sender->sent_today }}/{{ $sender->daily_limit }}</div>
                <div class="kpi-pill">Batas per jam: {{ $sender->hourly_limit }}</div>
                <div class="kpi-pill">Last sent: {{ $sender->last_sent_at?->format('d M Y H:i') ?: '-' }}</div>
            </div>
        </div>
    </div>
@endsection
