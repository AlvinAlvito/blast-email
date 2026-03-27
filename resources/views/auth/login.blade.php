<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | POSI Blast Console</title>
    <style>
        :root { --bg:#f4f7fb; --surface:#ffffff; --ink:#10233d; --muted:#62748b; --line:#d8e2ef; --brand:#175cd3; --brand-2:#53b1fd; --shadow:0 24px 60px rgba(15,23,42,.15); }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:"Segoe UI", Inter, Arial, sans-serif; color:var(--ink); background:
            radial-gradient(circle at top left, rgba(83,177,253,.32), transparent 26%),
            radial-gradient(circle at bottom right, rgba(23,92,211,.18), transparent 24%),
            linear-gradient(160deg, #f8fbff 0%, #eef4fb 52%, #e8eff9 100%); }
        .login-shell { min-height:100vh; display:grid; grid-template-columns:1.1fr .9fr; }
        .login-side { padding:56px; display:grid; align-content:space-between; gap:32px; }
        .brand-mark { width:60px; height:60px; border-radius:18px; display:grid; place-items:center; background:linear-gradient(135deg, var(--brand-2), var(--brand)); color:#fff; font-size:28px; font-weight:800; box-shadow:0 18px 40px rgba(23,92,211,.28); }
        .hero { display:grid; gap:18px; max-width:560px; }
        .eyebrow { letter-spacing:.18em; text-transform:uppercase; font-size:12px; color:#4c6a92; font-weight:700; }
        .hero h1 { margin:0; font-size:clamp(38px, 5vw, 66px); line-height:1.02; letter-spacing:-.04em; }
        .hero p { margin:0; font-size:18px; line-height:1.75; color:#53657d; }
        .feature-list { display:grid; gap:14px; max-width:520px; }
        .feature { display:flex; gap:12px; align-items:flex-start; padding:14px 16px; border:1px solid rgba(216,226,239,.8); background:rgba(255,255,255,.55); border-radius:18px; backdrop-filter:blur(10px); }
        .feature-dot { width:12px; height:12px; border-radius:999px; margin-top:7px; background:linear-gradient(135deg, var(--brand-2), var(--brand)); box-shadow:0 0 0 6px rgba(83,177,253,.14); }
        .feature strong { display:block; margin-bottom:4px; }
        .feature span { color:var(--muted); font-size:14px; line-height:1.6; }
        .login-panel-wrap { display:grid; place-items:center; padding:40px; }
        .login-panel { width:min(460px, 100%); background:rgba(255,255,255,.88); border:1px solid rgba(216,226,239,.9); border-radius:30px; box-shadow:var(--shadow); backdrop-filter:blur(14px); padding:34px; display:grid; gap:22px; }
        .login-panel h2 { margin:0; font-size:32px; letter-spacing:-.03em; }
        .login-panel p { margin:0; color:var(--muted); line-height:1.7; }
        .notice, .error-box { padding:14px 16px; border-radius:16px; font-size:14px; line-height:1.6; }
        .notice { background:rgba(23,92,211,.08); color:#254d7a; border:1px solid rgba(23,92,211,.12); }
        .error-box { background:rgba(240,68,56,.08); color:#9a2c24; border:1px solid rgba(240,68,56,.14); }
        .login-form { display:grid; gap:16px; }
        label { display:grid; gap:8px; color:#4f6482; font-size:14px; font-weight:600; }
        input { width:100%; border:1px solid var(--line); border-radius:16px; padding:15px 16px; font:inherit; color:var(--ink); background:#fff; box-shadow:inset 0 1px 2px rgba(15,23,42,.02); }
        input:focus { outline:none; border-color:rgba(23,92,211,.45); box-shadow:0 0 0 4px rgba(83,177,253,.15); }
        .submit { min-height:50px; border:none; border-radius:16px; font:inherit; font-weight:800; color:#fff; background:linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow:0 18px 30px rgba(23,92,211,.18); cursor:pointer; }
        .submit:hover { filter:brightness(1.02); }
        .panel-foot { color:var(--muted); font-size:13px; text-align:center; }
        @media (max-width: 980px) {
            .login-shell { grid-template-columns:1fr; }
            .login-side { padding:36px 24px 8px; }
            .login-panel-wrap { padding:24px; align-items:start; }
            .hero h1 { font-size:42px; }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <section class="login-side">
            <div class="hero">
                <div class="brand-mark">P</div>
                <div class="eyebrow">Internal Access</div>
                <h1>POSI Blast Console</h1>
                <p>Masuk ke dashboard untuk mengelola data kontak, email pengirim, pengiriman massal, dan analitik operasional dalam satu panel yang rapi.</p>
            </div>

            <div class="feature-list">
                <div class="feature">
                    <div class="feature-dot"></div>
                    <div>
                        <strong>Manajemen Kontak</strong>
                        <span>Import data Excel, normalisasi isi, dan telusuri kontak berdasarkan batch file.</span>
                    </div>
                </div>
                <div class="feature">
                    <div class="feature-dot"></div>
                    <div>
                        <strong>Kontrol Pengiriman</strong>
                        <span>Atur akun sender, jeda antar email, retry gagal, serta pause atau stop proses saat dibutuhkan.</span>
                    </div>
                </div>
                <div class="feature">
                    <div class="feature-dot"></div>
                    <div>
                        <strong>Analitik Operasional</strong>
                        <span>Lihat performa pengiriman, komposisi data, dan kualitas email penerima secara cepat.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="login-panel-wrap">
            <div class="login-panel">
                <div>
                    <h2>Masuk Dashboard</h2>
                    <p>Gunakan akun internal untuk membuka seluruh menu admin.</p>
                </div>

                @if (session('status'))
                    <div class="notice">{{ session('status') }}</div>
                @endif

                @if ($errors->has('login'))
                    <div class="error-box">{{ $errors->first('login') }}</div>
                @endif

                <form method="post" action="{{ route('login.attempt') }}" class="login-form">
                    @csrf
                    <label>
                        Username
                        <input type="text" name="username" value="{{ old('username') }}" autocomplete="username" required>
                    </label>
                    <label>
                        Password
                        <input type="password" name="password" autocomplete="current-password" required>
                    </label>
                    <button type="submit" class="submit">Masuk</button>
                </form>

                <div class="panel-foot">Akses ini hanya untuk penggunaan internal POSI.</div>
            </div>
        </section>
    </div>
</body>
</html>
