@extends('layouts.app')

@section('title', 'Katalog Sekolah (Tenant) - SIMANTAP Super Admin')

@section('sidebar')
    @include('superadmin.partials.sidebar')
@endsection

@section('page_title', 'Katalog Sekolah Terdaftar')
@section('page_subtitle', 'Kelola semua tenant sekolah, paket langganan, kuota, dan custom domain')

@section('content')
<style>
    :root {
        --navy: #4338CA;
        --navy-light: #6366F1;
        --navy-dark: #312E81;
    }
</style>

{{-- Form Tambah Sekolah Baru (Collapsible) --}}
<details class="card" id="tambah" style="margin-bottom:20px" {{ $errors->any() ? 'open' : '' }}>
    <summary class="card-header" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between">
        <h3 style="margin:0"><i class="fas fa-plus-circle" style="margin-right:8px;color:#4338CA"></i>Daftarkan Sekolah Baru &amp; Atur Paket</h3>
        <span class="btn btn-sm btn-ghost" style="pointer-events:none">Buka / Tutup Form</span>
    </summary>
    <div class="card-body">
        <form method="POST" action="{{ route('superadmin.tenants.store') }}">
            @csrf
            <div style="font-weight:700;font-size:14px;color:#4338CA;margin-bottom:12px">
                <i class="fas fa-school"></i> 1. Identitas &amp; Domain Sekolah
            </div>
            <div class="grid grid-3" style="gap:16px;margin-bottom:16px">
                <div class="form-group">
                    <label class="form-label">NPSN Resmi <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="npsn" class="form-control @error('npsn') is-invalid @enderror" value="{{ old('npsn') }}" required placeholder="Contoh: 40307000">
                    @error('npsn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Sekolah <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="nama_sekolah" class="form-control @error('nama_sekolah') is-invalid @enderror" value="{{ old('nama_sekolah') }}" required placeholder="Contoh: SMP Negeri 1 Makassar">
                    @error('nama_sekolah') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Subdomain Bawaan <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="subdomain" class="form-control @error('subdomain') is-invalid @enderror" value="{{ old('subdomain') }}" required placeholder="Contoh: smpn1makassar">
                    <small style="color:var(--muted)">Akan diakses di <code>subdomain.domain.com</code></small>
                    @error('subdomain') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div style="font-weight:700;font-size:14px;color:#4338CA;margin-bottom:12px;padding-top:10px;border-top:1px solid #E4E7EC">
                <i class="fas fa-cubes"></i> 2. Paket Layanan, Kuota &amp; Custom Domain
            </div>
            <div class="grid grid-4" style="gap:16px;margin-bottom:16px">
                <div class="form-group">
                    <label class="form-label">Tingkat Paket SaaS <span style="color:var(--bad)">*</span></label>
                    <select name="paket" class="form-control">
                        <option value="standar" {{ old('paket', 'standar') === 'standar' ? 'selected' : '' }}>Standar (Shared Cloud)</option>
                        <option value="pro" {{ old('paket') === 'pro' ? 'selected' : '' }}>Pro (Custom Domain Sendiri)</option>
                        <option value="enterprise" {{ old('paket') === 'enterprise' ? 'selected' : '' }}>Enterprise (Dedicated Hosting/DB)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Domain Kustom Sekolah</label>
                    <input type="text" name="domain_kustom" class="form-control @error('domain_kustom') is-invalid @enderror" value="{{ old('domain_kustom') }}" placeholder="Contoh: ujian.smpn1.sch.id">
                    <small style="color:var(--muted)">Opsional (bila sekolah punya domain sendiri)</small>
                    @error('domain_kustom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Batas Kuota Siswa (Akun)</label>
                    <input type="number" name="kuota_siswa" class="form-control" value="{{ old('kuota_siswa', 500) }}" min="10" max="100000">
                    <small style="color:var(--muted)">Kapasitas maksimal siswa aktif</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Jatuh Tempo / Kedaluwarsa</label>
                    <input type="date" name="tgl_kedaluwarsa" class="form-control" value="{{ old('tgl_kedaluwarsa') }}">
                    <small style="color:var(--muted)">Kosongkan untuk tanpa batas waktu</small>
                </div>
            </div>

            <div style="font-weight:700;font-size:14px;color:#4338CA;margin-bottom:12px;padding-top:10px;border-top:1px solid #E4E7EC">
                <i class="fas fa-user-shield"></i> 3. Akun Administrator Utama Sekolah
            </div>
            <div class="grid grid-3" style="gap:16px;margin-bottom:16px">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap Admin <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="admin_nama" class="form-control @error('admin_nama') is-invalid @enderror" value="{{ old('admin_nama') }}" required placeholder="Contoh: Operator Sekolah">
                    @error('admin_nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Pengguna (Username) <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="admin_user" class="form-control @error('admin_user') is-invalid @enderror" value="{{ old('admin_user') }}" required placeholder="admin_smpn1">
                    @error('admin_user') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Kata Sandi Awal <span style="color:var(--bad)">*</span></label>
                    <input type="password" name="admin_password" class="form-control @error('admin_password') is-invalid @enderror" required placeholder="Minimal 8 karakter">
                    @error('admin_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="submit" class="btn btn-sm" style="background:#4338CA;color:#fff">
                    <i class="fas fa-save"></i> Daftarkan Sekolah &amp; Buat Akun
                </button>
            </div>
        </form>
    </div>
</details>

{{-- Search & Filter Bar --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px">
        <form method="GET" action="{{ route('superadmin.tenants.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:2;min-width:220px">
                <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Cari nama sekolah, NPSN, subdomain, atau domain kustom...">
            </div>
            <div style="flex:1;min-width:140px">
                <select name="paket" class="form-control">
                    <option value="">Semua Paket</option>
                    <option value="standar" {{ $paket === 'standar' ? 'selected' : '' }}>Standar (Shared)</option>
                    <option value="pro" {{ $paket === 'pro' ? 'selected' : '' }}>Pro (Custom Domain)</option>
                    <option value="enterprise" {{ $paket === 'enterprise' ? 'selected' : '' }}>Enterprise (Dedicated)</option>
                </select>
            </div>
            <div style="flex:1;min-width:140px">
                <select name="langganan" class="form-control">
                    <option value="">Semua Langganan</option>
                    <option value="aktif" {{ $langganan === 'aktif' ? 'selected' : '' }}>Masih Aktif</option>
                    <option value="warning" {{ $langganan === 'warning' ? 'selected' : '' }}>Segera Habis (&le; 30 hari)</option>
                    <option value="expired" {{ $langganan === 'expired' ? 'selected' : '' }}>Kedaluwarsa (Expired)</option>
                </select>
            </div>
            <div style="flex:1;min-width:130px">
                <select name="status" class="form-control">
                    <option value="">Semua Akses</option>
                    <option value="1" {{ $status === '1' ? 'selected' : '' }}>Akses Aktif</option>
                    <option value="0" {{ $status === '0' ? 'selected' : '' }}>Akses Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm" style="background:#4338CA;color:#fff">
                <i class="fas fa-search"></i> Filter
            </button>
            @if($search !== '' || $status !== null || $paket !== null || $langganan !== null)
                <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

{{-- Tabel Data Tenants --}}
<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <h3><i class="fas fa-school" style="margin-right:8px;color:#4338CA"></i>Daftar Sekolah &amp; Status Resource</h3>
        <span class="tag tag-mut">{{ $tenants->total() }} total sekolah</span>
    </div>
    <div class="card-body tight">
        @if($tenants->count() > 0)
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:11px 14px;text-align:center;width:40px;color:#667085">No</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">Sekolah / NPSN</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">Akses Domain &amp; Subdomain</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Paket Layanan</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Kuota Siswa</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Masa Langganan</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Status</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenants as $i => $tenant)
                            @php
                                $siswaCount = $siswaCounts->get($tenant->id, 0);
                                $kuotaSiswa = $tenant->kuota_siswa ?: 500;
                                $persenKuota = $kuotaSiswa > 0 ? round(($siswaCount / $kuotaSiswa) * 100) : 0;
                                $sisaHari = $tenant->sisaHariLangganan();
                            @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:11px 14px;text-align:center;color:var(--muted)">
                                    {{ $tenants->firstItem() + $i }}
                                </td>
                                <td style="padding:11px 14px">
                                    <div style="font-weight:700">
                                        <a href="{{ route('superadmin.tenants.show', $tenant->uuid) }}" style="color:#4338CA;text-decoration:none">
                                            {{ $tenant->nama_sekolah }}
                                        </a>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted);font-family:monospace">
                                        NPSN: {{ $tenant->npsn }}
                                    </div>
                                </td>
                                <td style="padding:11px 14px">
                                    <div>
                                        <span style="background:#EEF2FF;color:#4338CA;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:600">
                                            {{ $tenant->subdomain }}.simantap.id
                                        </span>
                                    </div>
                                    @if($tenant->domain_kustom)
                                        <div style="margin-top:4px">
                                            <span style="background:#ECFDF5;color:#047857;padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:700">
                                                <i class="fas fa-globe" style="margin-right:3px"></i>{{ $tenant->domain_kustom }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td style="padding:11px 14px;text-align:center">
                                    @if($tenant->paket === 'enterprise')
                                        <span class="tag" style="background:#F3E8FF;color:#6B21A8;font-weight:700">Enterprise</span>
                                    @elseif($tenant->paket === 'pro')
                                        <span class="tag" style="background:#E0E7FF;color:#3730A3;font-weight:700">Pro</span>
                                    @else
                                        <span class="tag" style="background:#F2F4F7;color:#344054">Standar</span>
                                    @endif
                                </td>
                                <td style="padding:11px 14px;text-align:center">
                                    <div style="font-weight:700;font-size:12.5px">
                                        {{ $siswaCount }} <span style="font-weight:400;color:var(--muted)">/ {{ $kuotaSiswa }}</span>
                                    </div>
                                    <div style="width:70px;background:#E4E7EC;border-radius:4px;height:5px;margin:4px auto 0;overflow:hidden">
                                        <div style="background:{{ $persenKuota > 90 ? '#EF4444' : ($persenKuota > 75 ? '#F59E0B' : '#4338CA') }};width:{{ min(100, $persenKuota) }}%;height:100%"></div>
                                    </div>
                                </td>
                                <td style="padding:11px 14px;text-align:center">
                                    @if($sisaHari === null)
                                        <span class="tag tag-mut" style="font-size:11px">Tanpa Batas</span>
                                    @elseif($sisaHari < 0)
                                        <span class="tag tag-bad" style="font-size:11px">Habis ({{ abs($sisaHari) }} hari lalu)</span>
                                    @elseif($sisaHari <= 30)
                                        <span class="tag tag-warn" style="font-size:11px">{{ $sisaHari }} hari lagi</span>
                                    @else
                                        <span class="tag tag-ok" style="font-size:11px">{{ $sisaHari }} hari</span>
                                    @endif
                                </td>
                                <td style="padding:11px 14px;text-align:center">
                                    @if($tenant->aktif)
                                        <span class="tag tag-ok">Aktif</span>
                                    @else
                                        <span class="tag tag-bad">Nonaktif</span>
                                    @endif
                                </td>
                                <td style="padding:11px 14px;text-align:center">
                                    <div style="display:flex;gap:6px;justify-content:center;align-items:center">
                                        <form method="POST" action="{{ route('superadmin.tenants.impersonate', $tenant->uuid) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs" style="background:#EEF2FF;color:#4338CA;font-weight:700" title="Masuk langsung ke dasbor sekolah ini">
                                                <i class="fas fa-sign-in-alt"></i> Masuk
                                            </button>
                                        </form>
                                        <a href="{{ route('superadmin.tenants.show', $tenant->uuid) }}" class="btn btn-ghost btn-sm">
                                            Kelola
                                        </a>
                                        <form method="POST" action="{{ route('superadmin.tenants.toggle', $tenant->uuid) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs {{ $tenant->aktif ? 'btn-ghost' : 'btn-primary' }}" onclick="return confirm('Ubah status aktifasi?')">
                                                {{ $tenant->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:16px">
                {{ $tenants->links() }}
            </div>
        @else
            <div class="empty-state" style="padding:32px">
                <h4>Tidak ada data sekolah ditemukan</h4>
                <p>Coba gunakan kata kunci pencarian atau filter yang lain.</p>
            </div>
        @endif
    </div>
</div>
@endsection
