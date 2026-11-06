@extends('layouts.app')

@section('title', 'Katalog Sekolah (Tenant) - SIMANTAP Super Admin')

@section('sidebar')
    @include('superadmin.partials.sidebar')
@endsection

@section('page_title', 'Katalog Sekolah Terdaftar')
@section('page_subtitle', 'Kelola semua tenant sekolah, perizinan, dan status aktifasi')

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
        <h3 style="margin:0"><i class="fas fa-plus-circle" style="margin-right:8px;color:#4338CA"></i>Daftarkan Sekolah Baru Secara Manual</h3>
        <span class="btn btn-sm btn-ghost" style="pointer-events:none">Buka / Tutup Form</span>
    </summary>
    <div class="card-body">
        <form method="POST" action="{{ route('superadmin.tenants.store') }}">
            @csrf
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
                    <label class="form-label">Subdomain Tenant <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="subdomain" class="form-control @error('subdomain') is-invalid @enderror" value="{{ old('subdomain') }}" required placeholder="Contoh: smpn1makassar">
                    <small style="color:var(--muted)">Akan diakses di <code>subdomain.domain.com</code></small>
                    @error('subdomain') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div style="font-weight:700;font-size:14px;color:#4338CA;margin-bottom:12px;padding-top:10px;border-top:1px solid #E4E7EC">
                <i class="fas fa-user-shield"></i> Akun Administrator Pertama Sekolah
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
                    <i class="fas fa-save"></i> Buat Sekolah &amp; Akun Admin
                </button>
            </div>
        </form>
    </div>
</details>

{{-- Search & Filter Bar --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px">
        <form method="GET" action="{{ route('superadmin.tenants.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:240px">
                <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Cari nama sekolah, NPSN, atau subdomain...">
            </div>
            <div style="min-width:160px">
                <select name="status" class="form-control">
                    <option value="">Semua Status</option>
                    <option value="1" {{ $status === '1' ? 'selected' : '' }}>Hanya yang Aktif</option>
                    <option value="0" {{ $status === '0' ? 'selected' : '' }}>Hanya yang Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm" style="background:#4338CA;color:#fff">
                <i class="fas fa-search"></i> Filter
            </button>
            @if($search !== '' || $status !== null)
                <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

{{-- Tabel Data Tenants --}}
<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <h3><i class="fas fa-school" style="margin-right:8px;color:#4338CA"></i>Daftar Sekolah Terdaftar</h3>
        <span class="tag tag-mut">{{ $tenants->total() }} total sekolah</span>
    </div>
    <div class="card-body tight">
        @if($tenants->count() > 0)
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:11px 14px;text-align:center;width:50px;color:#667085">No</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">NPSN</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">Nama Sekolah</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">Subdomain</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">Admin Utama</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Siswa</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Guru</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Status</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenants as $i => $tenant)
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:11px 14px;text-align:center;color:var(--muted)">
                                    {{ $tenants->firstItem() + $i }}
                                </td>
                                <td style="padding:11px 14px;font-family:monospace;font-weight:700">
                                    {{ $tenant->npsn }}
                                </td>
                                <td style="padding:11px 14px;font-weight:700">
                                    <a href="{{ route('superadmin.tenants.show', $tenant->uuid) }}" style="color:#4338CA;text-decoration:none">
                                        {{ $tenant->nama_sekolah }}
                                    </a>
                                </td>
                                <td style="padding:11px 14px">
                                    <span style="background:#EEF2FF;color:#4338CA;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:600">
                                        {{ $tenant->subdomain }}
                                    </span>
                                </td>
                                <td style="padding:11px 14px">
                                    @php $adm = $adminUsers->get($tenant->id); @endphp
                                    @if($adm)
                                        <b>{{ $adm->nama_lengkap }}</b>
                                        <div style="font-size:11px;color:var(--muted)">{{ $adm->nama_pengguna }}</div>
                                    @else
                                        <span style="color:var(--muted)">Belum ada</span>
                                    @endif
                                </td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">
                                    {{ $siswaCounts->get($tenant->id, 0) }}
                                </td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">
                                    {{ $guruCounts->get($tenant->id, 0) }}
                                </td>
                                <td style="padding:11px 14px;text-align:center">
                                    @if($tenant->aktif)
                                        <span class="tag tag-ok">Aktif</span>
                                    @else
                                        <span class="tag tag-bad">Nonaktif</span>
                                    @endif
                                </td>
                                <td style="padding:11px 14px;text-align:center">
                                    <div style="display:flex;gap:6px;justify-content:center">
                                        <a href="{{ route('superadmin.tenants.show', $tenant->uuid) }}" class="btn btn-ghost btn-sm">
                                            Detail
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
                <p>Coba gunakan kata kunci pencarian yang lain.</p>
            </div>
        @endif
    </div>
</div>
@endsection
