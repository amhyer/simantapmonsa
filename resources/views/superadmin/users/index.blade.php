@extends('layouts.app')

@section('title', 'Daftar Akun Pengguna Per-Sekolah & Per-Wilayah - SIMANTAP Super Admin')

@section('sidebar')
    @include('superadmin.partials.sidebar')
@endsection

@section('page_title', 'Daftar Pengguna Multi-Tenant')
@section('page_subtitle', 'Pemisahan akun pengguna berdasarkan sekolah dan wilayah operasional')

@section('header_actions')
    <a href="{{ route('superadmin.tenants.index') }}" class="btn btn-sm btn-ghost" style="border:1px solid #E4E7EC">
        <i class="fas fa-school"></i> Katalog Sekolah
    </a>
@endsection

@section('content')
<style>
    :root {
        --navy: #4338CA;
        --navy-light: #6366F1;
        --navy-dark: #312E81;
    }
    .user-tab-nav {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        border-bottom: 2px solid #E5E7EB;
        padding-bottom: 2px;
        overflow-x: auto;
    }
    .user-tab-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        font-size: 13.5px;
        font-weight: 600;
        color: #4B5563;
        text-decoration: none;
        border-radius: 8px 8px 0 0;
        transition: 0.15s;
        white-space: nowrap;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }
    .user-tab-btn:hover {
        color: #4338CA;
        background: #F3F4F6;
    }
    .user-tab-btn.active {
        color: #4338CA;
        border-bottom-color: #4338CA;
        background: #EEF2FF;
    }
</style>

{{-- Tab Navigasi Pemisahan --}}
<div class="user-tab-nav">
    <a href="{{ route('superadmin.users.index', array_merge(request()->except('tab'), ['tab' => 'semua'])) }}" 
       class="user-tab-btn {{ $tab === 'semua' ? 'active' : '' }}">
        <i class="fas fa-list"></i> Semua Akun Pengguna
        <span class="tag" style="background:#E0E7FF;color:#3730A3;font-size:11px;padding:2px 8px;border-radius:99px">{{ $totalUsersPlatform }}</span>
    </a>
    <a href="{{ route('superadmin.users.index', array_merge(request()->except('tab'), ['tab' => 'per_sekolah'])) }}" 
       class="user-tab-btn {{ $tab === 'per_sekolah' ? 'active' : '' }}">
        <i class="fas fa-school"></i> Pemisahan Per-Sekolah
        <span class="tag" style="background:#E0E7FF;color:#3730A3;font-size:11px;padding:2px 8px;border-radius:99px">{{ $rekapSekolah->count() }} Sekolah</span>
    </a>
    <a href="{{ route('superadmin.users.index', array_merge(request()->except('tab'), ['tab' => 'per_wilayah'])) }}" 
       class="user-tab-btn {{ $tab === 'per_wilayah' ? 'active' : '' }}">
        <i class="fas fa-map-marked-alt"></i> Pemisahan Per-Wilayah
        <span class="tag" style="background:#E0E7FF;color:#3730A3;font-size:11px;padding:2px 8px;border-radius:99px">{{ $rekapWilayah->count() }} Wilayah</span>
    </a>
</div>

@if($tab === 'per_sekolah')
    {{-- TAB 2: PEMISAHAN PER-SEKOLAH --}}
    <div class="card">
        <div class="card-header" style="justify-content:space-between">
            <h3><i class="fas fa-school" style="margin-right:8px;color:#4338CA"></i>Rekapitulasi Akun Berdasarkan Sekolah</h3>
            <span class="tag tag-mut">{{ $rekapSekolah->count() }} sekolah terdaftar</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:11px 14px;text-align:center;width:40px;color:#667085">No</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">Nama Sekolah</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">Wilayah / Kecamatan</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Total Akun</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Siswa</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Guru</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Admin</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Tendik/Kepsek</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rekapSekolah as $idx => $s)
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:11px 14px;text-align:center;color:var(--muted)">{{ $idx + 1 }}</td>
                                <td style="padding:11px 14px">
                                    <div style="font-weight:700">
                                        <a href="{{ route('superadmin.tenants.show', $s->uuid) }}" style="color:#4338CA;text-decoration:none">
                                            {{ $s->nama_sekolah }}
                                        </a>
                                    </div>
                                    <div style="font-size:11px;color:var(--muted);font-family:monospace">NPSN: {{ $s->npsn }}</div>
                                </td>
                                <td style="padding:11px 14px">
                                    @if($s->wilayah)
                                        <span class="tag" style="background:#ECFDF5;color:#047857;font-weight:600">
                                            <i class="fas fa-map-pin" style="margin-right:4px"></i>{{ $s->wilayah }}
                                        </span>
                                    @else
                                        <span style="color:var(--muted);font-style:italic">Belum diatur</span>
                                    @endif
                                </td>
                                <td style="padding:11px 14px;text-align:center;font-weight:700;font-size:14px;color:#4338CA">
                                    {{ number_format($s->total_akun) }}
                                </td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">{{ number_format($s->siswa_count) }}</td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">{{ number_format($s->guru_count) }}</td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">{{ number_format($s->admin_count) }}</td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">{{ number_format($s->tendik_count) }}</td>
                                <td style="padding:11px 14px;text-align:center">
                                    <div style="display:flex;gap:6px;justify-content:center">
                                        <a href="{{ route('superadmin.users.index', ['tenant_id' => $s->id, 'tab' => 'semua']) }}" class="btn btn-xs" style="background:#EEF2FF;color:#4338CA;font-weight:600" title="Buka daftar akun sekolah ini">
                                            <i class="fas fa-users"></i> Buka Akun
                                        </a>
                                        <form method="POST" action="{{ route('superadmin.tenants.impersonate', $s->uuid) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-ghost" title="Masuk ke sekolah ini">
                                                <i class="fas fa-sign-in-alt"></i> Masuk
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align:center;padding:24px;color:var(--muted)">Belum ada data sekolah terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@elseif($tab === 'per_wilayah')
    {{-- TAB 3: PEMISAHAN PER-WILAYAH --}}
    <div class="card">
        <div class="card-header" style="justify-content:space-between">
            <h3><i class="fas fa-map-marked-alt" style="margin-right:8px;color:#4338CA"></i>Rekapitulasi Akun Pengguna Berdasarkan Wilayah</h3>
            <span class="tag tag-mut">{{ $rekapWilayah->count() }} wilayah</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:11px 14px;text-align:center;width:40px;color:#667085">No</th>
                            <th style="padding:11px 14px;text-align:left;color:#667085">Wilayah / Kecamatan</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Jumlah Sekolah</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Total Akun</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Siswa</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Guru</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Admin</th>
                            <th style="padding:11px 14px;text-align:center;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rekapWilayah as $idx => $w)
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:11px 14px;text-align:center;color:var(--muted)">{{ $idx + 1 }}</td>
                                <td style="padding:11px 14px;font-weight:700">
                                    <span class="tag" style="background:#ECFDF5;color:#047857;font-size:13px;padding:4px 10px">
                                        <i class="fas fa-map-marker-alt" style="margin-right:6px"></i>{{ $w->wilayah }}
                                    </span>
                                </td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">{{ number_format($w->total_sekolah) }} sekolah</td>
                                <td style="padding:11px 14px;text-align:center;font-weight:700;font-size:14px;color:#4338CA">
                                    {{ number_format($w->total_akun) }}
                                </td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">{{ number_format($w->total_siswa) }}</td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">{{ number_format($w->total_guru) }}</td>
                                <td style="padding:11px 14px;text-align:center;font-weight:600">{{ number_format($w->total_admin) }}</td>
                                <td style="padding:11px 14px;text-align:center">
                                    <a href="{{ route('superadmin.users.index', ['wilayah' => $w->wilayah, 'tab' => 'semua']) }}" class="btn btn-xs" style="background:#4338CA;color:#fff;font-weight:600">
                                        <i class="fas fa-search"></i> Buka Akun Wilayah Ini
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">Belum ada data wilayah sekolah yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@else
    {{-- TAB 1: SEMUA AKUN (DENGAN FILTER MULTI-TINGKAT) --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px">
            <form method="GET" action="{{ route('superadmin.users.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                <input type="hidden" name="tab" value="semua">
                
                {{-- Search keyword --}}
                <div style="flex:2;min-width:200px">
                    <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Cari nama pengguna, username, email...">
                </div>

                {{-- Filter Wilayah --}}
                <div style="flex:1;min-width:160px">
                    <select name="wilayah" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Wilayah</option>
                        @foreach($wilayahList as $w)
                            <option value="{{ $w }}" {{ $wilayah === $w ? 'selected' : '' }}>{{ $w }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Sekolah --}}
                <div style="flex:1.5;min-width:180px">
                    <select name="tenant_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Sekolah</option>
                        @foreach($tenantsList as $t)
                            <option value="{{ $t->id }}" {{ (string)$tenantId === (string)$t->id ? 'selected' : '' }}>
                                {{ $t->nama_sekolah }} {{ $t->wilayah ? "({$t->wilayah})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Peran --}}
                <div style="flex:1;min-width:140px">
                    <select name="peran" class="form-control" onchange="this.form.submit()">
                        <option value="">Semua Peran</option>
                        <option value="admin" {{ $peran === 'admin' ? 'selected' : '' }}>Admin Sekolah</option>
                        <option value="guru" {{ $peran === 'guru' ? 'selected' : '' }}>Guru</option>
                        <option value="kepsek" {{ $peran === 'kepsek' ? 'selected' : '' }}>Kepala Sekolah</option>
                        <option value="tendik" {{ $peran === 'tendik' ? 'selected' : '' }}>Tendik</option>
                        <option value="siswa" {{ $peran === 'siswa' ? 'selected' : '' }}>Siswa</option>
                        <option value="ortu" {{ $peran === 'ortu' ? 'selected' : '' }}>Orang Tua</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-sm" style="background:#4338CA;color:#fff">
                    <i class="fas fa-filter"></i> Filter
                </button>
                @if($search !== '' || $peran !== null || $wilayah !== null || $tenantId !== null)
                    <a href="{{ route('superadmin.users.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Indikator Filter Aktif --}}
    @if($wilayah || $tenantId)
        <div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="font-size:12.5px;color:var(--muted)">Menampilkan hasil untuk:</span>
            @if($wilayah)
                <span class="tag" style="background:#ECFDF5;color:#047857;font-weight:700">
                    <i class="fas fa-map-marker-alt" style="margin-right:4px"></i> Wilayah: {{ $wilayah }}
                </span>
            @endif
            @if($tenantId)
                @php $tObj = $tenantsList->firstWhere('id', $tenantId); @endphp
                <span class="tag" style="background:#EEF2FF;color:#4338CA;font-weight:700">
                    <i class="fas fa-school" style="margin-right:4px"></i> Sekolah: {{ $tObj?->nama_sekolah ?? $tenantId }}
                </span>
            @endif
            <a href="{{ route('superadmin.users.index') }}" style="font-size:12px;color:var(--bad);text-decoration:none;margin-left:4px">
                <i class="fas fa-times-circle"></i> Hapus Filter
            </a>
        </div>
    @endif

    {{-- Tabel Pengguna --}}
    <div class="card">
        <div class="card-header" style="justify-content:space-between">
            <h3><i class="fas fa-users" style="margin-right:8px;color:#4338CA"></i>Daftar Akun Pengguna</h3>
            <span class="tag tag-mut">{{ $users->total() }} akun ditemukan</span>
        </div>
        <div class="card-body tight">
            @if($users->count() > 0)
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:center;width:40px;color:#667085">No</th>
                                <th style="padding:11px 14px;text-align:left;color:#667085">Wilayah</th>
                                <th style="padding:11px 14px;text-align:left;color:#667085">Sekolah (Tenant)</th>
                                <th style="padding:11px 14px;text-align:left;color:#667085">Nama Lengkap</th>
                                <th style="padding:11px 14px;text-align:left;color:#667085">Username / Email</th>
                                <th style="padding:11px 14px;text-align:center;color:#667085">Peran</th>
                                <th style="padding:11px 14px;text-align:center;color:#667085">Status</th>
                                <th style="padding:11px 14px;text-align:right;color:#667085">Terakhir Masuk</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $i => $u)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:11px 14px;text-align:center;color:var(--muted)">
                                        {{ $users->firstItem() + $i }}
                                    </td>
                                    <td style="padding:11px 14px">
                                        @if($u->tenant && $u->tenant->wilayah)
                                            <a href="{{ route('superadmin.users.index', ['wilayah' => $u->tenant->wilayah]) }}" 
                                               style="text-decoration:none">
                                                <span class="tag" style="background:#ECFDF5;color:#047857;font-weight:600;font-size:11px">
                                                    {{ $u->tenant->wilayah }}
                                                </span>
                                            </a>
                                        @else
                                            <span style="color:var(--muted);font-size:11px">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px">
                                        @if($u->tenant)
                                            <a href="{{ route('superadmin.tenants.show', $u->tenant->uuid) }}" style="font-weight:600;color:#4338CA;text-decoration:none">
                                                {{ $u->tenant->nama_sekolah }}
                                            </a>
                                            <div style="font-size:11px;color:var(--muted)">NPSN: {{ $u->tenant->npsn }}</div>
                                        @else
                                            <span style="color:var(--muted)">Pusat Platform</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;font-weight:600">{{ $u->nama_lengkap }}</td>
                                    <td style="padding:11px 14px">
                                        <span style="font-family:monospace">{{ $u->nama_pengguna }}</span>
                                        @if($u->email)
                                            <div style="font-size:11px;color:var(--muted)">{{ $u->email }}</div>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;text-align:center">
                                        <span class="tag" style="background:#F2F4F7;text-transform:uppercase;font-size:10.5px">
                                            {{ $u->peran }}
                                        </span>
                                    </td>
                                    <td style="padding:11px 14px;text-align:center">
                                        @if($u->aktif)
                                            <span class="tag tag-ok">Aktif</span>
                                        @else
                                            <span class="tag tag-bad">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;text-align:right;color:var(--muted)">
                                        {{ $u->terakhir_masuk ? \Carbon\Carbon::parse($u->terakhir_masuk)->diffForHumans() : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:16px">
                    {{ $users->links() }}
                </div>
            @else
                <div class="empty-state" style="padding:32px">
                    <h4>Tidak ada pengguna ditemukan</h4>
                    <p>Coba gunakan kata kunci pencarian atau filter wilayah/sekolah yang lain.</p>
                </div>
            @endif
        </div>
    </div>
@endif
@endsection
