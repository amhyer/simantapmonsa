@extends('layouts.admin-with-tabs')

@section('title', 'Edit Bank Soal CBT - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Edit Bank Soal CBT')
@section('page_subtitle', $bank->title)

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">✏️ Edit Bank Soal CBT</h2>
            <p class="text-muted mb-0">Perbarui data dasar bank soal</p>
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
            <form method="POST" action="{{ route('admin.cbt.banks.update', $bank) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Judul Bank <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="200"
                           value="{{ old('title', $bank->title) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" maxlength="100"
                           value="{{ old('subject', $bank->subject) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Guru Pemilik <span class="text-danger">*</span></label>
                    <select name="guru_id" class="form-select" required>
                        @foreach($gurus as $guru)
                        <option value="{{ $guru->id }}" {{ old('guru_id', $bank->guru_id) == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama_lengkap }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
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
