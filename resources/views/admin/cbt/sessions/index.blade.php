@extends('layouts.admin-with-tabs')

@section('title', 'Sesi Ujian CBT - Admin')

@section('page_title', 'Sesi Ujian CBT')
@section('page_subtitle', 'Monitoring seluruh sesi ujian')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">🗓️ Sesi Ujian CBT</h2>
            <p class="text-muted mb-0">Seluruh sesi ujian dari seluruh guru</p>
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
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        @foreach(['draft', 'published', 'running', 'completed'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Guru</label>
                    <select name="guru_id" class="form-select">
                        <option value="">Semua Guru</option>
                        @foreach($gurus as $guru)
                        <option value="{{ $guru->id }}" {{ request('guru_id') == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama_lengkap }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-secondary ms-2">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('admin.cbt.sessions.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Sesi Ujian
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="4%">No</th>
                            <th width="22%">Judul</th>
                            <th width="10%">Kelas</th>
                            <th width="14%">Guru</th>
                            <th width="14%">Bank Soal</th>
                            <th width="10%">Status</th>
                            <th width="8%">Peserta</th>
                            <th width="8%">Hasil</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $index => $session)
                        <tr>
                            <td>{{ ($sessions->currentPage() - 1) * $sessions->perPage() + $index + 1 }}</td>
                            <td><strong>{{ $session->title }}</strong></td>
                            <td>{{ $session->kelas }}</td>
                            <td>{{ $session->guru->nama_lengkap ?? '-' }}</td>
                            <td>{{ $session->bank->title ?? '-' }}</td>
                            <td>
                                @php
                                $statusColors = ['draft' => 'secondary', 'published' => 'info', 'running' => 'primary', 'completed' => 'success'];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$session->status] ?? 'secondary' }}">{{ ucfirst($session->status) }}</span>
                            </td>
                            <td>{{ count($session->participant_ids ?? []) }}</td>
                            <td><span class="badge bg-primary">{{ $session->results_count ?? 0 }}</span></td>
                            <td>
                                <a href="{{ route('admin.cbt.sessions.show', $session) }}"
                                   class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if(($session->results_count ?? 0) > 0)
                                <a href="{{ route('admin.cbt.sessions.export', [$session, 'csv']) }}"
                                   class="btn btn-sm btn-success" title="Ekspor CSV">
                                    <i class="fas fa-file-csv"></i>
                                </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                Tidak ada sesi ujian
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $sessions->links() }}
        </div>
    </div>
</div>
@endsection
