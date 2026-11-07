<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - SIMANTAP_MONSA</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        :root { --navy: #1F3864; --gold: #B8860B; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #F5F7FB; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(31,56,100,.12); width: 100%; max-width: 400px; padding: 40px 32px; }
        .brand { text-align: center; margin-bottom: 32px; }
        .brand h1 { font-size: 28px; font-weight: 800; color: var(--navy); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .brand .dot { width: 12px; height: 12px; border-radius: 50%; background: var(--gold); }
        .brand p { font-size: 12px; color: #667085; margin-top: 8px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #344054; }
        .form-group input { width: 100%; padding: 12px 14px; border: 1.5px solid #E4E7EC; border-radius: 10px; font-size: 14px; outline: none; transition: .15s; }
        .form-group input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(31,56,100,.08); }
        .btn { width: 100%; padding: 12px; background: var(--navy); color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: .15s; }
        .btn:hover { background: #2B4A80; }
        .error { background: #FEE9E7; color: #B42318; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .status { background: #E7F6EC; color: #067647; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .forgot { display: block; text-align: right; margin-top: 12px; font-size: 13px; color: var(--navy); text-decoration: none; font-weight: 600; }
        .forgot:hover { text-decoration: underline; }
        .demo { background: #FBF4E4; border-left: 3px solid var(--gold); padding: 12px 14px; border-radius: 8px; font-size: 12.5px; margin-top: 20px; }
        .demo b { color: var(--navy); }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <img src="{{ asset('images/logo-128.png') }}" alt="Logo SIMANTAP_MONSA" style="width:72px;height:72px;border-radius:16px;object-fit:cover;margin:0 auto 12px;display:block;box-shadow:0 6px 20px rgba(31,56,100,.18);border:2px solid #B8860B">
            <h1><span class="dot"></span>SIMANTAP_MONSA</h1>
            <p>Situs Mandiri Terintegrasi Aplikasi Pembelajaran<br><span style="font-size:11px;color:#1F3864;font-weight:600">UPTD SD Negeri Unggulan Mongisidi 1</span></p>
        </div>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        @if(session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label for="nama_pengguna">Nama Pengguna</label>
                <input type="text" id="nama_pengguna" name="nama_pengguna" value="{{ old('nama_pengguna') }}" required autofocus placeholder="Masukkan nama pengguna">
            </div>
            <div class="form-group">
                <label for="kata_sandi">Kata Sandi</label>
                <input type="password" id="kata_sandi" name="kata_sandi" required placeholder="Masukkan kata sandi">
            </div>
            <button type="submit" class="btn">Masuk</button>
        </form>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:13px">
            <a class="forgot" style="margin-top:0" href="{{ route('password.request') }}">Lupa kata sandi?</a>
            <a href="{{ route('sekolah.register') }}" style="color:var(--navy);text-decoration:none;font-weight:600">Daftar Sekolah &rarr;</a>
        </div>

        @if(app()->environment('local'))
            <div class="demo">
                <b>Demo:</b> admin / admin123
            </div>
        @endif
    </div>
</body>
</html>
