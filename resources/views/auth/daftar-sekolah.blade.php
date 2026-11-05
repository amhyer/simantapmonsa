<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pendaftaran Sekolah Baru - SIMANTAP</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        .register-container {
            width: 100%;
            max-width: 680px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(31,56,100,.10);
            padding: 40px 36px;
        }
        .header {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 26px;
            font-weight: 800;
            color: var(--navy);
            text-decoration: none;
            margin-bottom: 6px;
        }
        .brand .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--gold);
        }
        .header p {
            font-size: 13.5px;
            color: var(--text-muted);
        }
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
            border-bottom: 1.5px solid var(--border);
            padding-bottom: 8px;
            margin: 24px 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .register-container { padding: 28px 20px; }
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group.col-span-2 {
            grid-column: span 2;
        }
        @media (max-width: 640px) {
            .form-group.col-span-2 { grid-column: span 1; }
        }
        .form-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: #344054;
            margin-bottom: 6px;
        }
        .form-group label span.req {
            color: #D92D20;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 13.5px;
            outline: none;
            transition: .15s;
            font-family: inherit;
        }
        .form-control:focus {
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(31,56,100,.08);
        }
        .input-group {
            display: flex;
            align-items: center;
        }
        .input-group input {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            border-right: none;
        }
        .input-group .input-addon {
            background: #F2F4F7;
            border: 1.5px solid var(--border);
            border-left: none;
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .hint {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: .15s;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover {
            background: var(--navy-light);
        }
        .alert-error {
            background: #FEE9E7;
            color: #B42318;
            border-left: 4px solid #D92D20;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .alert-error ul {
            margin: 6px 0 0 16px;
        }
        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .footer-note a {
            color: var(--navy);
            text-decoration: none;
            font-weight: 600;
        }
        .footer-note a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="header">
            <a href="{{ route('home') }}" class="brand">
                <span class="dot"></span>SIMANTAP
            </a>
            <p>Pendaftaran Akun Sekolah Baru (Multi-Tenant Platform)</p>
        </div>

        @if($errors->any())
            <div class="alert-error">
                <b>Mohon periksa kesalahan pengisian berikut:</b>
                <ul>
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('sekolah.register.store') }}" id="formDaftar">
            @csrf

            <!-- Bagian 1: Identitas Sekolah -->
            <div class="section-title">
                🏛️ 1. Identitas Sekolah
            </div>

            <div class="form-grid">
                <div class="form-group col-span-2">
                    <label for="nama_sekolah">Nama Resmi Sekolah <span class="req">*</span></label>
                    <input type="text" id="nama_sekolah" name="nama_sekolah" class="form-control"
                           value="{{ old('nama_sekolah') }}" required placeholder="Contoh: SMP Negeri 2 Makassar"
                           onkeyup="autoSlugSubdomain(this.value)">
                </div>

                <div class="form-group">
                    <label for="npsn">NPSN (Nomor Pokok Sekolah Nasional) <span class="req">*</span></label>
                    <input type="text" id="npsn" name="npsn" class="form-control"
                           value="{{ old('npsn') }}" required maxlength="8" placeholder="8 digit angka (contoh: 40312345)">
                    <div class="hint">Harus 8 digit angka terdaftar di Kemendikdasmen.</div>
                </div>

                <div class="form-group">
                    <label for="jenjang">Jenjang Pendidikan <span class="req">*</span></label>
                    <select id="jenjang" name="jenjang" class="form-control" required>
                        <option value="">-- Pilih Jenjang --</option>
                        <option value="SD" {{ old('jenjang') === 'SD' ? 'selected' : '' }}>SD / MI</option>
                        <option value="SMP" {{ old('jenjang') === 'SMP' ? 'selected' : '' }}>SMP / MTs</option>
                        <option value="SMA" {{ old('jenjang') === 'SMA' ? 'selected' : '' }}>SMA / MA</option>
                        <option value="SMK" {{ old('jenjang') === 'SMK' ? 'selected' : '' }}>SMK</option>
                    </select>
                </div>

                <div class="form-group col-span-2">
                    <label for="subdomain">Subdomain / Kode Sekolah <span class="req">*</span></label>
                    <div class="input-group">
                        <input type="text" id="subdomain" name="subdomain" class="form-control"
                               value="{{ old('subdomain') }}" required placeholder="smpn2mks" pattern="[a-z0-9\-]+"
                               style="text-transform:lowercase">
                        <span class="input-addon">.simantap.id</span>
                    </div>
                    <div class="hint">Alamat akses sekolah Anda. Hanya huruf kecil, angka, dan tanda hubung (-).</div>
                </div>

                <div class="form-group">
                    <label for="kota">Kota / Kabupaten</label>
                    <input type="text" id="kota" name="kota" class="form-control"
                           value="{{ old('kota') }}" placeholder="Contoh: Kota Makassar">
                </div>

                <div class="form-group">
                    <label for="alamat">Alamat Singkat</label>
                    <input type="text" id="alamat" name="alamat" class="form-control"
                           value="{{ old('alamat') }}" placeholder="Contoh: Jl. Ahmad Yani No. 5">
                </div>
            </div>

            <!-- Bagian 2: Akun Administrator Sekolah -->
            <div class="section-title">
                👤 2. Akun Administrator Sekolah
            </div>

            <div class="form-grid">
                <div class="form-group col-span-2">
                    <label for="nama_lengkap">Nama Lengkap Administrator <span class="req">*</span></label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control"
                           value="{{ old('nama_lengkap') }}" required placeholder="Contoh: Muhammad Akbar, S.Kom">
                    <div class="hint">Akun ini memiliki kewenangan penuh mengelola data sekolah Anda.</div>
                </div>

                <div class="form-group">
                    <label for="nama_pengguna">Nama Pengguna (Username Login) <span class="req">*</span></label>
                    <input type="text" id="nama_pengguna" name="nama_pengguna" class="form-control"
                           value="{{ old('nama_pengguna') }}" required placeholder="Contoh: admin_smpn2">
                </div>

                <div class="form-group">
                    <label for="email">Email Resmi Administrator <span class="req">*</span></label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="{{ old('email') }}" required placeholder="admin@sekolah.sch.id">
                </div>

                <div class="form-group col-span-2">
                    <label for="telepon">Nomor WhatsApp / HP</label>
                    <input type="text" id="telepon" name="telepon" class="form-control"
                           value="{{ old('telepon') }}" placeholder="08xxxxxxxxxx">
                </div>

                <div class="form-group">
                    <label for="kata_sandi">Kata Sandi <span class="req">*</span></label>
                    <input type="password" id="kata_sandi" name="kata_sandi" class="form-control"
                           required minlength="8" placeholder="Minimal 8 karakter">
                </div>

                <div class="form-group">
                    <label for="kata_sandi_confirmation">Konfirmasi Kata Sandi <span class="req">*</span></label>
                    <input type="password" id="kata_sandi_confirmation" name="kata_sandi_confirmation" class="form-control"
                           required minlength="8" placeholder="Ulangi kata sandi">
                </div>
            </div>

            <button type="submit" class="btn-submit">
                ✓ Daftarkan Sekolah Sekarang
            </button>
        </form>

        <div class="footer-note">
            Sudah memiliki akun sekolah? <a href="{{ route('login') }}">Masuk ke Sistem</a>
        </div>
    </div>

    <script>
        function autoSlugSubdomain(nama) {
            const subdomainInput = document.getElementById('subdomain');
            if (!subdomainInput.dataset.manual) {
                let slug = nama.toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .substring(0, 20)
                    .replace(/^-+|-+$/g, '');
                subdomainInput.value = slug;
            }
        }

        document.getElementById('subdomain').addEventListener('input', function() {
            this.dataset.manual = 'true';
        });
    </script>
</body>
</html>
