@extends('layouts.admin-with-tabs')

@section('title', 'Kelola Dimensi TKA - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Dimensi TKA')
@section('page_subtitle', 'Kelola dimensi P5')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">🎯 Kelola Dimensi TKA</h2>
            <p class="text-muted mb-0">Kelola dimensi Profil Pelajar Pancasila untuk penilaian TKA</p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Dimensi</h6>
                            <h2 class="mb-0">{{ $dimensions->count() }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-th-large fa-2x text-primary opacity-25"></i>
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
                            <h6 class="text-muted mb-2">Dimensi Aktif</h6>
                            <h2 class="mb-0">{{ $dimensions->where('aktif', true)->count() }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-3">
            <div class="card border-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Item Penilaian</h6>
                            <h2 class="mb-0">{{ $dimensions->sum('assessment_items_count') }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-list-check fa-2x text-info opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('admin.tka.dimensions.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Dimensi
            </a>
            <a href="{{ route('admin.tka.dashboard') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Dimensions Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="3%">No</th>
                            <th width="20%">Nama Dimensi</th>
                            <th width="10%">Kode</th>
                            <th width="30%">Deskripsi</th>
                            <th width="15%">Item Penilaian</th>
                            <th width="10%">Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dimensions as $index => $dimension)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $dimension->nama_dimensi }}</strong>
                            </td>
                            <td>
                                <span class="badge bg-primary">{{ $dimension->kode_dimensi }}</span>
                            </td>
                            <td>
                                <small>{{ Str::limit($dimension->deskripsi, 100) }}</small>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $dimension->assessment_items_count ?? 0 }}</span>
                            </td>
                            <td>
                                @if($dimension->aktif)
                                <span class="badge bg-success">Aktif</span>
                                @else
                                <span class="badge bg-secondary">Non-Aktif</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.tka.dimensions.show', $dimension) }}" 
                                   class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.tka.dimensions.edit', $dimension) }}" 
                                   class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.tka.dimensions.toggle-status', $dimension) }}" 
                                      method="POST" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-{{ $dimension->aktif ? 'secondary' : 'success' }}" 
                                            title="{{ $dimension->aktif ? 'Non-Aktifkan' : 'Aktifkan' }}">
                                        <i class="fas fa-{{ $dimension->aktif ? 'toggle-off' : 'toggle-on' }}"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                Tidak ada data dimensi
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

