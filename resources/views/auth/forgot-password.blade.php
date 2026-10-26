<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lupa Kata Sandi - SIMANTAP</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        :root { --navy: #1F3864; --gold: #B8860B; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #F5F7FB; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
        .login-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(31,56,100,.12); width: 100%; max-width: 420px; padding: 40px 32px; }
        .brand { text-align: center; margin-bottom: 28px; }
        .brand h1 { font-size: 26px; font-weight: 800; color: var(--navy); display: flex; align-items: center; justify-content: center; gap: 10px; }
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
        .hint { font-size: 12.5px; color: #667085; line-height: 1.55; margin-bottom: 18px; }
        .back { display: block; text-align: center; margin-top: 18px; font-size: 13px; color: var(--navy); text-decoration: none; font-weight: 600; }
        .back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <h1><span class="dot"></span>SIMANTAP</h1>
            <p>Atur ulang kata sandi akun</p>
        </div>

        @if(session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <p class="hint">Masukkan nama pengguna atau email yang terdaftar. Tautan reset hanya dapat dikirim jika akun sudah memiliki email.</p>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="form-group">
                <label for="login">Nama Pengguna / Email</label>
                <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus placeholder="contoh: guru01 atau guru@sekolah.sch.id">
            </div>
            <button type="submit" class="btn">Kirim Tautan Reset</button>
        </form>

        <a class="back" href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </div>
</body>
</html>
