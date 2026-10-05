@extends('layouts.app')

@section('title', 'Statistik Tryout - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Dashboard Tryout')
@section('page_subtitle', 'Statistik dan ringkasan tryout')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📊 Statistik Tryout</h2>
            <p class="text-muted mb-0">Monitoring dan analisis sistem tryout online</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Paket</h6>
                            <h2 class="mb-0">{{ $stats['total_packages'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-box fa-3x text-primary opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Sesi</h6>
                            <h2 class="mb-0">{{ $stats['total_sessions'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-check fa-3x text-success opacity-25"></i>
                        </div>
                    </div>
                    <small class="text-muted">{{ $stats['active_sessions'] }} sedang berjalan</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Hasil</h6>
                            <h2 class="mb-0">{{ $stats['total_results'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-bar fa-3x text-info opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Pass Rate</h6>
                            <h2 class="mb-0">{{ number_format($stats['average_pass_rate'], 1) }}%</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-percentage fa-3x text-warning opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Packages by Jenjang -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">📊 Paket per Jenjang</h5>
                </div>
                <div class="card-body">
                    @foreach($stats['packages_by_jenjang'] as $jenjang => $count)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ $jenjang }}</span>
                            <strong>{{ $count }}</strong>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-primary" 
                                 role="progressbar"
                                 style="width: {{ $stats['total_packages'] > 0 ? ($count / $stats['total_packages'] * 100) : 0 }}%">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Recent Sessions -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">📅 Sesi Terbaru</h5>
                </div>
                <div class="card-body">
                    @if($stats['recent_sessions']->count() > 0)
                    <div class="table-wrapper">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nama Sesi</th>
                                    <th>Paket</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['recent_sessions'] as $session)
                                <tr>
                                    <td>
                                        <strong>{{ $session->nama_sesi }}</strong><br>
                                        <small class="text-muted">{{ $session->kode_sesi }}</small>
                                    </td>
                                    <td>{{ $session->package->nama_paket ?? '-' }}</td>
                                    <td>
                                        @php
                                        $statusColors = [
                                            'draft' => 'secondary',
                                            'published' => 'info',
                                            'ongoing' => 'warning',
                                            'completed' => 'success',
                                            'archived' => 'dark',
                                        ];
                                        @endphp
                                        <span class="badge bg-{{ $statusColors[$session->status] ?? 'secondary' }}">
                                            {{ ucfirst($session->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center text-muted py-4">
                        <p class="mb-0">Belum ada sesi</p>
                    </div>
                    @endif
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin.tryout.sessions.index') }}" class="btn btn-sm btn-primary">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">🔗 Menu Cepat</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.tryout.packages.index') }}" class="btn btn-outline-primary w-100">
                                <i class="fas fa-box"></i><br>
                                Kelola Paket
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.tryout.sessions.index') }}" class="btn btn-outline-success w-100">
                                <i class="fas fa-calendar-check"></i><br>
                                Semua Sesi
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.tryout.results.index') }}" class="btn btn-outline-info w-100">
                                <i class="fas fa-chart-bar"></i><br>
                                Semua Hasil
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('tryout.packages.index') }}" class="btn btn-outline-secondary w-100" target="_blank">
                                <i class="fas fa-external-link-alt"></i><br>
                                View Guru
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
