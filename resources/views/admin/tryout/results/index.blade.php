@extends('layouts.admin-with-tabs')

@section('title', 'Kelola Hasil Tryout - Admin')

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
                    <h4 class="mb-0">{{ $ringkasan['total'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <small>Lulus</small>
                    <h4 class="mb-0">{{ $ringkasan['lulus'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-danger text-white">
                <div class="card-body py-2">
                    <small>Tidak Lulus</small>
                    <h4 class="mb-0">{{ $ringkasan['tidak_lulus'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-info text-white">
                <div class="card-body py-2">
                    <small>Rata-rata Skor</small>
                    <h4 class="mb-0">{{ number_format($ringkasan['rata'], 1) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-warning">
                <div class="card-body py-2">
                    <small>Skor Tertinggi</small>
                    <h4 class="mb-0">{{ number_format($ringkasan['tertinggi'], 1) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="card bg-secondary text-white">
                <div class="card-body py-2">
                    <small>Skor Terendah</small>
                    <h4 class="mb-0">{{ number_format($ringkasan['terendah'], 1) }}</h4>
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
                        @forelse($hasil as $index => $baris)
                        <tr>
                            <td>{{ ($hasil->currentPage() - 1) * $hasil->perPage() + $index + 1 }}</td>
                            <td>
                                <strong>{{ $baris['siswa_nama'] }}</strong><br>
                                <small class="text-muted">{{ $baris['siswa_kelas'] }}</small>
                            </td>
                            <td>{{ $baris['sesi_nama'] }}</td>
                            <td>{{ $baris['paket_nama'] }}</td>
                            <td>
                                <span class="badge bg-{{ $baris['lulus'] ? 'success' : 'danger' }}">
                                    {{ number_format($baris['skor_total'], 1) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success">{{ $baris['jumlah_benar'] }}</span>
                            </td>
                            <td>
                                <span class="badge bg-danger">{{ $baris['jumlah_salah'] }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $baris['jumlah_kosong'] }}</span>
                            </td>
                            <td>
                                @if($baris['waktu_detik'])
                                {{ floor($baris['waktu_detik'] / 60) }} m {{ $baris['waktu_detik'] % 60 }} d
                                @else
                                -
                                @endif
                            </td>
                            <td>
                                @if($baris['lulus'])
                                <span class="badge bg-success">Lulus</span>
                                @else
                                <span class="badge bg-danger">Tidak Lulus</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ $baris['detail_url'] }}"
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
            
            {{ $hasil->links() }}
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

