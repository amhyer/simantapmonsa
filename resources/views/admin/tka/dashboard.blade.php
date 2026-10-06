@extends('layouts.admin-with-tabs')

@section('title', 'Dashboard TKA - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Dashboard TKA')
@section('page_subtitle', 'Monitoring Profil Pelajar')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">🎯 Dashboard TKA (Tes Kesiapan Akhir)</h2>
            <p class="text-muted mb-0">Monitoring dan manajemen penilaian Profil Pelajar Pancasila</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Dimensi</h6>
                            <h2 class="mb-0">{{ $stats['total_dimensions'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-th-large fa-3x text-primary opacity-25"></i>
                        </div>
                    </div>
                    <small class="text-muted">{{ $stats['active_dimensions'] }} aktif</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Penilaian</h6>
                            <h2 class="mb-0">{{ $stats['total_assessments'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clipboard-check fa-3x text-success opacity-25"></i>
                        </div>
                    </div>
                    <small class="text-muted">{{ $stats['assessments_this_month'] }} bulan ini</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Early Warnings</h6>
                            <h2 class="mb-0">{{ $stats['early_warnings']['total'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning opacity-25"></i>
                        </div>
                    </div>
                    <small class="text-muted">{{ $stats['early_warnings']['pending'] }} pending</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Kritis</h6>
                            <h2 class="mb-0 text-danger">{{ $stats['critical_warnings']->count() }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-radiation fa-3x text-danger opacity-25"></i>
                        </div>
                    </div>
                    <small class="text-muted">Perlu tindakan segera</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Status Distribution -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">📊 Distribusi Status Kesiapan</h5>
                </div>
                <div class="card-body">
                    @php
                    $statusColors = [
                        'sangat_siap' => 'success',
                        'siap' => 'primary',
                        'perlu_bimbingan' => 'warning',
                        'belum_siap' => 'danger',
                    ];
                    $statusLabels = [
                        'sangat_siap' => 'Sangat Siap',
                        'siap' => 'Siap',
                        'perlu_bimbingan' => 'Perlu Bimbingan',
                        'belum_siap' => 'Belum Siap',
                    ];
                    @endphp
                    
                    @foreach($stats['status_distribution'] as $status => $count)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ $statusLabels[$status] ?? $status }}</span>
                            <strong>{{ $count }}</strong>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-{{ $statusColors[$status] ?? 'secondary' }}" 
                                 role="progressbar"
                                 style="width: {{ $stats['total_assessments'] > 0 ? ($count / $stats['total_assessments'] * 100) : 0 }}%">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Critical Warnings -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">🚨 Early Warning Kritis</h5>
                </div>
                <div class="card-body">
                    @if($stats['critical_warnings']->count() > 0)
                    <div class="table-wrapper">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th>Dimensi</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['critical_warnings'] as $warning)
                                <tr>
                                    <td>
                                        <strong>{{ $warning->siswa->nama_peserta_didik }}</strong><br>
                                        <small class="text-muted">{{ $warning->siswa->kelas }}</small>
                                    </td>
                                    <td>{{ $warning->dimension->nama_dimensi }}</td>
                                    <td>{{ $warning->created_at->format('d M Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.tka.warnings.index', ['status' => 'pending', 'tingkat_peringatan' => 'kritis']) }}" 
                                           class="btn btn-sm btn-danger">
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p class="mb-0">Tidak ada early warning kritis</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Assessments -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">📝 Penilaian Terbaru</h5>
                </div>
                <div class="card-body">
                    @if($stats['recent_assessments']->count() > 0)
                    <div class="table-wrapper">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['recent_assessments'] as $assessment)
                                <tr>
                                    <td>
                                        <strong>{{ $assessment->siswa->nama_peserta_didik }}</strong><br>
                                        <small class="text-muted">{{ $assessment->semester->label }}</small>
                                    </td>
                                    <td>{{ $assessment->tanggal_penilaian->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $statusColors[$assessment->status_kesiapan] ?? 'secondary' }}">
                                            {{ $statusLabels[$assessment->status_kesiapan] ?? $assessment->status_kesiapan }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center text-muted py-4">
                        <p class="mb-0">Belum ada penilaian</p>
                    </div>
                    @endif
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin.tka.assessments.index') }}" class="btn btn-sm btn-primary">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">🔗 Menu Cepat</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="{{ route('admin.tka.dimensions.index') }}" class="btn btn-outline-primary w-100">
                                <i class="fas fa-th-large"></i><br>
                                Kelola Dimensi
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="{{ route('admin.tka.assessments.index') }}" class="btn btn-outline-success w-100">
                                <i class="fas fa-clipboard-check"></i><br>
                                Semua Penilaian
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="{{ route('admin.tka.warnings.index') }}" class="btn btn-outline-warning w-100">
                                <i class="fas fa-exclamation-triangle"></i><br>
                                Early Warnings
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <button class="btn btn-outline-info w-100" disabled>
                                <i class="fas fa-file-export"></i><br>
                                Export Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

