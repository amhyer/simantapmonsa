<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Status Pendaftaran Sekolah - SIMANTAP_MONSA</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <style>
        :root {
            --navy: #1F3864;
            --navy-light: #2B4A80;
            --gold: #B8860B;
            --ok: #067647;
            --warn: #B54708;
            --bad: #B42318;
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
            padding: 24px 16px;
        }
        .status-card {
            width: 100%;
            max-width: 540px;
            background: #fff;
            border-radius: 18px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(31,56,100,.10);
            padding: 32px;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-pending { background: #FEF3E2; color: var(--warn); }
        .badge-approved { background: #ECFDF5; color: var(--ok); }
        .badge-rejected { background: #FEE9E7; color: var(--bad); }
        .step-box {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #F9FAFB;
            font-size: 13.5px;
            line-height: 1.6;
        }
        .alert-ok {
            background: #ECFDF5;
            border-left: 3px solid #10B981;
            color: var(--ok);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .alert-err {
            background: #FEE9E7;
            border-left: 3px solid var(--bad);
            color: var(--bad);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-navy { background: var(--navy); color: #fff; }
        .btn-outline { background: #fff; color: var(--navy); border: 1px solid var(--border); }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13.5px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="status-card">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;margin-bottom:16px">
            <div>
                <h1 style="font-size:20px;color:var(--navy);margin-bottom:4px">{{ $tenant->nama_sekolah }}</h1>
                <p style="font-size:13px;color:var(--text-muted)">NPSN: <strong>{{ $tenant->npsn }}</strong> · Subdomain: <code>{{ $tenant->subdomain }}</code></p>
            </div>
            @if($tenant->approvalStatus() === 'approved' && $tenant->aktif)
                <span class="badge badge-approved">Aktif &amp; Disetujui</span>
            @elseif($tenant->approvalStatus() === 'rejected')
                <span class="badge badge-rejected">Ditolak</span>
            @else
                <span class="badge badge-pending">Menunggu Aktivasi</span>
            @endif
        </div>

        @if(session('success'))
            <div class="alert-ok" role="status">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert-err" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="step-box">
            <div style="margin-bottom:10px">
                <strong>1. Verifikasi Email Operator:</strong>
                @if($tenant->isEmailVerified())
                    <span style="color:var(--ok);font-weight:700">✓ Sudah Terverifikasi</span>
                @else
                    <span style="color:var(--warn);font-weight:700">Belum Diverifikasi</span>
                    <p style="font-size:12.5px;color:var(--text-muted);margin-top:4px">
                        Kami telah mengirimkan tautan verifikasi ke alamat email operator yang didaftarkan (<strong>{{ $maskedEmail }}</strong>). Periksa kotak masuk atau folder Spam Anda.
                    </p>
                @endif
            </div>
            <div>
                <strong>2. Persetujuan Super Administrator:</strong>
                @if($tenant->approvalStatus() === 'approved' && $tenant->aktif)
                    <span style="color:var(--ok);font-weight:700">✓ Sudah Disetujui</span>
                @elseif($tenant->approvalStatus() === 'rejected')
                    <span style="color:var(--bad);font-weight:700">Ditolak</span>
                    @if(!empty($tenant->meta['rejection_reason']))
                        <p style="font-size:12.5px;color:var(--bad);margin-top:4px">Alasan: {{ $tenant->meta['rejection_reason'] }}</p>
                    @endif
                @else
                    <span style="color:var(--warn);font-weight:700">Menunggu Peninjauan</span>
                    <p style="font-size:12.5px;color:var(--text-muted);margin-top:4px">
                        Setelah email Anda terverifikasi, Super Administrator akan memvalidasi NPSN sekolah dan mengaktifkan akun Anda. Notifikasi akan dikirimkan melalui email.
                    </p>
                @endif
            </div>
        </div>

        @if(!$tenant->isEmailVerified())
            <form action="{{ route('sekolah.verify-email.resend', $tenant->uuid) }}" method="POST" style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border)">
                @csrf
                <label for="fld-resend-email" style="display:block;font-size:12.5px;font-weight:600;margin-bottom:6px;color:#344054">
                    Belum menerima email verifikasi? Masukkan email operator untuk kirim ulang:
                </label>
                <input id="fld-resend-email" type="email" name="email" class="form-control" placeholder="Masukkan email operator yang didaftarkan" required>
                <button type="submit" class="btn btn-navy" style="width:100%">Kirim Ulang Email Verifikasi</button>
            </form>
        @endif

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
            <a href="{{ route('login') }}" class="btn btn-outline">&larr; Halaman Login</a>
            <a href="{{ route('sekolah.status', $tenant->uuid) }}" class="btn btn-outline">Muat Ulang Status</a>
        </div>
    </div>
</body>
</html>
