<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - SIMANTAP</title>
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
        .demo { background: #FBF4E4; border-left: 3px solid var(--gold); padding: 12px 14px; border-radius: 8px; font-size: 12.5px; margin-top: 20px; }
        .demo b { color: var(--navy); }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <h1><span class="dot"></span>SIMANTAP</h1>
            <p>Situs Mandiri Terintegrasi Aplikasi Pembelajaran</p>
        </div>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
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

        @if(app()->environment('local'))
            <div class="demo">
                <b>Demo:</b> admin / admin123
            </div>
        @endif
    </div>
</body>
</html>
