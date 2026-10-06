@extends('layouts.app')

@section('title', 'Tryout - Dashboard Siswa')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📚 Tryout Online</h2>
            <p class="text-muted mb-0">Ikuti tryout untuk mengukur kemampuan Anda</p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Available Tryout Sessions -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">🎯 Tryout Tersedia</h4>
        </div>
    </div>

    @if($availableSessions->count() > 0)
    <div class="row">
        @foreach($availableSessions as $session)
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">{{ $session->nama_sesi }}</h5>
                    <small>{{ $session->kode_sesi }}</small>
                </div>
                <div class="card-body">
                    <h6 class="card-title">{{ $session->package->nama_paket }}</h6>
                    <p class="card-text text-muted small mb-2">
                        {{ $session->package->deskripsi }}
                    </p>
                    
                    <hr>
                    
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <h5 class="mb-0">{{ $session->package->jumlah_mapel }}</h5>
                            <small class="text-muted">Mapel</small>
                        </div>
                        <div class="col-4">
                            <h5 class="mb-0">{{ $session->package->durasi_total_menit }}</h5>
                            <small class="text-muted">Menit</small>
                        </div>
                        <div class="col-4">
                            <h5 class="mb-0">{{ $session->package->passing_grade }}</h5>
                            <small class="text-muted">Passing</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-success">Aktif</span>
                        <small class="text-muted">
                            Deadline: {{ $session->tanggal_selesai->format('d M Y H:i') }}
                        </small>
                    </div>

                    <a href="{{ route('tryout.student.start', $session) }}" 
                       class="btn btn-primary btn-sm w-100"
                       onclick="return confirm('Yakin ingin memulai tryout? Durasi {{ $session->package->durasi_total_menit }} menit.')">
                        🚀 Mulai Tryout
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Tidak ada tryout yang tersedia saat ini.
    </div>
    @endif

    <!-- My Tryout Results -->
    <div class="row mt-5">
        <div class="col-12">
            <h4 class="mb-3">📊 Riwayat Tryout</h4>
        </div>
    </div>

    @if($myResults->count() > 0)
    <div class="table-wrapper">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Paket Tryout</th>
                    <th>Tanggal</th>
                    <th>Nilai</th>
                    <th>Status</th>
                    <th>Peringkat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($myResults as $index => $result)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $result->session->package->nama_paket }}</strong><br>
                        <small class="text-muted">{{ $result->session->nama_sesi }}</small>
                    </td>
                    <td>{{ $result->waktu_mulai->format('d M Y H:i') }}</td>
                    <td>
                        <strong class="{{ $result->lulus ? 'text-success' : 'text-danger' }}">
                            {{ number_format($result->persentase, 1) }}
                        </strong>
                    </td>
                    <td>
                        @if($result->lulus)
                        <span class="badge bg-success">Lulus</span>
                        @else
                        <span class="badge bg-danger">Tidak Lulus</span>
                        @endif
                    </td>
                    <td>{{ $result->peringkat ?? '-' }}</td>
                    <td>
                        <a href="{{ route('tryout.student.result', $result) }}" 
                           class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> Detail
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="alert alert-secondary">
        <i class="fas fa-clock"></i> Belum ada riwayat tryout.
    </div>
    @endif
</div>
@endsection
