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
        <div class="stat-label">Total Sekolah (Tenant)</div>
        <div class="stat-value" style="color:#4338CA">{{ number_format($totalTenants) }}</div>
        <div class="stat-change">
            <span style="color:#027A48;font-weight:600">{{ $activeTenants }} aktif</span>
            @if(($inactiveTenants ?? 0) > 0)
                &bull; <span style="color:var(--bad)">{{ $inactiveTenants }} nonaktif</span>
            @endif
            &bull; {{ number_format($totalRombel ?? 0) }} rombel
        </div>
    </div>
    <div class="stat-card ok">
        <i class="fas fa-user-graduate stat-icon"></i>
        <div class="stat-label">Peserta Didik Terdaftar</div>
        <div class="stat-value">{{ number_format($totalSiswa) }}</div>
        <div class="stat-change">dari kapasitas {{ number_format($totalKuotaSiswa ?? 500) }} kuota siswa</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-chalkboard-teacher stat-icon"></i>
        <div class="stat-label">Pendidik &amp; Tendik (PTK)</div>
        <div class="stat-value">{{ number_format(($totalGuru ?? 0) + ($totalTendik ?? 0)) }}</div>
        <div class="stat-change">{{ number_format($totalGuru ?? 0) }} Guru/Kepsek &bull; {{ number_format($totalTendik ?? 0) }} Tendik</div>
    </div>
    <div class="stat-card primary">
        <i class="fas fa-users-cog stat-icon"></i>
        <div class="stat-label">Total Akun &amp; Ujian CBT</div>
        <div class="stat-value">{{ number_format($totalUsers) }}</div>
        <div class="stat-change">{{ number_format($totalCbtBanks ?? 0) }} bank soal &bull; {{ number_format($totalCbtSessions ?? 0) }} sesi ujian</div>
    </div>
</div>

{{-- Panel Kesehatan Isolasi Multi-Tenant & Aktivitas Login Terakhir --}}
<div class="grid grid-2" style="gap:20px;margin-bottom:20px">
    <div class="card" style="margin-bottom:0">
        <div class="card-header" style="justify-content:space-between">
            <h3><i class="fas fa-shield-alt" style="margin-right:8px;color:#4338CA"></i>Kesehatan Isolasi Data &amp; Tata Kelola</h3>
            @if(($unassignedUsersCount ?? 0) === 0 && ($orphanPtkCount ?? 0) === 0 && ($orphanSiswaCount ?? 0) === 0)
                <span class="tag tag-ok"><i class="fas fa-check-circle" style="margin-right:4px"></i>100% Terisolasi Bersih</span>
            @else
                <span class="tag tag-warn"><i class="fas fa-exclamation-circle" style="margin-right:4px"></i>Perlu Perhatian</span>
            @endif
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                <div style="padding:12px 14px;border-radius:10px;background:#F8FAFC;border:1px solid #E2E8F0">
                    <div style="font-size:11.5px;color:#64748B;margin-bottom:4px">Global Scope Isolasi (TenantScope)</div>
                    <div style="font-weight:700;font-size:13.5px;color:#027A48"><i class="fas fa-lock" style="margin-right:5px"></i>Aktif &amp; Terkunci</div>
                </div>
                <div style="padding:12px 14px;border-radius:10px;background:#F8FAFC;border:1px solid #E2E8F0">
                    <div style="font-size:11.5px;color:#64748B;margin-bottom:4px">Akun Tanpa Sekolah (Orphan)</div>
                    <div style="font-weight:700;font-size:13.5px;color:{{ ($unassignedUsersCount ?? 0) > 0 ? '#B45309' : '#027A48' }}">
                        {{ number_format($unassignedUsersCount ?? 0) }} Akun
                        @if(($unassignedUsersCount ?? 0) > 0)
                            <a href="{{ route('superadmin.users.index', ['tenant_id' => 'unassigned']) }}" style="font-size:11px;margin-left:6px;color:#4338CA">Periksa &rarr;</a>
                        @endif
                    </div>
                </div>
                <div style="padding:12px 14px;border-radius:10px;background:#F8FAFC;border:1px solid #E2E8F0">
                    <div style="font-size:11.5px;color:#64748B;margin-bottom:4px">Sinkronisasi PTK &amp; Siswa</div>
                    <div style="font-weight:700;font-size:13.5px;color:#1E293B">
                        {{ number_format($totalPtk ?? 0) }} PTK &bull; {{ number_format($totalSiswa) }} Siswa
                    </div>
                </div>
                <div style="padding:12px 14px;border-radius:10px;background:#F8FAFC;border:1px solid #E2E8F0">
                    <div style="font-size:11.5px;color:#64748B;margin-bottom:4px">Status Langganan Sekolah</div>
                    <div style="font-weight:700;font-size:13.5px;color:{{ ($expiredTenants ?? 0) > 0 ? 'var(--bad)' : '#027A48' }}">
                        {{ ($expiredTenants ?? 0) > 0 ? $expiredTenants . ' Sekolah Kedaluwarsa' : 'Semua Langganan Aktif' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:0">
        <div class="card-header" style="justify-content:space-between">
            <h3><i class="fas fa-history" style="margin-right:8px;color:#4338CA"></i>Aktivitas Login Terakhir Lintas Sekolah</h3>
            <a href="{{ route('superadmin.users.index') }}" class="btn btn-ghost btn-xs">Semua Pengguna &rarr;</a>
        </div>
        <div class="card-body tight">
            @if(isset($recentLogins) && $recentLogins->count() > 0)
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                        <tbody>
                            @foreach($recentLogins as $rl)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 14px">
                                        <div style="font-weight:700;color:#1E293B">{{ $rl->nama_lengkap }}</div>
                                        <div style="font-size:11px;color:#64748B">{{ $rl->tenant?->nama_sekolah ?? 'Super Admin Pusat' }}</div>
                                    </td>
                                    <td style="padding:8px 10px;text-align:center">
                                        <span class="tag" style="background:#EEF2FF;color:#4338CA;text-transform:uppercase;font-size:10px;font-weight:700">{{ $rl->peran }}</span>
                                    </td>
                                    <td style="padding:8px 14px;text-align:right;color:#64748B;font-size:11.5px">
                                        {{ \Carbon\Carbon::parse($rl->terakhir_masuk)->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">
                    Belum ada riwayat login terbaru yang tercatat.
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Tabel Sekolah Terdaftar & Kontrol Cepat --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header" style="justify-content:space-between;flex-wrap:wrap;gap:10px">
        <h3><i class="fas fa-building" style="margin-right:8px;color:#4338CA"></i>Daftar Sekolah Terdaftar &amp; Kontrol Operasional</h3>
        <div style="display:flex;gap:8px;align-items:center">
            <a href="{{ route('superadmin.tenants.index', ['tambah' => 1]) }}#tambah" class="btn btn-xs" style="background:#EEF2FF;color:#4338CA;font-weight:700">
                <i class="fas fa-plus-circle"></i> Daftarkan Sekolah
            </a>
            <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-ghost btn-sm">Katalog Lengkap &rarr;</a>
        </div>
    </div>
    <div class="card-body tight">
        @if($tenantsWithStats->count() > 0)
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 14px;text-align:left;color:#667085">Sekolah / NPSN</th>
                            <th style="padding:10px 14px;text-align:left;color:#667085">Domain &amp; Paket</th>
                            <th style="padding:10px 14px;text-align:left;color:#667085">Kepala Sekolah &amp; Admin</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Kuota Siswa</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Guru &amp; Tendik</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Status</th>
                            <th style="padding:10px 14px;text-align:center;color:#667085">Aksi Cepat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenantsWithStats as $item)
                            @php
                                $t = $item['tenant'];
                                $kuotaSiswa = $t->kuota_siswa ?: 500;
                                $persenKuota = $kuotaSiswa > 0 ? round(($item['total_siswa'] / $kuotaSiswa) * 100) : 0;
                            @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:10px 14px">
                                    <div style="font-weight:700">
                                        <a href="{{ route('superadmin.tenants.show', $t->uuid) }}" style="color:#4338CA;text-decoration:none">
                                            {{ $t->nama_sekolah }}
                                        </a>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted)">
                                        <span style="font-family:monospace;font-weight:600">NPSN: {{ $t->npsn }}</span>
                                        @if($t->wilayah) &bull; {{ $t->wilayah }} @endif
                                        &bull; {{ $item['total_rombel'] ?? 0 }} Rombel
                                    </div>
                                </td>
                                <td style="padding:10px 14px">
                                    <div>
                                        <span style="background:#EEF2FF;color:#4338CA;padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:600">
                                            {{ $t->subdomain }}.simantap.sch.id
                                        </span>
                                    </div>
                                    <div style="margin-top:4px;font-size:11px;color:#64748B">
                                        Paket: <b style="text-transform:uppercase;color:#3730A3">{{ $t->paket ?? 'standar' }}</b>
                                    </div>
                                </td>
                                <td style="padding:10px 14px">
                                    <div style="font-weight:600;font-size:12.5px;color:#1E293B">
                                        <i class="fas fa-user-tie" style="color:#4338CA;margin-right:4px" title="Kepala Sekolah"></i>{{ $item['kepsek']?->nama_lengkap ?? '—' }}
                                    </div>
                                    <div style="font-size:11px;color:var(--muted);margin-top:2px">
                                        Admin: <b>{{ $item['admin']?->nama_pengguna ?? '—' }}</b> ({{ $item['admin']?->email ?? '—' }})
                                    </div>
                                </td>
                                <td style="padding:10px 14px;text-align:center">
                                    <div style="font-weight:700;font-size:12.5px">
                                        {{ $item['total_siswa'] }} <span style="font-weight:400;color:var(--muted)">/ {{ $kuotaSiswa }}</span>
                                    </div>
                                    <div style="width:72px;background:#E4E7EC;border-radius:4px;height:5px;margin:4px auto 0;overflow:hidden">
                                        <div style="background:{{ $persenKuota > 90 ? 'var(--bad)' : ($persenKuota > 75 ? '#F59E0B' : '#4338CA') }};width:{{ min(100, $persenKuota) }}%;height:100%"></div>
                                    </div>
                                </td>
                                <td style="padding:10px 14px;text-align:center">
                                    <span style="font-weight:700;color:#1E293B">{{ $item['total_guru'] }}</span> <span style="font-size:11.5px;color:#64748B">Guru</span>
                                    <div style="font-size:11px;color:#64748B">{{ $item['total_tendik'] ?? 0 }} Tendik</div>
                                </td>
                                <td style="padding:10px 14px;text-align:center">
                                    @if($t->aktif)
                                        <span class="tag tag-ok">Aktif</span>
                                    @else
                                        <span class="tag tag-bad">Nonaktif</span>
                                    @endif
                                </td>
                                <td style="padding:10px 14px;text-align:center">
                                    <div style="display:flex;gap:6px;justify-content:center;align-items:center">
                                        <form method="POST" action="{{ route('superadmin.tenants.impersonate', $t->uuid) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs" style="background:#EEF2FF;color:#4338CA;font-weight:700" title="Masuk langsung ke dasbor sekolah ini (Mode Supervisi)">
                                                <i class="fas fa-sign-in-alt"></i> Masuk
                                            </button>
                                        </form>
                                        <a href="{{ route('superadmin.tenants.show', $t->uuid) }}" class="btn btn-ghost btn-xs" title="Kelola Paket & Detail Sekolah">
                                            <i class="fas fa-sliders-h"></i> Kelola
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
