@extends('layouts.admin-with-tabs')

@section('title', 'Kelola Penilaian TKA - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Penilaian TKA')
@section('page_subtitle', 'Kelola data penilaian')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📋 Kelola Penilaian TKA</h2>
            <p class="text-muted mb-0">Kelola semua penilaian Profil Pelajar Pancasila</p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Status Kesiapan</label>
                    <select name="status_kesiapan" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="sangat_siap" {{ request('status_kesiapan') == 'sangat_siap' ? 'selected' : '' }}>Sangat Siap</option>
                        <option value="siap" {{ request('status_kesiapan') == 'siap' ? 'selected' : '' }}>Siap</option>
                        <option value="perlu_bimbingan" {{ request('status_kesiapan') == 'perlu_bimbingan' ? 'selected' : '' }}>Perlu Bimbingan</option>
                        <option value="belum_siap" {{ request('status_kesiapan') == 'belum_siap' ? 'selected' : '' }}>Belum Siap</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Dimensi</label>
                    <select name="dimension_id" class="form-select">
                        <option value="">Semua Dimensi</option>
                        @foreach($dimensions ?? [] as $dimension)
                        <option value="{{ $dimension->id }}" {{ request('dimension_id') == $dimension->id ? 'selected' : '' }}>
                            {{ $dimension->nama_dimensi }}
                        </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Semester</label>
                    <select name="semester_id" class="form-select">
                        <option value="">Semua Semester</option>
                        @foreach($semesters ?? [] as $semester)
                        <option value="{{ $semester->id }}" {{ request('semester_id') == $semester->id ? 'selected' : '' }}>
                            {{ $semester->tahun_ajaran }}
                        </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="tanggal_from" class="form-control" value="{{ request('tanggal_from') }}">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="tanggal_to" class="form-control" value="{{ request('tanggal_to') }}">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Cari Siswa</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama siswa" value="{{ request('search') }}">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.tka.assessments.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <small>Sangat Siap</small>
                    <h4 class="mb-0">{{ $assessments->where('status_kesiapan', 'sangat_siap')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <small>Siap</small>
                    <h4 class="mb-0">{{ $assessments->where('status_kesiapan', 'siap')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-warning">
                <div class="card-body py-2">
                    <small>Perlu Bimbingan</small>
                    <h4 class="mb-0">{{ $assessments->where('status_kesiapan', 'perlu_bimbingan')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-danger text-white">
                <div class="card-body py-2">
                    <small>Belum Siap</small>
                    <h4 class="mb-0">{{ $assessments->where('status_kesiapan', 'belum_siap')->count() }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('admin.tka.dashboard') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Assessments Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="3%">No</th>
                            <th width="15%">Siswa</th>
                            <th width="15%">Dimensi</th>
                            <th width="10%">Tanggal</th>
                            <th width="10%">Skor</th>
                            <th width="12%">Status Kesiapan</th>
                            <th width="10%">Semester</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
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
                        @forelse($assessments as $index => $assessment)
                        <tr>
                            <td>{{ ($assessments->currentPage() - 1) * $assessments->perPage() + $index + 1 }}</td>
                            <td>
                                <strong>{{ $assessment->siswa->nama_peserta_didik ?? '-' }}</strong><br>
                                <small class="text-muted">{{ $assessment->siswa->kelas ?? '-' }}</small>
                            </td>
                            <td>
                                @php
                                $firstResponse = $assessment->responses->first();
                                echo $firstResponse?->assessmentItem?->dimension?->nama_dimensi ?? '-';
                                @endphp
                            </td>
                            <td>-</td>
                            <td>{{ $assessment->tanggal_penilaian ? $assessment->tanggal_penilaian->format('d M Y') : '-' }}</td>
                            <td>{{ number_format($assessment->total_score ?? $assessment->skor_akhir ?? 0, 1) }}</td>
                            <td>
                                <span class="badge bg-{{ $statusColors[$assessment->status_kesiapan] ?? 'secondary' }}">
                                    {{ $statusLabels[$assessment->status_kesiapan] ?? $assessment->status_kesiapan }}
                                </span>
                            </td>
                            <td>{{ $assessment->semester->tahun_ajaran ?? '-' }}</td>
                            <td>
                                {{-- Rute admin.tka.assessments.show/edit tidak ada (hanya index+history);
                                     tautkan ke riwayat penilaian per siswa. --}}
                                <a href="{{ route('admin.tka.assessments.history', ['siswaId' => $assessment->siswa_id]) }}"
                                   class="btn btn-sm btn-info" title="Riwayat Siswa">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                Tidak ada data penilaian
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{ $assessments->links() }}
        </div>
    </div>
</div>
@endsection

