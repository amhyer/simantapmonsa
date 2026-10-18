@extends('layouts.app')

@section('title', 'Laporan TKA untuk Orang Tua')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="mb-0">📋 Laporan Perkembangan Anak</h2>
        </div>
    </div>

    @if(!$report->dibaca_oleh_ortu)
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> Laporan baru saja dibaca.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Student Info -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">👤 Informasi Siswa</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td width="40%">Nama</td>
                            <td><strong>{{ $report->siswa->nama_peserta_didik }}</strong></td>
                        </tr>
                        <tr>
                            <td>Kelas</td>
                            <td>{{ $report->siswa->kelas }}</td>
                        </tr>
                        <tr>
                            <td>Semester</td>
                            <td>{{ $report->assessment->semester->label ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Tanggal</td>
                            <td>{{ $report->assessment->tanggal_penilaian->format('d M Y') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">🎯 Status Kesiapan</h5>
                </div>
                <div class="card-body text-center">
                    <h2 class="display-3 mb-3
                        @if($report->assessment->status_kesiapan === 'sangat_siap') text-success
                        @elseif($report->assessment->status_kesiapan === 'siap') text-primary
                        @elseif($report->assessment->status_kesiapan === 'perlu_bimbingan') text-warning
                        @else text-danger @endif">
                        @if($report->assessment->status_kesiapan === 'sangat_siap') ⭐⭐⭐⭐
                        @elseif($report->assessment->status_kesiapan === 'siap') ⭐⭐⭐
                        @elseif($report->assessment->status_kesiapan === 'perlu_bimbingan') ⭐⭐
                        @else ⭐ @endif
                    </h2>
                    <h4 class="text-capitalize mb-0">
                        {{ str_replace('_', ' ', $report->assessment->status_kesiapan) }}
                    </h4>
                    <p class="text-muted mb-0 mt-2">
                        Persentase: {{ number_format($report->assessment->persentase, 1) }}%
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Score per Dimension -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📊 Skor per Dimensi Profil Pelajar Pancasila</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($report->assessment->skor_per_dimensi as $dimensi)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100
                                @if($dimensi['total_skor'] / $dimensi['maksimal'] >= 0.75) border-success
                                @elseif($dimensi['total_skor'] / $dimensi['maksimal'] >= 0.5) border-warning
                                @else border-danger @endif">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <span class="badge bg-secondary">{{ $dimensi['kategori'] }}</span>
                                    </h6>
                                    <h6 class="mb-2">{{ $dimensi['nama_dimensi'] }}</h6>
                                    
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar 
                                            @if($dimensi['total_skor'] / $dimensi['maksimal'] >= 0.75) bg-success
                                            @elseif($dimensi['total_skor'] / $dimensi['maksimal'] >= 0.5) bg-warning
                                            @else bg-danger @endif"
                                            role="progressbar"
                                            style="width: {{ ($dimensi['total_skor'] / $dimensi['maksimal'] * 100) }}%">
                                            {{ number_format($dimensi['total_skor'] / $dimensi['maksimal'] * 100, 0) }}%
                                        </div>
                                    </div>
                                    
                                    <small class="text-muted">
                                        Skor: {{ number_format($dimensi['total_skor'], 1) }} / 
                                        {{ number_format($dimensi['maksimal'], 1) }}
                                    </small>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekomendasi -->
    @if($report->rekomendasi_rumah)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">💡 Rekomendasi untuk Orang Tua</h5>
                </div>
                <div class="card-body">
                    <p style="white-space: pre-wrap;">{{ $report->rekomendasi_rumah }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Suggested Activities -->
    @if($report->aktivitas_suggested && count($report->aktivitas_suggested) > 0)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">🏃 Aktivitas yang Disarankan di Rumah</h5>
                </div>
                <div class="card-body">
                    @foreach($report->aktivitas_suggested as $aktivitas)
                    <div class="mb-3">
                        <h6 class="mb-2">
                            <span class="badge bg-secondary">{{ $aktivitas['kategori'] }}</span>
                            {{ $aktivitas['dimension'] }}
                        </h6>
                        <ul class="list-group list-group-flush">
                            @foreach($aktivitas['activities'] as $activity)
                            <li class="list-group-item">
                                <i class="fas fa-check-circle text-success"></i> {{ $activity }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Actions -->
    <div class="row">
        <div class="col-12">
            <a href="{{ route('tka.parent.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>
@endsection
