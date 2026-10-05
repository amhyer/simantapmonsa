@extends('layouts.app')

@section('title', 'Manajemen Bank Soal CBT - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Bank Soal CBT')
@section('page_subtitle', 'Kelola semua bank soal')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">📚 Manajemen Bank Soal CBT</h2>
            <p class="text-muted mb-0">Kelola semua bank soal dari seluruh guru</p>
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
                    <label class="form-label">Semester</label>
                    <select name="semester_id" class="form-select">
                        <option value="">Semua Semester</option>
                        @foreach($semesters as $semester)
                        <option value="{{ $semester->id }}" {{ request('semester_id') == $semester->id ? 'selected' : '' }}>
                            {{ $semester->label }}
                        </option>
                        @endforeach
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
                    <label class="form-label">Status</label>
                    <select name="aktif" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="true" {{ request('aktif') === 'true' ? 'selected' : '' }}>Aktif</option>
                        <option value="false" {{ request('aktif') === 'false' ? 'selected' : '' }}>Non-Aktif</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Tingkat Kesulitan</label>
                    <select name="tingkat_kesulitan" class="form-select">
                        <option value="">Semua Tingkat</option>
                        <option value="mudah" {{ request('tingkat_kesulitan') == 'mudah' ? 'selected' : '' }}>Mudah</option>
                        <option value="sedang" {{ request('tingkat_kesulitan') == 'sedang' ? 'selected' : '' }}>Sedang</option>
                        <option value="sulit" {{ request('tingkat_kesulitan') == 'sulit' ? 'selected' : '' }}>Sulit</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Cari</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama/Kode bank" value="{{ request('search') }}">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="{{ route('admin.cbt.banks.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Bank Soal
            </a>
        </div>
    </div>

    <!-- Banks Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="3%">No</th>
                            <th width="12%">Kode/Nama Bank</th>
                            <th width="12%">Mata Pelajaran</th>
                            <th width="10%">Semester</th>
                            <th width="10%">Guru</th>
                            <th width="8%">Jumlah Soal</th>
                            <th width="8%">Tingkat</th>
                            <th width="6%">Status</th>
                            <th width="8%">Digunakan</th>
                            <th width="23%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($banks as $index => $bank)
                        <tr>
                            <td>{{ ($banks->currentPage() - 1) * $banks->perPage() + $index + 1 }}</td>
                            <td>
                                <strong>{{ $bank->kode_bank }}</strong><br>
                                <small>{{ $bank->nama_bank }}</small>
                            </td>
                            <td>{{ $bank->mataPelajaran->nama_mata_pelajaran ?? '-' }}</td>
                            <td>{{ $bank->semester->label ?? '-' }}</td>
                            <td>{{ $bank->guru->nama_lengkap ?? '-' }}</td>
                            <td>
                                <span class="badge bg-primary">{{ $bank->questions_count ?? 0 }}</span>
                            </td>
                            <td>
                                @php
                                $tingkatColors = ['mudah' => 'success', 'sedang' => 'warning', 'sulit' => 'danger'];
                                @endphp
                                <span class="badge bg-{{ $tingkatColors[$bank->tingkat_kesulitan] ?? 'secondary' }}">
                                    {{ ucfirst($bank->tingkat_kesulitan) }}
                                </span>
                            </td>
                            <td>
                                @if($bank->aktif)
                                <span class="badge bg-success">Aktif</span>
                                @else
                                <span class="badge bg-secondary">Non-Aktif</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $bank->testSessions()->count() }} sesi</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.cbt.banks.show', $bank) }}" 
                                   class="btn btn-sm btn-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.cbt.banks.edit', $bank) }}" 
                                   class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <form action="{{ route('admin.cbt.banks.toggle-status', $bank) }}" 
                                      method="POST" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-{{ $bank->aktif ? 'secondary' : 'success' }}" 
                                            title="{{ $bank->aktif ? 'Non-Aktifkan' : 'Aktifkan' }}">
                                        <i class="fas fa-{{ $bank->aktif ? 'toggle-off' : 'toggle-on' }}"></i>
                                    </button>
                                </form>
                                
                                @if($bank->testSessions()->count() == 0)
                                <form action="{{ route('admin.cbt.banks.destroy', $bank) }}" 
                                      method="POST" style="display:inline"
                                      onsubmit="return confirm('Yakin ingin menghapus bank soal ini?')">
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
                                Tidak ada data bank soal
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{ $banks->links() }}
        </div>
    </div>
</div>
@endsection
