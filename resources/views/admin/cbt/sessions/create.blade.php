@extends('layouts.admin-with-tabs')

@section('title', 'Buat Sesi Ujian CBT - Admin')

@section('page_title', 'Buat Sesi Ujian CBT')
@section('page_subtitle', 'Sesi baru (status draf)')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">➕ Buat Sesi Ujian CBT</h2>
            <p class="text-muted mb-0">
                Sesi dibuat sebagai draf. Soal & peserta dibekukan saat publikasi;
                pastikan guru dan kelas sesuai dengan data siswa (wali kelas = guru).
            </p>
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
            <form method="POST" action="{{ route('admin.cbt.sessions.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Guru <span class="text-danger">*</span></label>
                    <select name="guru_id" class="form-select" required>
                        <option value="">— Pilih Guru —</option>
                        @foreach($gurus as $guru)
                        <option value="{{ $guru->id }}" {{ old('guru_id') == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama_lengkap }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Bank Soal <span class="text-danger">*</span></label>
                    <select name="bank_id" class="form-select" required>
                        <option value="">— Pilih Bank Soal —</option>
                        @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                            {{ $bank->title }} — {{ $bank->subject }} ({{ $bank->guru->nama_lengkap ?? '-' }})
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Judul Sesi <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="200"
                           value="{{ old('title') }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Kelas <span class="text-danger">*</span></label>
                    <input type="text" name="kelas" class="form-control" maxlength="50"
                           value="{{ old('kelas') }}" placeholder="cth: 6.A" required>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Buka <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="opens_at" class="form-control"
                               value="{{ old('opens_at') }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tutup <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="closes_at" class="form-control"
                               value="{{ old('closes_at') }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Durasi (menit) <span class="text-danger">*</span></label>
                        <input type="number" name="duration_minutes" class="form-control"
                               min="1" max="300" value="{{ old('duration_minutes', 60) }}" required>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Draf
                    </button>
                    <a href="{{ route('admin.cbt.sessions.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
