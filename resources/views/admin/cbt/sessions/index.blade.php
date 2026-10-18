@extends('layouts.admin-with-tabs')

@section('title', 'Manajemen Sesi Ujian CBT - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Sesi Ujian CBT')
@section('page_subtitle', 'Kelola semua sesi ujian')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📋 Manajemen Sesi Ujian CBT</h2>
            <p class="text-muted mb-0">Kelola semua sesi ujian CBT dari seluruh guru</p>
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
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>Published</option>
                        <option value="ongoing" {{ request('status') == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                </div>
                
                <div class="col-md-2">
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
                
                <div class="col-md-2">
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        @foreach($kelasList as $kelas)
                        <option value="{{ $kelas }}" {{ request('kelas') == $kelas ? 'selected' : '' }}>
                            {{ $kelas }}
                        </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="tanggal_mulai_from" class="form-control" value="{{ request('tanggal_mulai_from') }}">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="tanggal_mulai_to" class="form-control" value="{{ request('tanggal_mulai_to') }}">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Cari</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama/Kode sesi" value="{{ request('search') }}">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('admin.cbt.sessions.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Sesi Baru
            </a>
        </div>
    </div>

    <!-- Sessions Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="3%">No</th>
                            <th width="15%">Kode/Nama Sesi</th>
                            <th width="12%">Bank Soal</th>
                            <th width="10%">Guru</th>
                            <th width="8%">Kelas</th>
                            <th width="12%">Jadwal</th>
                            <th width="8%">Durasi</th>
                            <th width="7%">Peserta</th>
                            <th width="8%">Status</th>
                            <th width="17%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $index => $session)
                        <tr>
                            <td>{{ ($sessions->currentPage() - 1) * $sessions->perPage() + $index + 1 }}</td>
                            <td>
                                <strong>{{ $session->kode_sesi }}</strong><br>
                                <small>{{ $session->nama_sesi }}</small>
                            </td>
                            <td>
                                {{ $session->questionBank->nama_bank ?? '-' }}<br>
                                <small class="text-muted">{{ $session->mataPelajaran->nama_mata_pelajaran ?? '-' }}</small>
                            </td>
                            <td>{{ $session->guru->nama_lengkap ?? '-' }}</td>
                            <td>
                                @if($session->rombel)
                                {{ $session->rombel->nama_rombel }}
                                @elseif($session->kelas)
                                {{ $session->kelas }}
                                @else
                                <span class="badge bg-info">Semua</span>
                                @endif
                            </td>
                            <td>
                                <small>
                                    {{ $session->tanggal_mulai->format('d M H:i') }}<br>
                                    s/d {{ $session->tanggal_selesai->format('d M H:i') }}
                                </small>
                            </td>
                            <td>{{ $session->durasi_menit }} menit</td>
                            <td>
                                <span class="badge bg-secondary">{{ $session->test_results_count ?? 0 }}</span>
                            </td>
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
                            <td>
                                <a href="{{ route('admin.cbt.sessions.show', $session) }}" 
                                   class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                @if($session->status === 'ongoing')
                                <form action="{{ route('admin.cbt.sessions.force-complete', $session) }}" 
                                      method="POST" style="display:inline"
                                      onsubmit="return confirm('Yakin ingin memaksa menyelesaikan semua sesi yang sedang berjalan?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning" title="Force Complete">
                                        <i class="fas fa-stop"></i>
                                    </button>
                                </form>
                                @endif
                                
                                @if($session->test_results_count == 0)
                                <form action="{{ route('admin.cbt.sessions.destroy', $session) }}" 
                                      method="POST" style="display:inline"
                                      onsubmit="return confirm('Yakin ingin menghapus sesi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                Tidak ada data sesi ujian
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

