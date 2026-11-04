@extends('layouts.admin-with-tabs')

@section('title', 'Kelola Dimensi TKA - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Dimensi Profil Pelajar Pancasila')
@section('page_subtitle', 'Konfigurasi 6 dimensi dan butir indikator pengamatan karakter siswa')

@section('header_actions')
    <a href="{{ route('admin.tka.dimensions.create') }}" class="btn btn-gold btn-sm">
        <i class="fas fa-plus"></i> Tambah Dimensi
    </a>
@endsection

@section('content')
{{-- Stat Summary --}}
<div class="grid grid-3" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-th-large stat-icon"></i>
        <div class="stat-label">Total Dimensi</div>
        <div class="stat-value">{{ $dimensions->count() }}</div>
        <div class="stat-change">Dimensi kurikulum</div>
    </div>

    <div class="stat-card ok">
        <i class="fas fa-check-circle stat-icon"></i>
        <div class="stat-label">Dimensi Aktif</div>
        <div class="stat-value">{{ $dimensions->where('aktif', true)->count() }}</div>
        <div class="stat-change up">Digunakan dalam asesmen</div>
    </div>

    <div class="stat-card gold">
        <i class="fas fa-list-check stat-icon"></i>
        <div class="stat-label">Total Indikator</div>
        <div class="stat-value">{{ $dimensions->sum('items_count') }}</div>
        <div class="stat-change">Butir rubrik pengamatan</div>
    </div>
</div>

{{-- Dimensions Table Card --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-layer-group" style="color:var(--navy);margin-right:8px"></i>Daftar Dimensi Profil Pelajar Pancasila</h3>
        <a href="{{ route('admin.tka.dimensions.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Tambah Dimensi
        </a>
    </div>

    <div class="card-body tight">
        @if($dimensions->isEmpty())
            <div class="empty-state">
                <div class="icon">🎯</div>
                <h4>Belum Ada Dimensi Dikonfigurasi</h4>
                <p>Tambahkan 6 dimensi Profil Pelajar Pancasila untuk memulai penilaian TKA.</p>
                <div style="margin-top:16px">
                    <a href="{{ route('admin.tka.dimensions.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Dimensi Sekarang
                    </a>
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:10%">Kode</th>
                            <th style="width:30%">Nama Dimensi</th>
                            <th style="width:25%">Deskripsi Ringkas</th>
                            <th style="width:10%">Bobot</th>
                            <th style="width:10%">Indikator</th>
                            <th style="width:8%">Status</th>
                            <th style="width:12%;text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dimensions as $dim)
                            <tr>
                                <td>
                                    <span class="tag tag-primary" style="font-family:monospace;font-weight:700">
                                        {{ $dim->kode_dimensi }}
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:700;font-size:14px;color:var(--text)">
                                        <a href="{{ route('admin.tka.dimensions.show', $dim) }}" style="color:var(--navy);text-decoration:none">
                                            {{ $dim->nama_dimensi }}
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:12.5px;color:var(--muted);line-height:1.4">
                                        {{ Str::limit($dim->deskripsi ?? 'Tidak ada deskripsi', 80) }}
                                    </div>
                                </td>
                                <td>
                                    <span class="tag tag-gold">{{ $dim->bobot ?? 1 }}%</span>
                                </td>
                                <td>
                                    <span class="tag tag-secondary">
                                        {{ $dim->items_count ?? $dim->assessmentItems()->count() }} Butir
                                    </span>
                                </td>
                                <td>
                                    @if($dim->aktif)
                                        <span class="tag tag-ok">Aktif</span>
                                    @else
                                        <span class="tag tag-bad">Nonaktif</span>
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;align-items:center;gap:6px">
                                        <a href="{{ route('admin.tka.dimensions.show', $dim) }}" class="btn btn-primary btn-sm" title="Lihat Indikator">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.tka.dimensions.edit', $dim) }}" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.tka.dimensions.toggle-status', $dim) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm" title="{{ $dim->aktif ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                <i class="fas fa-power-off" style="color:{{ $dim->aktif ? 'var(--bad)' : 'var(--ok)' }}"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
