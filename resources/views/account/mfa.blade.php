@extends('layouts.app')

@section('title', 'Keamanan & Autentikasi Dua Faktor (MFA)')
@section('page_title', 'Autentikasi Dua Faktor (MFA)')
@section('page_subtitle', 'Lindungi akun Anda dengan aplikasi authenticator (TOTP RFC 6238)')

@section('sidebar')
    @if(!empty($sidebarPartial) && View::exists($sidebarPartial))
        @include($sidebarPartial)
    @endif
@endsection

@section('content')
@if(is_array($freshRecoveryCodes) && count($freshRecoveryCodes) > 0)
    <div class="card" style="margin-bottom:20px;border:1.5px solid #F59E0B;background:#FFFBEB">
        <div class="card-header">
            <h3><i class="fas fa-key" style="margin-right:8px;color:#B45309"></i>Simpan Kode Pemulihan (Ditampilkan Sekali Saja)</h3>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#92400E;margin-bottom:12px">
                Simpan 8 kode berikut di tempat aman. Masing-masing kode hanya dapat dipakai <strong>sekali</strong> jika Anda kehilangan akses ke perangkat authenticator.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px">
                @foreach($freshRecoveryCodes as $rc)
                    <div style="background:#fff;border:1px solid #FCD34D;border-radius:8px;padding:10px 12px;text-align:center;font-family:monospace;font-size:15px;font-weight:700;letter-spacing:1px;color:#1F3864">
                        {{ $rc }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-shield-halved" style="margin-right:8px;color:var(--navy)"></i>Status MFA Akun</h3>
            @if($mfaEnabled)
                <span class="tag tag-ok">Aktif</span>
            @else
                <span class="tag tag-warn">Belum Aktif</span>
            @endif
        </div>
        <div class="card-body">
            @if($mfaEnabled)
                <p style="font-size:13.5px;color:var(--text);margin-bottom:12px">
                    Autentikasi Dua Faktor (TOTP) aktif sejak <strong>{{ $user->mfa_confirmed_at?->format('d/m/Y H:i') }}</strong>.
                </p>
                <p style="font-size:13px;color:var(--muted);margin-bottom:18px">
                    Sisa kode pemulihan yang tersedia: <strong>{{ $remainingRecoveryCodes }}</strong> dari 8 kode.
                </p>

                <form method="POST" action="{{ route('account.mfa.recovery') }}" style="margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--line)">
                    @csrf
                    <div style="margin-bottom:12px">
                        <label for="mfa-regen-password" class="form-label">Buat Ulang Kode Pemulihan</label>
                        <input id="mfa-regen-password" type="password" name="kata_sandi_saat_ini" class="form-control" required autocomplete="current-password" placeholder="Masukkan kata sandi saat ini">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-rotate"></i> Buat Ulang 8 Kode Pemulihan
                    </button>
                </form>

                @if(!$mfaEnforced)
                    <form method="POST" action="{{ route('account.mfa.disable') }}">
                        @csrf
                        @method('DELETE')
                        <div style="margin-bottom:12px">
                            <label for="mfa-disable-password" class="form-label">Nonaktifkan MFA</label>
                            <input id="mfa-disable-password" type="password" name="kata_sandi_saat_ini" class="form-control" required autocomplete="current-password" placeholder="Masukkan kata sandi saat ini">
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fas fa-shield-xmark"></i> Nonaktifkan MFA
                        </button>
                    </form>
                @else
                    <div style="font-size:12.5px;color:var(--muted)">
                        MFA bersifat <strong>wajib</strong> untuk peran Anda. Jika perangkat authenticator hilang, gunakan kode pemulihan atau minta reset darurat via CLI <code>php artisan mfa:reset</code>.
                    </div>
                @endif
            @else
                <p style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:16px">
                    1. Buka aplikasi <strong>Google Authenticator</strong>, <strong>Authy</strong>, atau <strong>Microsoft Authenticator</strong>.<br>
                    2. Pindai QR Code di bawah atau masukkan kode rahasia secara manual.<br>
                    3. Masukkan kata sandi saat ini dan kode 6 digit yang muncul di aplikasi untuk mengaktifkan MFA.
                </p>

                @if($qrSvg)
                    <div style="text-align:center;margin-bottom:16px">
                        <img src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" alt="QR Code MFA TOTP" style="width:200px;height:200px;border:1px solid var(--line);border-radius:12px;padding:8px;background:#fff">
                    </div>
                @endif

                @if($pendingSecret)
                    <div style="background:#F8FAFC;border:1px solid var(--line);border-radius:8px;padding:10px 12px;text-align:center;margin-bottom:18px">
                        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Kode Rahasia Manual (Base32)</div>
                        <code style="font-size:14px;font-weight:700;letter-spacing:1.5px">{{ $pendingSecret }}</code>
                    </div>
                @endif

                <form method="POST" action="{{ route('account.mfa.enable') }}">
                    @csrf
                    <div style="margin-bottom:14px">
                        <label for="mfa-enable-password" class="form-label">Kata Sandi Saat Ini</label>
                        <input id="mfa-enable-password" type="password" name="kata_sandi_saat_ini" class="form-control @error('kata_sandi_saat_ini') is-invalid @enderror" required autocomplete="current-password">
                        @error('kata_sandi_saat_ini')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div style="margin-bottom:16px">
                        <label for="mfa-enable-code" class="form-label">Kode OTP 6 Digit</label>
                        <input id="mfa-enable-code" type="text" name="code" class="form-control @error('code') is-invalid @enderror" required autocomplete="one-time-code" placeholder="000000" maxlength="16">
                        @error('code')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Verifikasi &amp; Aktifkan MFA
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-circle-info" style="margin-right:8px;color:var(--gold)"></i>Panduan Keamanan</h3>
        </div>
        <div class="card-body">
            <ul style="font-size:13px;color:var(--muted);line-height:1.7;padding-left:18px;margin-bottom:16px">
                <li>Kode OTP berubah otomatis setiap 30 detik dan hanya dapat dipakai satu kali.</li>
                <li>Simpan 8 kode pemulihan di pengelola sandi atau cetak di tempat aman.</li>
                <li>Setiap perubahan status MFA otomatis mencabut sesi perangkat lain demi keamanan akun Anda.</li>
            </ul>
            <a href="{{ route('account.profile.show') }}" class="btn btn-ghost btn-sm">
                <i class="fas fa-arrow-left"></i> Kembali ke Profil Akun
            </a>
        </div>
    </div>
</div>
@endsection
