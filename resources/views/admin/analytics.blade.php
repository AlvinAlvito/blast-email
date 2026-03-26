@extends('layouts.admin')

@php
    $pageTitle = 'Analytics';
    $pageDescription = 'Panel analitik untuk komposisi data, wilayah, dan performa pengiriman.';
    $maxLevel = max(1, (int) ($contactsByLevel->max('total') ?? 1));
    $maxProvince = max(1, (int) ($contactsByProvince->max('total') ?? 1));
@endphp

@section('content')
    <div class="analytics-grid">
        <div class="panel">
            <div class="panel-header"><div><h3>Audience by Education Level</h3><p>Komposisi kontak berdasarkan jenjang.</p></div></div>
            @if ($contactsByLevel->isEmpty())
                <div class="empty">Belum ada data jenjang.</div>
            @else
                <div class="chart-shell">
                    @foreach ($contactsByLevel as $row)
                        <div class="chart-col"><div class="chart-value">{{ number_format($row->total) }}</div><div class="chart-bar" style="height: {{ max(16, (int) round(($row->total / $maxLevel) * 180)) }}px;"></div><div class="chart-label">{{ $row->education_level ?: 'Unknown' }}</div></div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="panel">
            <div class="panel-header"><div><h3>Top Provinces</h3><p>Provinsi dengan kontak paling banyak.</p></div></div>
            @if ($contactsByProvince->isEmpty())
                <div class="empty">Belum ada data provinsi.</div>
            @else
                <div class="bar-list">
                    @foreach ($contactsByProvince as $row)
                        <div class="bar-item"><div class="bar-meta"><strong>{{ $row->province }}</strong><span>{{ number_format($row->total) }}</span></div><div class="bar-track"><div class="bar-fill" style="width: {{ max(8, (int) round(($row->total / $maxProvince) * 100)) }}%"></div></div></div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="panel">
            <div class="panel-header"><div><h3>Delivery Status</h3><p>Distribusi recipient berdasarkan status queue.</p></div></div>
            <div class="mini-grid"><div class="kpi-pill">Queued: {{ number_format($campaignStatus['queued'] ?? 0) }}</div><div class="kpi-pill">Sent: {{ number_format($campaignStatus['sent'] ?? 0) }}</div><div class="kpi-pill">Failed: {{ number_format($campaignStatus['failed'] ?? 0) }}</div><div class="kpi-pill">Pending: {{ number_format($campaignStatus['pending'] ?? 0) }}</div></div>
        </div>
        <div class="panel">
            <div class="panel-header"><div><h3>Operational Notes</h3><p>Panduan cepat untuk menjaga kualitas pengiriman.</p></div></div>
            <div class="mini-grid"><div class="kpi-pill">Mulai dari segment paling hangat dan batch kecil.</div><div class="kpi-pill">Pastikan sender tidak melebihi limit.</div><div class="kpi-pill">Amati bounce dan failure sebelum scale up.</div><div class="kpi-pill">Pisahkan campaign per jenjang agar copy lebih relevan.</div></div>
        </div>
    </div>
@endsection
