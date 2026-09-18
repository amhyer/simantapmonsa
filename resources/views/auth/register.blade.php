<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - SIMANTAP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F0F2F5;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .register-container {
            max-width: 480px; width: 100%; background: #fff;
            border-radius: 16px; padding: 40px 36px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
        }
        .register-container .logo { text-align: center; margin-bottom: 28px; }
        .register-container .logo h1 { font-size: 24px; font-weight: 800; color: #1F3864; }
        .register-container .logo h1 span { color: #B8860B; }
        .register-container .logo p { font-size: 14px; color: #667085; }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 13px; font-weight: 600;
            color: #101828; margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px; border: 1.5px solid #E4E7EC;
            border-radius: 10px; font-size: 14px; transition: 0.15s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: #1F3864;
            box-shadow: 0 0 0 3px rgba(31,56,100,0.1);
        }
        .form-group .hint { font-size: 12px; color: #667085; margin-top: 4px; }
        .btn-register {
            width: 100%; padding: 12px; background: #1F3864; color: #fff;
            border: none; border-radius: 10px; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: 0.15s;
        }
        .btn-register:hover { background: #2B4A80; }
        .error-message {
            background: #FEE9E7; color: #B42318; padding: 10px 14px;
            border-radius: 8px; font-size: 13px; margin-bottom: 16px;
            border-left: 3px solid #B42318;
        }
        .login-link {
            text-align: center; margin-top: 16px; font-size: 14px; color: #667085;
        }
        .login-link a { color: #1F3864; font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .terms {
            font-size: 12px; color: #667085; text-align: center; margin-top: 12px;
        }
        .terms a { color: #1F3864; }
        .success-message {
            background: #E6F5F0; color: #12805C; padding: 10px 14px;
            border-radius: 8px; font-size: 13px; margin-bottom: 16px;
            border-left: 3px solid #12805C;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>SIMAN<span>TAP</span></h1>
            <p>Daftar akun baru</p>
        </div>

        @if(session('success'))
            <div class="success-message">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="error-message">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap"
                       value="{{ old('nama_lengkap') }}" required>
            </div>

            <div class="form-group">
                <label for="nama_pengguna">Nama Pengguna</label>
                <input type="text" id="nama_pengguna" name="nama_pengguna"
                       value="{{ old('nama_pengguna') }}" required>
                <div class="hint">Digunakan untuk login. Huruf kecil, tanpa spasi.</div>
            </div>

            <div class="form-group">
                <label for="kata_sandi">Kata Sandi</label>
                <input type="password" id="kata_sandi" name="kata_sandi" required>
                <div class="hint">Minimal 6 karakter.</div>
            </div>

            <div class="form-group">
                <label for="kata_sandi_confirmation">Konfirmasi Kata Sandi</label>
                <input type="password" id="kata_sandi_confirmation" name="kata_sandi_confirmation" required>
            </div>

            <div class="form-group">
                <label for="peran">Daftar Sebagai</label>
                <select id="peran" name="peran" required>
                    <option value="guru" {{ old('peran') == 'guru' ? 'selected' : '' }}>Guru</option>
                    <option value="siswa" {{ old('peran') == 'siswa' ? 'selected' : '' }}>Siswa</option>
                    <option value="ortu" {{ old('peran') == 'ortu' ? 'selected' : '' }}>Orang Tua</option>
                    <option value="kepsek" {{ old('peran') == 'kepsek' ? 'selected' : '' }}>Kepala Sekolah</option>
                </select>
            </div>

            <button type="submit" class="btn-register">Daftar Sekarang</button>
        </form>

        <div class="login-link">
            Sudah punya akun? <a href="{{ route('login') }}">Login di sini</a>
        </div>

        <div class="terms">
            Dengan mendaftar, Anda menyetujui <a href="#">Syarat & Ketentuan</a> dan <a href="#">Kebijakan Privasi</a>.
        </div>
    </div>
</body>
</html>
