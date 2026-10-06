@extends('layouts.app')

@section('title', 'Hasil Tryout')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📊 Hasil Tryout</h2>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Summary Card -->
    <div class="row mb-4">
        <div class="col-lg-4 mb-3">
            <div class="card border-{{ $result->lulus ? 'success' : 'danger' }} h-100">
                <div class="card-body text-center">
                    <h3 class="display-4 {{ $result->lulus ? 'text-success' : 'text-danger' }}">
                        {{ number_format($result->persentase, 1) }}
                    </h3>
                    <h5>Nilai Total</h5>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <h6 class="mb-0">{{ number_format($result->nilai_total, 1) }}</h6>
                            <small class="text-muted">Skor</small>
                        </div>
                        <div class="col-6">
                            <h6 class="mb-0">{{ number_format($result->nilai_maksimal, 1) }}</h6>
                            <small class="text-muted">Maksimal</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-{{ $result->lulus ? 'success' : 'danger' }} text-white text-center">
                    <h5 class="mb-0">
                        @if($result->lulus)
                        ✅ LULUS
                        @else
                        ❌ TIDAK LULUS
                        @endif
                    </h5>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">📋 Informasi</h5>
                    <hr>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td width="40%">Nama</td>
                            <td><strong>{{ $result->siswa->nama_peserta_didik }}</strong></td>
                        </tr>
                        <tr>
                            <td>Kelas</td>
                            <td>{{ $result->siswa->kelas }}</td>
                        </tr>
                        <tr>
                            <td>Paket</td>
                            <td>{{ $result->session->package->nama_paket }}</td>
                        </tr>
                        <tr>
                            <td>Peringkat</td>
                            <td><strong>{{ $result->peringkat ?? '-' }}</strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">⏱️ Waktu</h5>
                    <hr>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td width="40%">Mulai</td>
                            <td>{{ $result->waktu_mulai->format('d M Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td>Selesai</td>
                            <td>{{ $result->waktu_selesai->format('d M Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td>Durasi</td>
                            <td><strong>{{ floor($result->durasi_total_detik / 60) }} menit</strong></td>
                        </tr>
                        <tr>
                            <td>Passing Grade</td>
                            <td>{{ $result->session->package->passing_grade }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Nilai per Mapel -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📊 Detail Nilai per Mata Pelajaran</h5>
                </div>
                <div class="card-body">
                    <div class="table-wrapper">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Skor</th>
                                    <th>Maksimal</th>
                                    <th>Persentase</th>
                                    <th>Passing Grade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($result->detail_nilai_mapel as $index => $mapel)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $mapel['nama_mapel'] }}</strong></td>
                                    <td>{{ number_format($mapel['skor'], 1) }}</td>
                                    <td>{{ number_format($mapel['maksimal'], 1) }}</td>
                                    <td>
                                        <strong class="{{ $mapel['lulus'] ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($mapel['persentase'], 1) }}%
                                        </strong>
                                    </td>
                                    <td>{{ $result->session->package->subjects[$index]->passing_grade_mapel ?? 60 }}%</td>
                                    <td>
                                        @if($mapel['lulus'])
                                        <span class="badge bg-success">Lulus</span>
                                        @else
                                        <span class="badge bg-danger">Tidak Lulus</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analisis Kelemahan -->
    @if(count($result->analisis_kelemahan) > 0)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">⚠️ Area yang Perlu Ditingkatkan</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @foreach($result->analisis_kelemahan as $weak)
                        <li class="list-group-item">
                            <strong>{{ $weak['nama_mapel'] }}</strong>
                            <span class="badge bg-warning text-dark float-end">
                                {{ number_format($weak['persentase'], 1) }}%
                            </span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Rekomendasi -->
    @if($result->rekomendasi_pembelajaran)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">💡 Rekomendasi Pembelajaran</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">{{ $result->rekomendasi_pembelajaran }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Actions -->
    <div class="row">
        <div class="col-12">
            <a href="{{ route('tryout.student.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>
@endsection
