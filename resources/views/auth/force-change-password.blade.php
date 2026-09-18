<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ganti Password - SIMANTAP</title>
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
        .warning { background: #FBF4E4; border-left: 3px solid var(--gold); padding: 12px 14px; border-radius: 8px; font-size: 12.5px; margin-bottom: 16px; }
        .info { background: #EDF5FF; color: #1F3864; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        ul { margin: 8px 0 0 16px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <h1><span class="dot"></span>SIMANTAP</h1>
            <p>Ubah Password Pertama Kali</p>
        </div>

        @if(session('warning'))
            <div class="warning">{{ session('warning') }}</div>
        @endif

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <div class="info">
            Anda harus mengubah password sebelum melanjutkan.
            <ul>
                <li>Minimal 8 karakter</li>
                <li>Tidak boleh sama dengan password lama</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('force-password-change.update') }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="password">Password Baru</label>
                <input type="password" id="password" name="password" required autofocus placeholder="Masukkan password baru (min. 8 karakter)">
            </div>
            <div class="form-group">
                <label for="password_confirmation">Konfirmasi Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Ulangi password baru">
            </div>
            <button type="submit" class="btn">Simpan & Lanjutkan</button>
        </form>
    </div>
</body>
</html>
