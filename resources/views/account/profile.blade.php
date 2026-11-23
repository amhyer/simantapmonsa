@extends('layouts.app')

@section('title', 'Profil & Keamanan Akun')
@section('page_title', 'Profil & Keamanan Akun')
@section('page_subtitle', 'Kelola identitas akun, email pemulihan, dan ubah kata sandi mandiri')

@section('sidebar')
    @if(!empty($sidebarPartial) && View::exists($sidebarPartial))
        @include($sidebarPartial)
    @endif
@endsection

@section('content')
<div class="grid grid-2" style="align-items:start">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-circle" style="margin-right:8px;color:var(--navy)"></i>Identitas Akun</h3>
            <span class="tag tag-primary">{{ labelPeran($user->peran) }}</span>
        </div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--line)">
                <div class="avatar avatar-lg" style="background:var(--navy);color:#fff">
                    {{ strtoupper(substr($user->nama_lengkap ?? 'U', 0, 2)) }}
                </div>
                <div>
                    <div style="font-size:16px;font-weight:700;color:var(--text)">{{ $user->nama_lengkap }}</div>
                    <div style="font-size:13px;color:var(--muted)">Nama pengguna: <code>{{ $user->nama_pengguna }}</code></div>
                    @if($user->terakhir_masuk)
                        <div style="font-size:12px;color:var(--muted);margin-top:2px">
                            Terakhir masuk: {{ $user->terakhir_masuk->format('d/m/Y H:i') }}
                        </div>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('account.profile.update') }}">
                @csrf
                @method('PUT')

                <div style="margin-bottom:14px">
                    <label for="akun-email" class="form-label">Email Pemulihan (Opsional)</label>
                    <input
                        id="akun-email"
                        type="email"
                        name="email"
                        value="{{ old('email', $user->email) }}"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="nama@contoh.sch.id"
                        autocomplete="email"
                    >
                    @error('email')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    <div style="font-size:12px;color:var(--muted);margin-top:4px">
                        Digunakan untuk fitur Lupa Kata Sandi mandiri bila suatu saat Anda lupa sandi.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Profil
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-lock" style="margin-right:8px;color:var(--gold)"></i>Ubah Kata Sandi</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('account.profile.password') }}">
                @csrf
                @method('PUT')

                <div style="margin-bottom:14px">
                    <label for="kata-sandi-saat-ini" class="form-label">Kata Sandi Saat Ini</label>
                    <input
                        id="kata-sandi-saat-ini"
                        type="password"
                        name="kata_sandi_saat_ini"
                        class="form-control @error('kata_sandi_saat_ini') is-invalid @enderror"
                        autocomplete="current-password"
                        required
                    >
                    @error('kata_sandi_saat_ini')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div style="margin-bottom:14px">
                    <label for="password-baru" class="form-label">Kata Sandi Baru</label>
                    <input
                        id="password-baru"
                        type="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    @error('password')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    <div style="font-size:12px;color:var(--muted);margin-top:4px">
                        Minimal 8 karakter (gunakan kombinasi huruf dan angka).
                    </div>
                </div>

                <div style="margin-bottom:18px">
                    <label for="password-baru-konfirmasi" class="form-label">Konfirmasi Kata Sandi Baru</label>
                    <input
                        id="password-baru-konfirmasi"
                        type="password"
                        name="password_confirmation"
                        class="form-control"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> Perbarui Kata Sandi
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
