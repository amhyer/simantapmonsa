<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verifikasi Dua Faktor (MFA) - SIMANTAP_MONSA</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        :root { --navy: #1F3864; --gold: #B8860B; --bad: #B42318; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #F5F7FB; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
        .login-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(31,56,100,.12); width: 100%; max-width: 420px; padding: 40px 32px; }
        .brand { text-align: center; margin-bottom: 24px; }
        .brand h1 { font-size: 24px; font-weight: 800; color: var(--navy); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .brand .dot { width: 12px; height: 12px; border-radius: 50%; background: var(--gold); }
        .brand p { font-size: 12.5px; color: #667085; margin-top: 8px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #344054; }
        .form-group input { width: 100%; padding: 12px 14px; border: 1.5px solid #E4E7EC; border-radius: 10px; font-size: 18px; letter-spacing: 2px; text-align: center; font-weight: 700; outline: none; transition: .15s; }
        .form-group input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(31,56,100,.08); }
        .btn { width: 100%; padding: 12px; background: var(--navy); color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: .15s; }
        .btn:hover { background: #2B4A80; }
        .error { background: #FEE9E7; color: var(--bad); padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .hint { font-size: 12.5px; color: #667085; line-height: 1.55; margin-bottom: 18px; }
        .back { display: block; text-align: center; margin-top: 18px; font-size: 13px; color: var(--navy); text-decoration: none; font-weight: 600; }
        .back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <img src="{{ asset('images/logo-128.png') }}" alt="Logo SIMANTAP_MONSA" style="width:64px;height:64px;border-radius:14px;object-fit:cover;margin:0 auto 10px;display:block;box-shadow:0 4px 16px rgba(31,56,100,.15);border:2px solid #B8860B">
            <h1><span class="dot"></span>Verifikasi MFA</h1>
            <p>Akun: <strong>{{ $namaPengguna }}</strong></p>
        </div>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <p class="hint">Masukkan kode 6 digit dari aplikasi authenticator Anda (Google Authenticator / Authy / Microsoft Authenticator), atau salah satu kode pemulihan 8 karakter (contoh: <code>ABCD-EFGH</code>).</p>

        <form method="POST" action="{{ route('mfa.challenge.verify') }}">
            @csrf
            <div class="form-group">
                <label for="code">Kode OTP / Kode Pemulihan</label>
                <input type="text" id="code" name="code" required autofocus autocomplete="one-time-code" placeholder="000000">
            </div>
            <button type="submit" class="btn">Verifikasi &amp; Masuk</button>
        </form>

        <a class="back" href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </div>
</body>
</html>
