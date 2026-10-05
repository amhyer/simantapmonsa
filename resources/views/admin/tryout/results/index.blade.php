@extends('layouts.app')

@section('title', 'Kelola Hasil Tryout - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Hasil Tryout')
@section('page_subtitle', 'Lihat semua hasil tryout')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📊 Kelola Hasil Tryout</h2>
            <p class="text-muted mb-0">Monitoring dan analisis hasil ujian tryout</p>
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
                    <label class="form-label">Status Kelulusan</label>
                    <select name="lulus" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="true" {{ request('lulus') === 'true' ? 'selected' : '' }}>Lulus</option>
                        <option value="false" {{ request('lulus') === 'false' ? 'selected' : '' }}>Tidak Lulus</option>
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
                    <label class="form-label">Sesi</label>
                    <select name="session_id" class="form-select">
                        <option value="">Semua Sesi</option>
                        @foreach($sessions as $session)
                        <option value="{{ $session->id }}" {{ request('session_id') == $session->id ? 'selected' : '' }}>
                            {{ $session->nama_sesi }}
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
                    <a href="{{ route('admin.tryout.results.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                    <button type="button" class="btn btn-success" onclick="exportResults()">
                        <i class="fas fa-file-export"></i> Export Excel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-3">
        <div class="col-md-2 mb-2">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <small>Total Hasil</small>
                    <h4 class="mb-0">{{ $results->total() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <small>Lulus</small>
                    <h4 class="mb-0">{{ $results->where('lulus', true)->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-danger text-white">
                <div class="card-body py-2">
                    <small>Tidak Lulus</small>
                    <h4 class="mb-0">{{ $results->where('lulus', false)->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-info text-white">
                <div class="card-body py-2">
                    <small>Rata-rata Skor</small>
                    <h4 class="mb-0">{{ number_format($results->avg('skor_total') ?? 0, 1) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-warning">
                <div class="card-body py-2">
                    <small>Skor Tertinggi</small>
                    <h4 class="mb-0">{{ number_format($results->max('skor_total') ?? 0, 1) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-secondary text-white">
                <div class="card-body py-2">
                    <small>Skor Terendah</small>
                    <h4 class="mb-0">{{ number_format($results->min('skor_total') ?? 0, 1) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('admin.tryout.statistics') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Statistik
            </a>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="3%">No</th>
                            <th width="12%">Siswa</th>
                            <th width="10%">Sesi</th>
                            <th width="10%">Paket</th>
                            <th width="8%">Skor</th>
                            <th width="8%">Benar</th>
                            <th width="8%">Salah</th>
                            <th width="8%">Kosong</th>
                            <th width="8%">Waktu</th>
                            <th width="8%">Status</th>
                            <th width="9%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($results as $index => $result)
                        <tr>
                            <td>{{ ($results->currentPage() - 1) * $results->perPage() + $index + 1 }}</td>
                            <td>
                                <strong>{{ $result->siswa->nama_peserta_didik ?? '-' }}</strong><br>
                                <small class="text-muted">{{ $result->siswa->kelas ?? '-' }}</small>
                            </td>
                            <td>{{ $result->session->nama_sesi ?? '-' }}</td>
                            <td>{{ $result->session->package->nama_paket ?? '-' }}</td>
                            <td>
                                <span class="badge bg-{{ $result->lulus ? 'success' : 'danger' }}">
                                    {{ number_format($result->skor_total, 1) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success">{{ $result->jumlah_benar }}</span>
                            </td>
                            <td>
                                <span class="badge bg-danger">{{ $result->jumlah_salah }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $result->jumlah_kosong }}</span>
                            </td>
                            <td>
                                @if($result->waktu_pengerjaan_detik)
                                {{ floor($result->waktu_pengerjaan_detik / 60) }} m {{ $result->waktu_pengerjaan_detik % 60 }} d
                                @else
                                -
                                @endif
                            </td>
                            <td>
                                @if($result->lulus)
                                <span class="badge bg-success">Lulus</span>
                                @else
                                <span class="badge bg-danger">Tidak Lulus</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.tryout.results.show', $result) }}" 
                                   class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted">
                                Tidak ada data hasil tryout
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{ $results->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function exportResults() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '{{ route('admin.tryout.results.export') }}?' + params.toString();
}
</script>
@endpush
