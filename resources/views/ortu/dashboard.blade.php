@extends('layouts.app')

@section('title', 'Dashboard Orang Tua - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Beranda Orang Tua')
@section('page_subtitle', 'Pantau perkembangan anak')

@section('content')
    @if(!$anak)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi admin.
        </div>
    @else

    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-user stat-icon"></i>
            <div class="stat-label">Nama Anak</div>
            <div class="stat-value" style="font-size:16px">{{ $anak->nama_peserta_didik }}</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-id-card stat-icon"></i>
            <div class="stat-label">NIS</div>
            <div class="stat-value" style="font-size:16px">{{ $anak->nis }}</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-school stat-icon"></i>
            <div class="stat-label">Kelas</div>
            <div class="stat-value" style="font-size:16px">{{ $anak->kelas }}</div>
        </div>
        <div class="stat-card primary">
            <i class="fas fa-phone stat-icon"></i>
            <div class="stat-label">No. HP Orang Tua</div>
            <div class="stat-value" style="font-size:14px">{{ $anak->telepon ?? '-' }}</div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-graduate" style="margin-right:8px;color:var(--navy)"></i>Data Diri Anak</h3>
            </div>
            <div class="card-body">
                <p style="margin-bottom:8px"><b>Tempat, Tanggal Lahir:</b> {{ $anak->tempat_lahir ?? '-' }}, {{ $anak->tanggal_lahir ? \Carbon\Carbon::parse($anak->tanggal_lahir)->translatedFormat('d M Y') : '-' }}</p>
                <p style="margin-bottom:8px"><b>Jenis Kelamin:</b> {{ $anak->jenis_kelamin }}</p>
                <p style="margin-bottom:8px"><b>Alamat:</b> {{ $anak->alamat ?? '-' }}</p>
                <p style="margin-bottom:8px"><b>No. HP Siswa:</b> {{ $anak->telepon ?? '-' }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--gold)"></i>Akses Cepat</h3>
            </div>
            <div class="card-body" style="display:grid;gap:8px">
                <a href="{{ route('ortu.kebiasaan.index') }}" class="btn btn-ghost btn-block"><i class="fas fa-clipboard-list"></i> Isi 7 Kebiasaan</a>
                <a href="{{ route('ortu.nilai.index') }}" class="btn btn-ghost btn-block"><i class="fas fa-chart-bar"></i> Lihat Perkembangan Nilai</a>
            </div>
        </div>
    </div>
    @endif
@endsection
