<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk - SIMANTAP_MONSA</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        :root {
            --navy: #1F3864;
            --navy-light: #2B4A80;
            --gold: #B8860B;
            --bg: #F5F7FB;
            --border: #E4E7EC;
            --text-dark: #1D2939;
            --text-muted: #667085;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px 16px;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        .back-nav {
            margin-bottom: 16px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--navy);
            text-decoration: none;
            padding: 8px 14px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        .back-link:hover {
            background: #EEF2F9;
            color: var(--navy-light);
            border-color: #CBD5E1;
            transform: translateX(-2px);
        }
        .login-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(31,56,100,.10);
            border: 1px solid rgba(228,231,236,0.8);
            padding: 36px 32px;
            width: 100%;
        }
        .brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand img {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            object-fit: cover;
            margin: 0 auto 12px;
            display: block;
            box-shadow: 0 6px 20px rgba(31,56,100,.16);
            border: 2px solid var(--gold);
        }
        .brand h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--navy);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: -0.5px;
        }
        .brand .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--gold);
            display: inline-block;
        }
        .brand p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
            line-height: 1.45;
        }
        .brand .sub-sch {
            font-size: 11px;
            color: var(--navy);
            font-weight: 700;
            margin-top: 2px;
            display: block;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 6px;
            color: #344054;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #E4E7EC;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            background: #fff;
            color: var(--text-dark);
        }
        .form-control:focus {
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(31,56,100,.10);
        }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            padding-right: 46px;
        }
        .btn-toggle-password {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 44px;
            background: transparent;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748B;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
            transition: color 0.15s ease;
        }
        .btn-toggle-password:hover {
            color: var(--navy);
        }
        .btn-toggle-password:focus {
            outline: none;
            color: var(--navy);
        }
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s ease;
            box-shadow: 0 2px 6px rgba(31,56,100,0.2);
        }
        .btn-primary:hover {
            background: var(--navy-light);
            box-shadow: 0 4px 12px rgba(31,56,100,0.3);
            transform: translateY(-1px);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .error {
            background: #FEE9E7;
            border-left: 3px solid #D92D20;
            color: #B42318;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .status {
            background: #ECFDF5;
            border-left: 3px solid #10B981;
            color: #067647;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .auth-footer-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #F2F4F7;
            font-size: 13px;
        }
        .auth-footer-links a {
            color: var(--navy);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.15s;
        }
        .auth-footer-links a:hover {
            text-decoration: underline;
            color: var(--navy-light);
        }
        .demo-box {
            background: #FBF4E4;
            border-left: 3px solid var(--gold);
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            margin-top: 20px;
            color: #714B00;
        }
        .demo-box b {
            color: var(--navy);
        }
    </style>
</head>
<body>
    <div class="login-container">
        {{-- Tombol Kembali ke Halaman Utama / Landing Page --}}
        <div class="back-nav">
            <a href="{{ route('home') }}" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span>Kembali ke Beranda</span>
            </a>
        </div>

        <div class="login-card">
            <div class="brand">
                <img src="{{ asset('images/logo-128.png') }}" alt="Logo SIMANTAP_MONSA">
                <h1><span class="dot"></span>SIMANTAP_MONSA</h1>
                <p>
                    Situs Mandiri Terintegrasi Aplikasi Pembelajaran
                    <span class="sub-sch">{{ $layoutNamaSekolah ?? 'UPTD SD Negeri Unggulan Mongisidi 1' }}</span>
                </p>
            </div>

            @if($errors->any())
                <div class="error" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            @if(session('status'))
                <div class="status" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label for="nama_pengguna">Nama Pengguna (Username)</label>
                    <input type="text" id="nama_pengguna" name="nama_pengguna" class="form-control" value="{{ old('nama_pengguna') }}" required autofocus placeholder="Masukkan nama pengguna" autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="kata_sandi">Kata Sandi</label>
                    <div class="password-wrapper">
                        <input type="password" id="kata_sandi" name="kata_sandi" class="form-control" required placeholder="Masukkan kata sandi" autocomplete="current-password">
                        <button type="button" class="btn-toggle-password" id="btnTogglePassword" aria-label="Tampilkan kata sandi" tabindex="-1">
                            {{-- Ikon Mata Terbuka (Default saat password tersembunyi) --}}
                            <svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            {{-- Ikon Mata Tertutup/Dicoret (Saat password terlihat) --}}
                            <svg id="eyeSlashIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Masuk ke Sistem</button>
            </form>

            <div class="auth-footer-links">
                <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
                <a href="{{ route('sekolah.register') }}">Daftar Sekolah &rarr;</a>
            </div>

            @if(app()->environment('local'))
                <div class="demo-box">
                    <b>Demo Akun:</b> admin / admin123
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('kata_sandi');
            const toggleBtn = document.getElementById('btnTogglePassword');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeSlashIcon = document.getElementById('eyeSlashIcon');

            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener('click', function () {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    toggleBtn.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');

                    if (eyeIcon && eyeSlashIcon) {
                        eyeIcon.style.display = isPassword ? 'none' : 'block';
                        eyeSlashIcon.style.display = isPassword ? 'block' : 'none';
                    }
                    passwordInput.focus();
                });
            }
        });
    </script>
</body>
</html>
