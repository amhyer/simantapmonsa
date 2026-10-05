@extends('layouts.app')

@section('title', 'Kelola Paket Tryout - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Paket Tryout')
@section('page_subtitle', 'Kelola paket tryout')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📦 Kelola Paket Tryout</h2>
            <p class="text-muted mb-0">Kelola semua paket tryout online</p>
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
                    <label class="form-label">Jenjang</label>
                    <select name="jenjang" class="form-select">
                        <option value="">Semua Jenjang</option>
                        <option value="SD" {{ request('jenjang') == 'SD' ? 'selected' : '' }}>SD</option>
                        <option value="SMP" {{ request('jenjang') == 'SMP' ? 'selected' : '' }}>SMP</option>
                        <option value="SMA" {{ request('jenjang') == 'SMA' ? 'selected' : '' }}>SMA</option>
                        <option value="SMK" {{ request('jenjang') == 'SMK' ? 'selected' : '' }}>SMK</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Tipe Ujian</label>
                    <select name="tipe_ujian" class="form-select">
                        <option value="">Semua Tipe</option>
                        <option value="USBN" {{ request('tipe_ujian') == 'USBN' ? 'selected' : '' }}>USBN</option>
                        <option value="UNBK" {{ request('tipe_ujian') == 'UNBK' ? 'selected' : '' }}>UNBK</option>
                        <option value="AKM" {{ request('tipe_ujian') == 'AKM' ? 'selected' : '' }}>AKM</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="aktif" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="true" {{ request('aktif') === 'true' ? 'selected' : '' }}>Aktif</option>
                        <option value="false" {{ request('aktif') === 'false' ? 'selected' : '' }}>Non-Aktif</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Mata Pelajaran</label>
                    <select name="mata_pelajaran_id" class="form-select">
                        <option value="">Semua Mapel</option>
                        @foreach($mataPelajarans as $mapel)
                        <option value="{{ $mapel->id }}" {{ request('mata_pelajaran_id') == $mapel->id ? 'selected' : '' }}>
                            {{ $mapel->nama_mata_pelajaran }}
                        </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select">
                        <option value="">Semua Tahun</option>
                        @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                        <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Cari</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama/Kode paket" value="{{ request('search') }}">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.tryout.packages.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <small>Total Paket</small>
                    <h4 class="mb-0">{{ $packages->total() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <small>Paket Aktif</small>
                    <h4 class="mb-0">{{ $packages->where('aktif', true)->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-info text-white">
                <div class="card-body py-2">
                    <small>Total Soal</small>
                    <h4 class="mb-0">{{ $packages->sum('total_soal') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-warning">
                <div class="card-body py-2">
                    <small>Sesi Terkait</small>
                    <h4 class="mb-0">{{ $packages->sum(function($p) { return $p->sessions->count(); }) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('admin.tryout.packages.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Paket
            </a>
            <a href="{{ route('admin.tryout.statistics') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Statistik
            </a>
        </div>
    </div>

    <!-- Packages Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="3%">No</th>
                            <th width="15%">Kode/Nama Paket</th>
                            <th width="10%">Mata Pelajaran</th>
                            <th width="8%">Jenjang</th>
                            <th width="8%">Tipe</th>
                            <th width="6%">Tahun</th>
                            <th width="8%">Soal</th>
                            <th width="8%">Waktu</th>
                            <th width="6%">KKM</th>
                            <th width="6%">Status</th>
                            <th width="8%">Sesi</th>
                            <th width="14%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($packages as $index => $package)
                        <tr>
                            <td>{{ ($packages->currentPage() - 1) * $packages->perPage() + $index + 1 }}</td>
                            <td>
                                <strong>{{ $package->kode_paket }}</strong><br>
                                <small>{{ $package->nama_paket }}</small>
                            </td>
                            <td>{{ $package->mataPelajaran->nama_mata_pelajaran ?? '-' }}</td>
                            <td>
                                <span class="badge bg-primary">{{ $package->jenjang }}</span>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $package->tipe_ujian }}</span>
                            </td>
                            <td>{{ $package->tahun }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ $package->total_soal }}</span>
                            </td>
                            <td>{{ $package->waktu_menit }} mnt</td>
                            <td>{{ $package->kkm }}</td>
                            <td>
                                @if($package->aktif)
                                <span class="badge bg-success">Aktif</span>
                                @else
                                <span class="badge bg-secondary">Non-Aktif</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-warning">{{ $package->sessions->count() }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.tryout.packages.show', $package) }}" 
                                   class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.tryout.packages.edit', $package) }}" 
                                   class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.tryout.packages.toggle-status', $package) }}" 
                                      method="POST" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-{{ $package->aktif ? 'secondary' : 'success' }}" 
                                            title="{{ $package->aktif ? 'Non-Aktifkan' : 'Aktifkan' }}">
                                        <i class="fas fa-{{ $package->aktif ? 'toggle-off' : 'toggle-on' }}"></i>
                                    </button>
                                </form>
                                @if($package->sessions->count() == 0)
                                <form action="{{ route('admin.tryout.packages.destroy', $package) }}" 
                                      method="POST" style="display:inline"
                                      onsubmit="return confirm('Yakin ingin menghapus paket ini?')">
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
                            <td colspan="12" class="text-center text-muted">
                                Tidak ada data paket tryout
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{ $packages->links() }}
        </div>
    </div>
</div>
@endsection
