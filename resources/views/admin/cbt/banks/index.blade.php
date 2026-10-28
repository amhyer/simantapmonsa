@extends('layouts.admin-with-tabs')

@section('title', 'Manajemen Bank Soal CBT - Admin')

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

                <div class="col-md-4">
                    <label class="form-label">Cari (judul / mata pelajaran)</label>
                    <input type="text" name="q" class="form-control" placeholder="Judul atau mata pelajaran" value="{{ request('q') }}">
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary ms-2">
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
                            <th width="4%">No</th>
                            <th width="30%">Judul</th>
                            <th width="18%">Mata Pelajaran</th>
                            <th width="18%">Guru</th>
                            <th width="10%">Jumlah Soal</th>
                            <th width="20%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($banks as $index => $bank)
                        <tr>
                            <td>{{ ($banks->currentPage() - 1) * $banks->perPage() + $index + 1 }}</td>
                            <td><strong>{{ $bank->title }}</strong></td>
                            <td>{{ $bank->subject }}</td>
                            <td>{{ $bank->guru->nama_lengkap ?? '-' }}</td>
                            <td>
                                <span class="badge bg-primary">{{ $bank->questions_count ?? 0 }}</span>
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

                                @if(($bank->sessions_count ?? $bank->sessions()->count()) == 0)
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
                            <td colspan="6" class="text-center text-muted">
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
