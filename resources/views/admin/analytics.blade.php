@extends('layouts.admin')

@php
    $pageTitle = 'Analytics';
    $pageDescription = 'Panel analitik untuk komposisi data, wilayah, dan performa pengiriman.';
    $maxLevel = max(1, (int) ($contactsByLevel->max('total') ?? 1));
    $maxProvince = max(1, (int) ($contactsByProvince->max('total') ?? 1));
    $donutPalette = ['#175cd3', '#53b1fd', '#12b76a', '#f79009', '#f04438', '#7a5af8', '#06aed4', '#0ba5ec'];

    $buildDonut = function ($items, $labelKey, $valueKey = 'total') use ($donutPalette) {
        $total = max(1, (int) $items->sum($valueKey));
        $current = 0;
        $segments = [];
        $legend = [];

        foreach ($items->values() as $index => $item) {
            $value = (int) data_get($item, $valueKey, 0);
            if ($value <= 0) {
                continue;
            }

            $start = round(($current / $total) * 360, 2);
            $current += $value;
            $end = round(($current / $total) * 360, 2);
            $color = $donutPalette[$index % count($donutPalette)];

            $segments[] = "{$color} {$start}deg {$end}deg";
            $legend[] = [
                'label' => data_get($item, $labelKey) ?: 'Lainnya',
                'value' => $value,
                'color' => $color,
            ];
        }

        return [
            'background' => $segments !== [] ? 'conic-gradient('.implode(', ', $segments).')' : 'conic-gradient(#dbe3ef 0deg 360deg)',
            'legend' => $legend,
            'total' => $total,
        ];
    };

    $provinceDonut = $buildDonut($contactsByProvince, 'province');
    $levelDonut = $buildDonut($contactsByLevel, 'education_level');
@endphp

@section('content')
    <div class="analytics-grid">
        <div class="panel">
            <div class="panel-header"><div><h3>Donat Provinsi</h3><p>Komposisi kontak berdasarkan provinsi utama.</p></div></div>
            @if ($contactsByProvince->isEmpty())
                <div class="empty">Chart akan muncul setelah ada data provinsi.</div>
            @else
                <div class="donut-grid">
                    <div class="donut-chart" style="background: {{ $provinceDonut['background'] }};">
                        <div class="donut-center">{{ number_format($provinceDonut['total']) }}<span>Total Kontak</span></div>
                    </div>
                    <div class="donut-legend">
                        @foreach ($provinceDonut['legend'] as $item)
                            <div class="legend-item">
                                <span class="legend-dot" style="background: {{ $item['color'] }};"></span>
                                <span class="legend-label">{{ $item['label'] }}</span>
                                <span class="legend-value">{{ number_format($item['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <div class="panel">
            <div class="panel-header"><div><h3>Donat Jenjang</h3><p>Komposisi kontak berdasarkan jenjang pendidikan.</p></div></div>
            @if ($contactsByLevel->isEmpty())
                <div class="empty">Belum ada data jenjang.</div>
            @else
                <div class="donut-grid">
                    <div class="donut-chart" style="background: {{ $levelDonut['background'] }};">
                        <div class="donut-center">{{ number_format($levelDonut['total']) }}<span>Total Kontak</span></div>
                    </div>
                    <div class="donut-legend">
                        @foreach ($levelDonut['legend'] as $item)
                            <div class="legend-item">
                                <span class="legend-dot" style="background: {{ $item['color'] }};"></span>
                                <span class="legend-label">{{ $item['label'] ?: 'Tidak diketahui' }}</span>
                                <span class="legend-value">{{ number_format($item['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
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
            <div class="panel-header"><div><h3>Kesehatan Email</h3><p>Status kontak berdasarkan kualitas email yang tersimpan.</p></div></div>
            <div class="mini-grid">
                <div class="kpi-pill">Aktif: {{ number_format($contactHealth['active'] ?? 0) }}</div>
                <div class="kpi-pill">Email invalid: {{ number_format($contactHealth['invalid_email'] ?? 0) }}</div>
                <div class="kpi-pill">Diblokir server: {{ number_format($contactHealth['blocked'] ?? 0) }}</div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><div><h3>Operational Notes</h3><p>Panduan cepat untuk menjaga kualitas pengiriman.</p></div></div>
            <div class="mini-grid"><div class="kpi-pill">Mulai dari segment paling hangat dan batch kecil.</div><div class="kpi-pill">Pastikan sender tidak melebihi limit.</div><div class="kpi-pill">Amati bounce dan failure sebelum scale up.</div><div class="kpi-pill">Pisahkan campaign per jenjang agar copy lebih relevan.</div></div>
        </div>
    </div>
@endsection
