<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($pageTitle ?? 'Admin').' | POSI Blast Console' }}</title>
    <style>
        :root { --bg:#f3f5f8; --surface:#fff; --surface-2:#eef3fb; --ink:#122033; --muted:#64748b; --line:#dbe3ef; --brand:#175cd3; --accent:#12b76a; --warn:#f79009; --danger:#f04438; --shadow:0 18px 38px rgba(15,23,42,.08); --sidebar:#0f172a; --sidebar-muted:#94a3b8; }
        * { box-sizing:border-box; } body { margin:0; font-family:"Segoe UI", Inter, Arial, sans-serif; background:radial-gradient(circle at top left, rgba(23,92,211,.09), transparent 24%), linear-gradient(180deg, #f9fbfd 0%, var(--bg) 100%); color:var(--ink); }
        a { color:inherit; text-decoration:none; } .app-shell { display:grid; grid-template-columns:280px 1fr; min-height:100vh; }
        .sidebar { background:linear-gradient(180deg, rgba(23,92,211,.22), transparent 30%), var(--sidebar); color:#fff; padding:28px 20px; display:grid; grid-template-rows:auto auto 1fr auto; gap:24px; position:sticky; top:0; height:100vh; }
        .brand { display:grid; gap:8px; padding:4px 6px 18px; border-bottom:1px solid rgba(148,163,184,.15); }
        .brand-mark { width:44px; height:44px; border-radius:14px; display:grid; place-items:center; background:linear-gradient(135deg, #2e90fa, #175cd3); box-shadow:0 16px 30px rgba(23,92,211,.32); font-weight:800; }
        .brand h1,.headline h2,.panel-header h3 { margin:0; } .brand p,.headline p { margin:0; color:var(--sidebar-muted); font-size:13px; line-height:1.6; }
        .sidebar-section,.stack,.mini-grid,.bar-list,.bar-item,.form-grid,.headline { display:grid; gap:10px; }
        .sidebar-label { padding:0 8px; color:var(--sidebar-muted); font-size:11px; letter-spacing:.18em; text-transform:uppercase; }
        .nav-link { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border-radius:16px; color:#d8e2f0; transition:.2s ease; }
        .nav-link:hover { background:rgba(148,163,184,.12); color:#fff; } .nav-link.active { background:linear-gradient(135deg, rgba(46,144,250,.24), rgba(23,92,211,.18)); color:#fff; box-shadow:inset 0 0 0 1px rgba(96,165,250,.18); }
        .nav-meta { min-width:26px; height:26px; padding:0 8px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-size:11px; background:rgba(148,163,184,.16); color:#d9e3f1; }
        .sidebar-foot { padding:16px; border-radius:18px; background:rgba(15,23,42,.44); border:1px solid rgba(148,163,184,.12); color:var(--sidebar-muted); font-size:13px; line-height:1.6; }
        .main { padding:28px; } .topbar,.panel-header,.card-top,.bar-meta { display:flex; align-items:center; justify-content:space-between; gap:12px; } .topbar { margin-bottom:24px; } .headline h2 { font-size:clamp(26px, 4vw, 40px); } .headline p { color:var(--muted); }
        .topbar-actions,.summary,.kpi-strip { display:flex; gap:12px; flex-wrap:wrap; }
        .user-badge { display:inline-flex; align-items:center; gap:10px; padding:8px 14px; border-radius:14px; border:1px solid var(--line); background:#fff; color:var(--muted); font-size:13px; font-weight:700; }
        .user-dot { width:10px; height:10px; border-radius:999px; background:linear-gradient(135deg, #12b76a, #53b1fd); box-shadow:0 0 0 5px rgba(18,183,106,.12); }
        .button,.button-secondary,.button-danger,.button-ghost { display:inline-flex; align-items:center; justify-content:center; gap:10px; min-height:46px; padding:0 16px; border-radius:14px; font-weight:700; border:1px solid transparent; }
        .button { background:linear-gradient(135deg, var(--brand), #2e90fa); color:#fff; box-shadow:0 16px 28px rgba(23,92,211,.16); } .button-secondary { background:var(--surface); color:var(--ink); border-color:var(--line); }
        .button-danger { background:rgba(240,68,56,.1); color:var(--danger); border-color:rgba(240,68,56,.18); }
        .button-ghost { background:#f8fbff; color:var(--ink); border-color:var(--line); min-height:38px; padding:0 12px; font-size:13px; }
        .flash { margin-bottom:20px; padding:16px 18px; border-radius:18px; background:rgba(18,183,106,.08); border:1px solid rgba(18,183,106,.18); }
        .metrics { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:16px; margin-bottom:22px; } .content-grid { display:grid; grid-template-columns:1.4fr .9fr; gap:18px; } .two-col,.analytics-grid,.form-row { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:18px; }
        .card,.panel { background:var(--surface); border:1px solid var(--line); border-radius:22px; box-shadow:var(--shadow); } .card { padding:18px; display:grid; gap:14px; } .panel { padding:22px; }
        .card-label,th,label,.contact-sub,.chart-label { color:var(--muted); } .card-value { font-size:34px; font-weight:800; letter-spacing:-.03em; } .card-note { min-width:38px; height:38px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background:var(--surface-2); color:var(--brand); font-size:12px; font-weight:800; }
        label { display:grid; gap:8px; font-size:13px; } input,select,textarea { width:100%; border:1px solid var(--line); border-radius:14px; padding:13px 14px; font:inherit; color:var(--ink); background:#fff; } textarea { min-height:170px; resize:vertical; }
        .field-hint { font-size:12px; line-height:1.5; color:var(--muted); margin-top:-2px; }
        table { width:100%; border-collapse:collapse; } th,td { padding:12px 0; border-bottom:1px solid #edf1f7; text-align:left; font-size:14px; vertical-align:top; } th { font-weight:600; }
        .status { display:inline-flex; align-items:center; justify-content:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; background:rgba(23,92,211,.1); color:var(--brand); }
        .status.sent { background:rgba(18,183,106,.1); color:var(--accent); } .status.failed { background:rgba(240,68,56,.1); color:var(--danger); } .status.queued { background:rgba(247,144,9,.14); color:var(--warn); }
        .bar-track { height:10px; background:#edf3fb; border-radius:999px; overflow:hidden; } .bar-fill { height:100%; border-radius:inherit; background:linear-gradient(90deg, var(--brand), #53b1fd); }
        .empty,.kpi-pill { padding:14px 16px; border-radius:16px; background:linear-gradient(180deg, #fafcff, #f7faff); border:1px solid var(--line); color:var(--muted); }
        .contacts-grid { display:grid; gap:12px; } .contact-row { padding:16px; border:1px solid var(--line); border-radius:18px; display:grid; grid-template-columns:1.1fr 1fr .8fr .8fr; gap:14px; background:linear-gradient(180deg, #fff, #fbfdff); } .contact-name { font-weight:700; }
        .pagination-shell { display:grid; gap:12px; margin-top:16px; }
        .pagination-meta { color:var(--muted); font-size:13px; }
        .pagination-links { display:flex; flex-wrap:wrap; gap:8px; }
        .pagination-link { display:inline-flex; align-items:center; justify-content:center; min-width:42px; min-height:38px; padding:0 14px; border-radius:12px; border:1px solid var(--line); background:#fff; color:var(--ink); font-size:13px; font-weight:700; }
        .pagination-link.active { background:linear-gradient(135deg, var(--brand), #2e90fa); border-color:transparent; color:#fff; }
        .pagination-link.disabled { color:var(--sidebar-muted); background:#f8fafc; }
        .chart-shell { height:230px; display:grid; align-items:end; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:12px; padding-top:16px; } .chart-col { display:grid; gap:10px; justify-items:center; } .chart-bar { width:100%; border-radius:16px 16px 8px 8px; background:linear-gradient(180deg, #53b1fd, var(--brand)); min-height:10px; box-shadow:inset 0 -10px 22px rgba(15,23,42,.14); } .chart-value { font-size:12px; font-weight:700; }
        .donut-grid { display:grid; grid-template-columns:220px 1fr; gap:22px; align-items:center; }
        .donut-chart { width:220px; height:220px; border-radius:50%; position:relative; background:conic-gradient(var(--brand) 0deg 360deg); box-shadow:inset 0 0 0 1px rgba(15,23,42,.04); }
        .donut-chart::after { content:""; position:absolute; inset:34px; border-radius:50%; background:var(--surface); box-shadow:inset 0 0 0 1px var(--line); }
        .donut-center { position:absolute; inset:0; display:grid; place-items:center; text-align:center; z-index:1; padding:0 48px; font-weight:700; color:var(--ink); }
        .donut-center span { display:block; font-size:12px; color:var(--muted); font-weight:600; }
        .donut-legend { display:grid; gap:12px; }
        .legend-item { display:grid; grid-template-columns:14px 1fr auto; gap:10px; align-items:center; padding:10px 12px; border:1px solid var(--line); border-radius:14px; background:linear-gradient(180deg, #fff, #fbfdff); }
        .legend-dot { width:14px; height:14px; border-radius:999px; }
        .legend-label { font-weight:600; }
        .legend-value { color:var(--muted); font-size:13px; font-weight:700; }
        @media (max-width:1200px) { .metrics { grid-template-columns:repeat(2, minmax(0, 1fr)); } .content-grid,.analytics-grid,.two-col,.form-row { grid-template-columns:1fr; } }
        @media (max-width:940px) { .app-shell { grid-template-columns:1fr; } .sidebar { position:static; height:auto; grid-template-rows:none; } .contact-row,.donut-grid { grid-template-columns:1fr; } .donut-chart { margin:0 auto; } }
    </style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><div class="brand-mark">P</div><div><h1>POSI Blast Console</h1><p>Dashboard admin untuk import kontak, pengaturan email pengirim, pengiriman email, dan analitik operasional.</p></div></div>
        <div class="sidebar-section">
            <div class="sidebar-label">Navigation</div>
            <a href="{{ route('admin.overview') }}" class="nav-link {{ request()->routeIs('admin.overview') ? 'active' : '' }}"><span>Overview</span><span class="nav-meta">{{ number_format($stats['contacts']) }}</span></a>
            <a href="{{ route('admin.contacts') }}" class="nav-link {{ request()->routeIs('admin.contacts') ? 'active' : '' }}"><span>Contacts</span><span class="nav-meta">{{ number_format($stats['emailable_contacts']) }}</span></a>
            <a href="{{ route('admin.senders') }}" class="nav-link {{ request()->routeIs('admin.senders') ? 'active' : '' }}"><span>Senders</span><span class="nav-meta">{{ number_format($stats['senders']) }}</span></a>
            <a href="{{ route('admin.campaigns') }}" class="nav-link {{ request()->routeIs('admin.campaigns') ? 'active' : '' }}"><span>Pengiriman</span><span class="nav-meta">{{ number_format($stats['campaigns']) }}</span></a>
            <a href="{{ route('admin.analytics') }}" class="nav-link {{ request()->routeIs('admin.analytics') ? 'active' : '' }}"><span>Analytics</span><span class="nav-meta">{{ number_format($stats['sent_recipients']) }}</span></a>
        </div>
        <div class="sidebar-foot">Mulai dari batch kecil, pakai template import resmi, dan pantau limit sender sebelum menaikkan volume kirim.</div>
    </aside>
    <main class="main">
        <div class="topbar">
            <div class="headline"><h2>{{ $pageTitle ?? 'Overview' }}</h2><p>{{ $pageDescription ?? 'Panel kontrol internal untuk operasional blast email POSI.' }}</p></div>
            <div class="topbar-actions">
                <div class="user-badge"><span class="user-dot"></span>{{ session('admin_username', 'admin') }}</div>
                <a class="button-secondary" href="{{ route('imports.template') }}">Download Format</a>
                <a class="button" href="{{ route('admin.campaigns') }}">Buat Pengiriman</a>
                <form action="{{ route('logout') }}" method="post" style="margin:0;">
                    @csrf
                    <button type="submit" class="button-secondary">Keluar</button>
                </form>
            </div>
        </div>
        @if (session('status'))
            <div class="flash">
                <strong>{{ session('status') }}</strong>
                @if (session('import_summary'))
                    <div class="summary"><span>Sheet: {{ session('import_summary.sheets') }}</span><span>Dipindai: {{ session('import_summary.rows_scanned') }}</span><span>Baru: {{ session('import_summary.contacts_created') }}</span><span>Update: {{ session('import_summary.contacts_updated') }}</span><span>Dilewati: {{ session('import_summary.skipped') }}</span></div>
                @endif
            </div>
        @endif
        @if ($errors->any())
            <div class="flash" style="background:rgba(240,68,56,.08); border-color:rgba(240,68,56,.16);">
                <strong>Perlu diperbaiki sebelum lanjut</strong>
                <div class="summary">@foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach</div>
            </div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
