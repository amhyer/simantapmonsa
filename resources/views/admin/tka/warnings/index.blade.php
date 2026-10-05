@extends('layouts.app')

@section('title', 'Kelola Early Warning TKA - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Early Warning TKA')
@section('page_subtitle', 'Pantau peringatan dini')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">⚠️ Kelola Early Warning TKA</h2>
            <p class="text-muted mb-0">Monitoring dan tindak lanjut peringatan dini siswa</p>
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
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="notified" {{ request('status') == 'notified' ? 'selected' : '' }}>Notified</option>
                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Tingkat Peringatan</label>
                    <select name="tingkat_peringatan" class="form-select">
                        <option value="">Semua Tingkat</option>
                        <option value="ringan" {{ request('tingkat_peringatan') == 'ringan' ? 'selected' : '' }}>Ringan</option>
                        <option value="sedang" {{ request('tingkat_peringatan') == 'sedang' ? 'selected' : '' }}>Sedang</option>
                        <option value="berat" {{ request('tingkat_peringatan') == 'berat' ? 'selected' : '' }}>Berat</option>
                        <option value="kritis" {{ request('tingkat_peringatan') == 'kritis' ? 'selected' : '' }}>Kritis</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Dimensi</label>
                    <select name="dimension_id" class="form-select">
                        <option value="">Semua Dimensi</option>
                        @foreach($dimensions as $dimension)
                        <option value="{{ $dimension->id }}" {{ request('dimension_id') == $dimension->id ? 'selected' : '' }}>
                            {{ $dimension->nama_dimensi }}
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
                    <a href="{{ route('admin.tka.warnings.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-3">
        <div class="col-md-2 mb-2">
            <div class="card bg-secondary text-white">
                <div class="card-body py-2">
                    <small>Total</small>
                    <h4 class="mb-0">{{ $warnings->total() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-warning">
                <div class="card-body py-2">
                    <small>Pending</small>
                    <h4 class="mb-0">{{ $warnings->where('status', 'pending')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-info text-white">
                <div class="card-body py-2">
                    <small>Notified</small>
                    <h4 class="mb-0">{{ $warnings->where('status', 'notified')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <small>In Progress</small>
                    <h4 class="mb-0">{{ $warnings->where('status', 'in_progress')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <small>Resolved</small>
                    <h4 class="mb-0">{{ $warnings->where('status', 'resolved')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-danger text-white">
                <div class="card-body py-2">
                    <small>Kritis</small>
                    <h4 class="mb-0">{{ $warnings->where('tingkat_peringatan', 'kritis')->count() }}</h4>
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

    <!-- Warnings Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="3%">No</th>
                            <th width="15%">Siswa</th>
                            <th width="15%">Dimensi</th>
                            <th width="10%">Tingkat</th>
                            <th width="10%">Tanggal</th>
                            <th width="12%">Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        $statusColors = [
                            'pending' => 'warning',
                            'notified' => 'info',
                            'in_progress' => 'primary',
                            'resolved' => 'success',
                        ];
                        $statusLabels = [
                            'pending' => 'Pending',
                            'notified' => 'Notified',
                            'in_progress' => 'In Progress',
                            'resolved' => 'Resolved',
                        ];
                        $tingkatColors = [
                            'ringan' => 'info',
                            'sedang' => 'warning',
                            'berat' => 'danger',
                            'kritis' => 'danger',
                        ];
                        @endphp
                        @forelse($warnings as $index => $warning)
                        <tr class="{{ $warning->tingkat_peringatan === 'kritis' ? 'table-danger' : '' }}">
                            <td>{{ ($warnings->currentPage() - 1) * $warnings->perPage() + $index + 1 }}</td>
                            <td>
                                <strong>{{ $warning->siswa->nama_peserta_didik ?? '-' }}</strong><br>
                                <small class="text-muted">{{ $warning->siswa->kelas ?? '-' }}</small>
                            </td>
                            <td>{{ $warning->dimension->nama_dimensi ?? '-' }}</td>
                            <td>
                                <span class="badge bg-{{ $tingkatColors[$warning->tingkat_peringatan] ?? 'secondary' }}">
                                    {{ ucfirst($warning->tingkat_peringatan) }}
                                </span>
                            </td>
                            <td>{{ $warning->created_at->format('d M Y') }}</td>
                            <td>
                                <span class="badge bg-{{ $statusColors[$warning->status] ?? 'secondary' }}">
                                    {{ $statusLabels[$warning->status] ?? $warning->status }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.tka.warnings.show', $warning) }}" 
                                   class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($warning->status !== 'resolved')
                                <form action="{{ route('admin.tka.warnings.update-status', $warning) }}" 
                                      method="POST" style="display:inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $warning->status === 'pending' ? 'notified' : ($warning->status === 'notified' ? 'in_progress' : 'resolved') }}">
                                    <button type="submit" class="btn btn-sm btn-success" title="Update Status">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                Tidak ada data early warning
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{ $warnings->links() }}
        </div>
    </div>
</div>
@endsection
