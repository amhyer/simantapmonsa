@extends('layouts.admin-with-tabs')

@section('title', 'Dasbor Admin - SIMANTAP')
@section('page_title', 'Dasbor Admin')
@section('page_subtitle', $tenant->nama_sekolah)

@section('content')
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <div class="stat-label">Total Siswa</div>
            <div class="stat-value">{{ number_format($siswa) }}</div>
            <div class="stat-change">{{ number_format($siswaAktif) }} aktif</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-label">Guru Aktif</div>
            <div class="stat-value">{{ number_format($guru) }}</div>
        </div>
        <div class="stat-card ok">
            <div class="stat-label">Kelas / Rombel</div>
            <div class="stat-value">{{ number_format($rombel) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Akun Pengguna</div>
            <div class="stat-value">{{ number_format($totalUsers) }}</div>
        </div>
    </div>
    <div class="card" style="margin-bottom:20px">
        <div class="card-header"><h3>Sinkronisasi Dapodik Terakhir</h3></div>
        <div class="card-body">
            @forelse($syncLogs as $log)
                <p>{{ $log->created_at?->format('d/m/Y H:i') ?? '-' }} — {{ $log->tipe }}: {{ $log->berhasil }} berhasil, {{ $log->gagal }} gagal</p>
            @empty
                <p>Belum ada log sinkronisasi untuk sekolah ini.</p>
            @endforelse
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.dapodik.index') }}">Lihat Dapodik</a>
        </div>
    </div>
    <p style="color:var(--muted)">Dasbor sekolah hanya menampilkan data yang sudah dipisahkan berdasarkan sekolah. Statistik sistem global tidak ditampilkan.</p>
@endsection
