@extends('layouts.admin-with-tabs')

@section('title', 'Kelola Sesi Tryout - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Sesi Tryout')
@section('page_subtitle', 'Kelola sesi tryout')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📅 Kelola Sesi Tryout</h2>
            <p class="text-muted mb-0">Kelola semua sesi ujian tryout online</p>
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
                    <label class="form-label">Paket Tryout</label>
                    <select name="package_id" class="form-select">
                        <option value="">Semua Paket</option>
                        @foreach($packages as $package)
                        <option value="{{ $package->id }}" {{ request('package_id') == $package->id ? 'selected' : '' }}>
                            {{ $package->nama_paket }}
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
                    <label class="form-label">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" class="form-select">
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach($tahunAjarans as $ta)
                        <option value="{{ $ta->id }}" {{ request('tahun_ajaran_id') == $ta->id ? 'selected' : '' }}>
                            {{ $ta->tahun_ajaran }}
                        </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Cari</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama/Kode sesi" value="{{ request('search') }}">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.tryout.sessions.index') }}" class="btn btn-secondary">
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
                    <small>Total Sesi</small>
                    <h4 class="mb-0">{{ $sessions->total() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-info text-white">
                <div class="card-body py-2">
                    <small>Draft</small>
                    <h4 class="mb-0">{{ $sessions->where('status', 'draft')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <small>Published</small>
                    <h4 class="mb-0">{{ $sessions->where('status', 'published')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-warning">
                <div class="card-body py-2">
                    <small>Ongoing</small>
                    <h4 class="mb-0">{{ $sessions->where('status', 'ongoing')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <small>Completed</small>
                    <h4 class="mb-0">{{ $sessions->where('status', 'completed')->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-dark text-white">
                <div class="card-body py-2">
                    <small>Archived</small>
                    <h4 class="mb-0">{{ $sessions->where('status', 'archived')->count() }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('admin.tryout.sessions.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Sesi Baru
            </a>
            <a href="{{ route('admin.tryout.statistics') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Statistik
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
                            <th width="12%">Paket Tryout</th>
                            <th width="12%">Jadwal</th>
                            <th width="8%">Durasi</th>
                            <th width="6%">Peserta</th>
                            <th width="8%">Hasil</th>
                            <th width="8%">Pass Rate</th>
                            <th width="8%">Status</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        $statusColors = [
                            'draft' => 'secondary',
                            'published' => 'info',
                            'ongoing' => 'warning',
                            'completed' => 'success',
                            'archived' => 'dark',
                        ];
                        @endphp
                        @forelse($sessions as $index => $session)
                        <tr>
                            <td>{{ ($sessions->currentPage() - 1) * $sessions->perPage() + $index + 1 }}</td>
                            <td>
                                <strong>{{ $session->kode_sesi }}</strong><br>
                                <small>{{ $session->nama_sesi }}</small>
                            </td>
                            <td>
                                {{ $session->package->nama_paket ?? '-' }}<br>
                                <small class="text-muted">{{ $session->package->tipe_ujian ?? '-' }}</small>
                            </td>
                            <td>
                                <small>
                                    {{ $session->tanggal_mulai->format('d M Y H:i') }}<br>
                                    s/d {{ $session->tanggal_selesai->format('d M Y H:i') }}
                                </small>
                            </td>
                            <td>{{ $session->durasi_menit }} mnt</td>
                            <td>
                                <span class="badge bg-secondary">{{ $session->results->pluck('siswa_id')->unique()->count() }}</span>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $session->results->count() }}</span>
                            </td>
                            <td>
                                @php
                                $passRate = $session->results->count() > 0 
                                    ? ($session->results->where('lulus', true)->count() / $session->results->count() * 100) 
                                    : 0;
                                @endphp
                                <span class="badge bg-{{ $passRate >= 70 ? 'success' : ($passRate >= 50 ? 'warning' : 'danger') }}">
                                    {{ number_format($passRate, 1) }}%
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $statusColors[$session->status] ?? 'secondary' }}">
                                    {{ ucfirst($session->status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.tryout.sessions.show', $session) }}" 
                                   class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.tryout.sessions.edit', $session) }}" 
                                   class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if($session->status === 'ongoing')
                                <form action="{{ route('admin.tryout.sessions.force-complete', $session) }}" 
                                      method="POST" style="display:inline"
                                      onsubmit="return confirm('Yakin ingin memaksa menyelesaikan semua sesi yang sedang berjalan?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning" title="Force Complete">
                                        <i class="fas fa-stop"></i>
                                    </button>
                                </form>
                                @endif
                                @if($session->results->count() == 0 && $session->status === 'draft')
                                <form action="{{ route('admin.tryout.sessions.destroy', $session) }}" 
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
                                Tidak ada data sesi tryout
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

