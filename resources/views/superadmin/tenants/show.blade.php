@extends('layouts.app')

@section('title', 'Kelola Sekolah ' . $tenant->nama_sekolah . ' - SIMANTAP Super Admin')

@section('sidebar')
    @include('superadmin.partials.sidebar')
@endsection

@section('page_title', $tenant->nama_sekolah)
@section('page_subtitle', 'NPSN: ' . $tenant->npsn . ' • Subdomain: ' . $tenant->subdomain . ($tenant->domain_kustom ? ' • Domain: ' . $tenant->domain_kustom : ''))

@section('header_actions')
    <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-sm btn-ghost">
        &larr; Kembali ke Katalog
    </a>
    @if($admin)
        <form method="POST" action="{{ route('superadmin.tenants.impersonate', $tenant->uuid) }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-sm" style="background:#4338CA;color:#fff" title="Masuk langsung ke dasbor sekolah ini">
                <i class="fas fa-sign-in-alt"></i> Masuk sebagai Admin Sekolah
            </button>
        </form>
    @endif
    <form method="POST" action="{{ route('superadmin.tenants.toggle', $tenant->uuid) }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-sm {{ $tenant->aktif ? 'btn-warn' : 'btn-primary' }}" onclick="return confirm('Ubah status akses sekolah ini?')">
            {{ $tenant->aktif ? 'Nonaktifkan Akses' : 'Aktifkan Akses' }}
        </button>
    </form>
@endsection

@section('content')
<style>
    :root {
        --navy: #4338CA;
        --navy-light: #6366F1;
        --navy-dark: #312E81;
    }
</style>

@php
    $kuotaSiswa = $tenant->kuota_siswa ?: 500;
    $persenSiswa = $kuotaSiswa > 0 ? round(($totalSiswa / $kuotaSiswa) * 100) : 0;
    $sisaHari = $tenant->sisaHariLangganan();
@endphp

{{-- Kartu Ringkasan Status Resource & Langganan --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card" style="border-left:4px solid #4338CA">
        <i class="fas fa-cubes stat-icon" style="color:#4338CA"></i>
        <div class="stat-label">Paket Layanan SaaS</div>
        <div class="stat-value" style="color:#4338CA;font-size:20px;text-transform:uppercase">{{ $tenant->paket ?? 'STANDAR' }}</div>
        <div class="stat-change">{{ $tenant->labelPaket() }}</div>
    </div>
    <div class="stat-card {{ $persenSiswa > 90 ? 'bad' : ($persenSiswa > 75 ? 'gold' : 'ok') }}">
        <i class="fas fa-user-graduate stat-icon"></i>
        <div class="stat-label">Penggunaan Kuota Siswa</div>
        <div class="stat-value">{{ number_format($totalSiswa) }} <span style="font-size:14px;color:var(--muted)">/ {{ number_format($kuotaSiswa) }}</span></div>
        <div class="stat-change">{{ $persenSiswa }}% kapasitas terpakai</div>
    </div>
    <div class="stat-card {{ $sisaHari === null ? 'ok' : ($sisaHari < 0 ? 'bad' : ($sisaHari <= 30 ? 'gold' : 'ok')) }}">
        <i class="fas fa-calendar-check stat-icon"></i>
        <div class="stat-label">Masa Aktif Langganan</div>
        <div class="stat-value" style="font-size:18px">
            @if($sisaHari === null)
                UNLIMITED
            @elseif($sisaHari < 0)
                EXPIRED
            @else
                {{ $sisaHari }} HARI
            @endif
        </div>
        <div class="stat-change">
            @if($tenant->tgl_kedaluwarsa)
                Jatuh tempo: {{ $tenant->tgl_kedaluwarsa->translatedFormat('d M Y') }}
            @else
                Tanpa batas kedaluwarsa
            @endif
        </div>
    </div>
    <div class="stat-card {{ $tenant->aktif ? 'ok' : 'bad' }}">
        <i class="fas fa-shield-alt stat-icon"></i>
        <div class="stat-label">Status Akses Platform</div>
        <div class="stat-value" style="font-size:18px">{{ $tenant->aktif ? 'AKTIF' : 'DIBLOKIR' }}</div>
        <div class="stat-change">{{ $tenant->aktif ? 'Akses sistem terbuka normal' : 'Pengguna tidak dapat login' }}</div>
    </div>
</div>

{{-- Alert Peringatan Jika Langganan Habis --}}
@if($tenant->isExpired())
    <div class="alert alert-danger" style="margin-bottom:20px;background:#FEF2F2;border:1px solid #F87171;color:#991B1B;padding:14px 18px;border-radius:8px">
        <div style="display:flex;align-items:center;gap:12px">
            <i class="fas fa-exclamation-triangle" style="font-size:22px"></i>
            <div>
                <b>Perhatian: Masa Langganan Sekolah Telah Berakhir!</b>
                <div>Pengguna dari sekolah ini akan menerima pesan pembatasan akses (402 Payment Required) hingga Super Admin memperbarui masa aktif langganan di bawah.</div>
            </div>
        </div>
    </div>
@elseif($sisaHari !== null && $sisaHari <= 30)
    <div class="alert alert-warning" style="margin-bottom:20px;background:#FFFBEB;border:1px solid #FCD34D;color:#92400E;padding:14px 18px;border-radius:8px">
        <div style="display:flex;align-items:center;gap:12px">
            <i class="fas fa-clock" style="font-size:22px"></i>
            <div>
                <b>Peringatan: Masa langganan tersisa {{ $sisaHari }} hari lagi.</b> Segera hubungi pihak sekolah terkait perpanjangan biaya hosting atau domain.
            </div>
        </div>
    </div>
@endif

<div class="grid grid-2" style="margin-bottom:20px">
    {{-- Form Pengaturan Paket, Kuota & Domain Kustom --}}
    <div class="card">
        <div class="card-header" style="justify-content:space-between">
            <h3><i class="fas fa-sliders-h" style="margin-right:8px;color:#4338CA"></i>Pengaturan Paket, Kuota &amp; Domain</h3>
            <span class="tag" style="background:#EEF2FF;color:#4338CA;font-weight:700">Kontrol Pemilik</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.tenants.update', $tenant->uuid) }}">
                @csrf
                @method('PUT')

                <div class="form-group" style="margin-bottom:14px">
                    <label class="form-label">Nama Sekolah Resmi <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="nama_sekolah" class="form-control" value="{{ old('nama_sekolah', $tenant->nama_sekolah) }}" required>
                </div>

                <div class="grid grid-2" style="gap:12px;margin-bottom:14px">
                    <div class="form-group">
                        <label class="form-label">Tingkat Paket SaaS <span style="color:var(--bad)">*</span></label>
                        <select name="paket" class="form-control">
                            <option value="standar" {{ old('paket', $tenant->paket) === 'standar' ? 'selected' : '' }}>Standar (Shared Cloud)</option>
                            <option value="pro" {{ old('paket', $tenant->paket) === 'pro' ? 'selected' : '' }}>Pro (Custom Domain Sendiri)</option>
                            <option value="enterprise" {{ old('paket', $tenant->paket) === 'enterprise' ? 'selected' : '' }}>Enterprise (Dedicated Hosting/DB)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Domain Kustom Sekolah</label>
                        <input type="text" name="domain_kustom" class="form-control @error('domain_kustom') is-invalid @enderror" value="{{ old('domain_kustom', $tenant->domain_kustom) }}" placeholder="cbt.smpn1.sch.id">
                        @error('domain_kustom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                @if($tenant->domain_kustom)
                    <div style="background:#F0FDF4;border:1px solid #BBF7D0;padding:10px 12px;border-radius:6px;font-size:12px;color:#166534;margin-bottom:14px">
                        <i class="fas fa-info-circle"></i> <b>Petunjuk DNS Sekolah:</b> Pihak sekolah wajib mengarahkan CNAME domain <code>{{ $tenant->domain_kustom }}</code> ke host server SIMANTAP.
                    </div>
                @endif

                <div class="grid grid-2" style="gap:12px;margin-bottom:14px">
                    <div class="form-group">
                        <label class="form-label">Batas Kuota Siswa (Akun)</label>
                        <input type="number" name="kuota_siswa" class="form-control" value="{{ old('kuota_siswa', $tenant->kuota_siswa ?: 500) }}" min="10" max="100000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batas Kuota File/Storage (MB)</label>
                        <input type="number" name="kuota_storage_mb" class="form-control" value="{{ old('kuota_storage_mb', $tenant->kuota_storage_mb ?: 2048) }}" min="100" max="1048576" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px">
                    <label class="form-label">Tanggal Kedaluwarsa Langganan</label>
                    <input type="date" name="tgl_kedaluwarsa" id="inputTglKedaluwarsa" class="form-control" value="{{ old('tgl_kedaluwarsa', $tenant->tgl_kedaluwarsa ? $tenant->tgl_kedaluwarsa->toDateString() : '') }}">
                    <div style="display:flex;gap:8px;margin-top:6px">
                        <button type="button" class="btn btn-xs btn-ghost" onclick="setPerpanjang(6)">+ 6 Bulan</button>
                        <button type="button" class="btn btn-xs btn-ghost" onclick="setPerpanjang(12)">+ 1 Tahun</button>
                        <button type="button" class="btn btn-xs btn-ghost" onclick="document.getElementById('inputTglKedaluwarsa').value = ''">Kosongkan (Unlimited)</button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Catatan Langganan &amp; Pembayaran Hosting</label>
                    <textarea name="catatan_langganan" class="form-control" rows="2" placeholder="Contoh: Pembayaran hosting tahun 2026 lunas via transfer Bank BPD">{{ old('catatan_langganan', $tenant->catatan_langganan) }}</textarea>
                </div>

                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-sm" style="background:#4338CA;color:#fff">
                        <i class="fas fa-save"></i> Simpan Perubahan Paket
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ringkasan Beban Resource & Aktivitas Database --}}
    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-server" style="margin-right:8px;color:#4338CA"></i>Beban Resource Ujian &amp; Database</h3>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table style="width:100%;font-size:13px;line-height:2">
                        <tr>
                            <td style="color:var(--muted)">Bank Soal CBT</td>
                            <td><b>{{ number_format($cbtBankCount) }}</b> paket soal</td>
                        </tr>
                        <tr>
                            <td style="color:var(--muted)">Sesi Ujian Aktif &amp; Riwayat</td>
                            <td><b>{{ number_format($cbtSessionCount) }}</b> sesi ujian</td>
                        </tr>
                        <tr>
                            <td style="color:var(--muted)">Hasil Ujian Tersimpan</td>
                            <td><b>{{ number_format($cbtResultCount) }}</b> riwayat nilai siswa</td>
                        </tr>
                        <tr>
                            <td style="color:var(--muted)">Total Akun Guru &amp; Pegawai</td>
                            <td><b>{{ number_format($totalGuru + $totalKepsek + $totalTendik) }}</b> pengguna</td>
                        </tr>
                        <tr>
                            <td style="color:var(--muted)">Alamat Subdomain Bawaan</td>
                            <td><code>{{ $tenant->subdomain }}.simantap.id</code></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Administrator Utama --}}
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-shield" style="margin-right:8px;color:#B8860B"></i>Administrator Utama Sekolah</h3>
            </div>
            <div class="card-body">
                @if($admin)
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
                        <div style="width:44px;height:44px;border-radius:50%;background:#4338CA;color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700">
                            {{ substr($admin->nama_lengkap, 0, 2) }}
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:14px">{{ $admin->nama_lengkap }}</div>
                            <div style="font-size:12px;color:var(--muted)">Username: <b>{{ $admin->nama_pengguna }}</b></div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table style="width:100%;font-size:12.5px;line-height:1.8">
                            <tr>
                                <td style="width:110px;color:var(--muted)">Email</td>
                                <td>{{ $admin->email ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td style="color:var(--muted)">Status Akun</td>
                                <td>
                                    @if($admin->aktif)
                                        <span class="tag tag-ok">Aktif</span>
                                    @else
                                        <span class="tag tag-bad">Nonaktif</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #E4E7EC">
                        <form method="POST" action="{{ route('superadmin.tenants.impersonate', $tenant->uuid) }}" style="margin-bottom:10px">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="background:#4338CA;color:#fff;width:100%">
                                <i class="fas fa-sign-in-alt"></i> Masuk sebagai Admin Ini
                            </button>
                        </form>

                        <details style="font-size:12.5px">
                            <summary style="cursor:pointer;color:#4338CA;font-weight:600">
                                <i class="fas fa-key"></i> Reset Sandi Akun Admin Sekolah
                            </summary>
                            <form method="POST" action="{{ route('superadmin.tenants.reset-admin-password', $tenant->uuid) }}" style="margin-top:10px">
                                @csrf
                                <div class="form-group" style="margin-bottom:8px">
                                    <input type="password" name="password_baru" class="form-control form-control-sm" placeholder="Password baru (min 8 karakter)" required>
                                </div>
                                <button type="submit" class="btn btn-xs btn-warn" onclick="return confirm('Reset kata sandi admin ini?')">
                                    Simpan Password Baru
                                </button>
                            </form>
                        </details>
                    </div>
                @else
                    <div class="empty-state" style="padding:16px">
                        <p>Belum ada akun administrator yang terdaftar pada sekolah ini.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Pengguna Terbaru di Sekolah Ini --}}
<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <h3><i class="fas fa-users" style="margin-right:8px;color:#4338CA"></i>Akun Pengguna Terdaftar ({{ $recentUsers->count() }} Terbaru)</h3>
        <a href="{{ route('superadmin.users.index') }}?q={{ urlencode($tenant->nama_sekolah) }}" class="btn btn-ghost btn-sm">Lihat Semua Pengguna</a>
    </div>
    <div class="card-body tight">
        @if($recentUsers->count() > 0)
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 14px;text-align:left;color:#667085">Nama Lengkap</th>
                            <th style="padding:10px 14px;text-align:left;color:#667085">Username</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Peran</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Status</th>
                            <th style="padding:10px 14px;text-align:right;color:#667085">Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentUsers as $u)
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:10px 14px;font-weight:600">{{ $u->nama_lengkap }}</td>
                                <td style="padding:10px 14px;font-family:monospace">{{ $u->nama_pengguna }}</td>
                                <td style="padding:10px 14px;text-align:center">
                                    <span class="tag" style="background:#F2F4F7;text-transform:uppercase;font-size:10.5px">
                                        {{ $u->peran }}
                                    </span>
                                </td>
                                <td style="padding:10px 14px;text-align:center">
                                    @if($u->aktif)
                                        <span class="tag tag-ok">Aktif</span>
                                    @else
                                        <span class="tag tag-bad">Nonaktif</span>
                                    @endif
                                </td>
                                <td style="padding:10px 14px;text-align:right;color:var(--muted)">
                                    {{ $u->created_at ? $u->created_at->diffForHumans() : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state" style="padding:24px">
                <p>Belum ada akun lain di sekolah ini.</p>
            </div>
        @endif
    </div>
</div>

<script>
function setPerpanjang(months) {
    const d = new Date();
    d.setMonth(d.getMonth() + months);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    document.getElementById('inputTglKedaluwarsa').value = `${yyyy}-${mm}-${dd}`;
}
</script>
@endsection
