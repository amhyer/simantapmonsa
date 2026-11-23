@extends('layouts.admin-with-tabs')

@section('title', 'Dasbor Admin - SIMANTAP')
@section('page_title', 'Dasbor Admin')
@section('page_subtitle', $tenant->nama_sekolah)

@section('content')
    @php $dashAvail = \App\Services\Navigation\SchoolDataAvailabilityService::status(); @endphp
    @if(!($dashAvail['has_any_data'] ?? false))
        <div class="card" style="margin-bottom:20px;border:1.5px solid #C7D7FE;background:linear-gradient(135deg,#EEF4FF,#F8FAFF)">
            <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                <div style="flex:1;min-width:240px">
                    <div style="font-size:15px;font-weight:800;color:var(--navy);margin-bottom:4px">
                        <i class="fas fa-database" style="margin-right:6px;color:var(--gold)"></i>Data Sekolah Masih Kosong
                    </div>
                    <div style="font-size:13px;color:#475467;line-height:1.5">
                        Saat ini hanya akun Administrator yang aktif dan menu tanpa data disembunyikan otomatis. Silakan tarik data dari Dapodik agar seluruh menu sekolah muncul sesuai data yang ditarik.
                    </div>
                </div>
                <a href="{{ route('admin.dapodik.index') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-sync-alt"></i> Tarik Data Dapodik
                </a>
            </div>
        </div>
    @endif
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
    @include('admin.partials.forum-pengumuman')
@endsection
