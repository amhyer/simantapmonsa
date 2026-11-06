@extends('layouts.app')

@section('title', 'Detail Sekolah ' . $tenant->nama_sekolah . ' - SIMANTAP Super Admin')

@section('sidebar')
    @include('superadmin.partials.sidebar')
@endsection

@section('page_title', $tenant->nama_sekolah)
@section('page_subtitle', 'NPSN: ' . $tenant->npsn . ' • Subdomain: ' . $tenant->subdomain)

@section('header_actions')
    <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-sm btn-ghost">
        &larr; Kembali ke Katalog
    </a>
    <form method="POST" action="{{ route('superadmin.tenants.toggle', $tenant->uuid) }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-sm {{ $tenant->aktif ? 'btn-warn' : 'btn-primary' }}" onclick="return confirm('Ubah status aktifasi sekolah ini?')">
            {{ $tenant->aktif ? 'Nonaktifkan Sekolah' : 'Aktifkan Sekolah' }}
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

{{-- Info Ringkasan Sekolah --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card" style="border-left:4px solid #4338CA">
        <i class="fas fa-user-graduate stat-icon" style="color:#4338CA"></i>
        <div class="stat-label">Total Siswa</div>
        <div class="stat-value" style="color:#4338CA">{{ number_format($totalSiswa) }}</div>
        <div class="stat-change">terdaftar di sekolah ini</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-chalkboard-teacher stat-icon"></i>
        <div class="stat-label">Guru &amp; PTK</div>
        <div class="stat-value">{{ number_format($totalGuru) }}</div>
        <div class="stat-change">akun pengajar aktif</div>
    </div>
    <div class="stat-card primary">
        <i class="fas fa-user-tie stat-icon"></i>
        <div class="stat-label">Kepala Sekolah &amp; Tendik</div>
        <div class="stat-value">{{ number_format($totalKepsek + $totalTendik) }}</div>
        <div class="stat-change">{{ $totalKepsek }} kepsek &bull; {{ $totalTendik }} tendik</div>
    </div>
    <div class="stat-card {{ $tenant->aktif ? 'ok' : 'bad' }}">
        <i class="fas fa-power-off stat-icon"></i>
        <div class="stat-label">Status Lisensi Platform</div>
        <div class="stat-value" style="font-size:18px">{{ $tenant->aktif ? 'AKSES AKTIF' : 'NONAKTIF' }}</div>
        <div class="stat-change">{{ $tenant->aktif ? 'Dapat login dan ujian' : 'Akses ditangguhkan' }}</div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom:20px">
    {{-- Profil Identitas Tenant --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-building" style="margin-right:8px;color:#4338CA"></i>Data Identitas Sekolah</h3>
        </div>
        <div class="card-body">
            <table style="width:100%;font-size:13.5px;line-height:2">
                <tr>
                    <td style="width:140px;color:var(--muted)">Nama Sekolah</td>
                    <td><b>{{ $tenant->nama_sekolah }}</b></td>
                </tr>
                <tr>
                    <td style="color:var(--muted)">NPSN</td>
                    <td><code style="background:#F2F4F7;padding:2px 6px;border-radius:4px;font-weight:700">{{ $tenant->npsn }}</code></td>
                </tr>
                <tr>
                    <td style="color:var(--muted)">Subdomain</td>
                    <td><b>{{ $tenant->subdomain }}</b> <small style="color:var(--muted)">({{ $tenant->subdomain }}.simantapmonsa.id)</small></td>
                </tr>
                <tr>
                    <td style="color:var(--muted)">UUID Tenant</td>
                    <td><code style="font-size:11px">{{ $tenant->uuid }}</code></td>
                </tr>
                <tr>
                    <td style="color:var(--muted)">Tanggal Daftar</td>
                    <td>{{ $tenant->created_at ? $tenant->created_at->translatedFormat('d F Y, H:i') : '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Akun Administrator Sekolah --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-shield" style="margin-right:8px;color:#B8860B"></i>Administrator Utama Sekolah</h3>
        </div>
        <div class="card-body">
            @if($admin)
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
                    <div style="width:48px;height:48px;border-radius:50%;background:#4338CA;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700">
                        {{ substr($admin->nama_lengkap, 0, 2) }}
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:15px">{{ $admin->nama_lengkap }}</div>
                        <div style="font-size:12px;color:var(--muted)">Username: <b>{{ $admin->nama_pengguna }}</b></div>
                    </div>
                </div>
                <table style="width:100%;font-size:13px;line-height:1.8">
                    <tr>
                        <td style="width:120px;color:var(--muted)">Email</td>
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
                    <tr>
                        <td style="color:var(--muted)">Terakhir Masuk</td>
                        <td>{{ $admin->terakhir_masuk ? \Carbon\Carbon::parse($admin->terakhir_masuk)->diffForHumans() : 'Belum pernah login' }}</td>
                    </tr>
                </table>
            @else
                <div class="empty-state" style="padding:20px">
                    <p>Belum ada akun administrator yang terdaftar pada tenant ini.</p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Pengguna Terbaru di Sekolah Ini --}}
<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <h3><i class="fas fa-users" style="margin-right:8px;color:#4338CA"></i>Akun Terbaru di Sekolah Ini</h3>
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
@endsection
