@extends('layouts.app')

@section('title', 'Dasbor Super Admin - SIMANTAP')

@section('sidebar')
    @include('superadmin.partials.sidebar')
@endsection

@section('page_title', 'Pusat Kontrol Multi-Sekolah')
@section('page_subtitle', 'Monitoring dan tata kelola platform SIMANTAP Makassar Ujian')

@section('header_actions')
    <a href="{{ route('superadmin.tenants.index') }}#tambah" class="btn btn-sm" style="background:#4338CA;color:#fff">
        <i class="fas fa-plus"></i> Tambah Sekolah Baru
    </a>
@endsection

@section('content')
<style>
    :root {
        --navy: #4338CA;
        --navy-light: #6366F1;
        --navy-dark: #312E81;
    }
    .stat-card.indigo { border-left: 4px solid #4338CA; }
    .stat-card.indigo .stat-icon { color: #4338CA; }
</style>

{{-- Banner Pusat --}}
<div style="background:linear-gradient(135deg, #312E81 0%, #4338CA 100%);color:#fff;border-radius:12px;padding:20px 24px;margin-bottom:20px;box-shadow:0 4px 16px rgba(67,56,202,0.2)">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div style="display:flex;align-items:center;gap:16px">
            <div style="width:52px;height:52px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div>
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.8px;opacity:0.85">Otoritas Pusat Platform &bull; Super Administrator</div>
                <h2 style="font-size:20px;font-weight:800;margin:2px 0 4px 0;color:#fff">Pusat Layanan Multi-Tenant SIMANTAP</h2>
                <div style="font-size:13px;opacity:0.9">Mengawasi seluruh sekolah, isolasi data, dan keandalan ujian serentak.</div>
            </div>
        </div>
        <div>
            <span class="tag" style="background:rgba(255,255,255,0.2);color:#fff;font-size:13px;padding:6px 14px;border-radius:999px">
                <i class="fas fa-circle" style="color:#34D399;font-size:8px;margin-right:6px"></i> Multi-Tenant Engine Aktif
            </span>
        </div>
    </div>
</div>

{{-- Top Statistics Cards --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card indigo">
        <i class="fas fa-school stat-icon"></i>
        <div class="stat-label">Total Sekolah</div>
        <div class="stat-value" style="color:#4338CA">{{ number_format($totalTenants) }}</div>
        <div class="stat-change">{{ $activeTenants }} sekolah aktif</div>
    </div>
    <div class="stat-card ok">
        <i class="fas fa-user-graduate stat-icon"></i>
        <div class="stat-label">Total Siswa Kota/Platform</div>
        <div class="stat-value">{{ number_format($totalSiswa) }}</div>
        <div class="stat-change">di seluruh tenant</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-chalkboard-teacher stat-icon"></i>
        <div class="stat-label">Guru &amp; PTK</div>
        <div class="stat-value">{{ number_format($totalGuru) }}</div>
        <div class="stat-change">terdaftar aktif</div>
    </div>
    <div class="stat-card primary">
        <i class="fas fa-users-cog stat-icon"></i>
        <div class="stat-label">Total Akun Sekolah</div>
        <div class="stat-value">{{ number_format($totalUsers) }}</div>
        <div class="stat-change">guru, siswa, ortu, tendik</div>
    </div>
</div>

{{-- Tabel Sekolah Terbaru --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header" style="justify-content:space-between">
        <h3><i class="fas fa-building" style="margin-right:8px;color:#4338CA"></i>Sekolah Terdaftar Terbaru</h3>
        <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-ghost btn-sm">Lihat Semua Sekolah &rarr;</a>
    </div>
    <div class="card-body tight">
        @if($tenantsWithStats->count() > 0)
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 14px;text-align:left;color:#667085">NPSN</th>
                            <th style="padding:10px 14px;text-align:left;color:#667085">Nama Sekolah</th>
                            <th style="padding:10px 14px;text-align:left;color:#667085">Subdomain</th>
                            <th style="padding:10px 14px;text-align:left;color:#667085">Admin Utama</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Siswa</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Guru</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Status</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenantsWithStats as $item)
                            @php $t = $item['tenant']; @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:10px 14px;font-family:monospace;font-weight:700">{{ $t->npsn }}</td>
                                <td style="padding:10px 14px;font-weight:600">{{ $t->nama_sekolah }}</td>
                                <td style="padding:10px 14px">
                                    <span style="background:#EEF2FF;color:#4338CA;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:600">
                                        {{ $t->subdomain }}.simantap
                                    </span>
                                </td>
                                <td style="padding:10px 14px">
                                    {{ $item['admin']?->nama_lengkap ?? '—' }}
                                    <div style="font-size:11px;color:var(--muted)">{{ $item['admin']?->nama_pengguna }}</div>
                                </td>
                                <td style="padding:10px 14px;text-align:center;font-weight:600">{{ $item['total_siswa'] }}</td>
                                <td style="padding:10px 14px;text-align:center;font-weight:600">{{ $item['total_guru'] }}</td>
                                <td style="padding:10px 14px;text-align:center">
                                    @if($t->aktif)
                                        <span class="tag tag-ok">Aktif</span>
                                    @else
                                        <span class="tag tag-bad">Nonaktif</span>
                                    @endif
                                </td>
                                <td style="padding:10px 14px;text-align:center">
                                    <div style="display:flex;gap:6px;justify-content:center">
                                        <a href="{{ route('superadmin.tenants.show', $t->uuid) }}" class="btn btn-ghost btn-sm" title="Detail Sekolah">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        <form method="POST" action="{{ route('superadmin.tenants.toggle', $t->uuid) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs {{ $t->aktif ? 'btn-ghost' : 'btn-primary' }}" onclick="return confirm('Ubah status aktif sekolah ini?')">
                                                {{ $t->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state" style="padding:40px">
                <i class="fas fa-school" style="font-size:48px;opacity:.2;margin-bottom:12px"></i>
                <h4>Belum ada sekolah terdaftar</h4>
                <p>Sekolah yang mendaftar melalui form pendaftaran mandiri atau dibuat via CLI akan tampil di sini.</p>
            </div>
        @endif
    </div>
</div>
@endsection
