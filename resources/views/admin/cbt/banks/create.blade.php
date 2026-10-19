@extends('layouts.admin-with-tabs')

@section('title', 'Buat Bank Soal CBT - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Buat Bank Soal CBT')
@section('page_subtitle', 'Bank soal baru untuk seluruh guru')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">➕ Buat Bank Soal CBT</h2>
            <p class="text-muted mb-0">Isi data dasar bank soal; soal diisi guru pemilik setelahnya</p>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.cbt.banks.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Judul Bank <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="200"
                           value="{{ old('title') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" maxlength="100"
                           value="{{ old('subject') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Guru Pemilik <span class="text-danger">*</span></label>
                    <select name="guru_id" class="form-select" required>
                        <option value="">— Pilih Guru —</option>
                        @foreach($gurus as $guru)
                        <option value="{{ $guru->id }}" {{ old('guru_id') == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama_lengkap }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                    <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
