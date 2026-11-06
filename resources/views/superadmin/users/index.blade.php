@extends('layouts.app')

@section('title', 'Akun Pengguna Seluruh Sekolah - SIMANTAP Super Admin')

@section('sidebar')
    @include('superadmin.partials.sidebar')
@endsection

@section('page_title', 'Akun Seluruh Sekolah')
@section('page_subtitle', 'Pantau akun pengguna di semua tenant platform')

@section('content')
<style>
    :root {
        --navy: #4338CA;
        --navy-light: #6366F1;
        --navy-dark: #312E81;
    }
</style>

{{-- Search & Filter Bar --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px">
        <form method="GET" action="{{ route('superadmin.users.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:240px">
                <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Cari nama pengguna, username, email...">
            </div>
            <div style="min-width:160px">
                <select name="peran" class="form-control">
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
                <i class="fas fa-search"></i> Filter
            </button>
            @if($search !== '' || $peran !== null)
                <a href="{{ route('superadmin.users.index') }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <h3><i class="fas fa-users" style="margin-right:8px;color:#4338CA"></i>Daftar Akun Pengguna Terdaftar</h3>
        <span class="tag tag-mut">{{ $users->total() }} akun</span>
    </div>
    <div class="card-body tight">
        @if($users->count() > 0)
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:11px 14px;text-align:center;width:50px;color:#667085">No</th>
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
                <p>Coba gunakan kata kunci pencarian yang lain.</p>
            </div>
        @endif
    </div>
</div>
@endsection
