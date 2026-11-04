@extends('layouts.app')
@section('title', ($bank->exists ? 'Edit Bank Soal' : 'Buat Bank Soal') . ' - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', $bank->exists ? 'Ubah Identitas Bank Soal' : 'Buat Bank Soal Baru')
@section('page_subtitle', 'Computer Based Test • Pilihan ganda')

@section('content')
@include('cbt.feedback')

<div style="max-width:700px;margin:0 auto">
    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-{{ $bank->exists ? 'pen-to-square' : 'folder-plus' }}" style="margin-right:8px;color:var(--navy)"></i>
                {{ $bank->exists ? 'Form Edit Bank Soal' : 'Form Pembuatan Bank Soal' }}
            </h3>
            <a href="{{ route('cbt.question-banks.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ $bank->exists ? route('cbt.question-banks.update', $bank) : route('cbt.question-banks.store') }}">
                @csrf
                @if($bank->exists)
                    @method('PUT')
                @endif

                <div style="margin-bottom:20px">
                    <label for="title" class="form-label">
                        Nama / Judul Bank Soal <span style="color:var(--bad)">*</span>
                    </label>
                    <input id="title" 
                           type="text" 
                           name="title" 
                           class="form-control" 
                           maxlength="200" 
                           required 
                           placeholder="Contoh: Penilaian Harian Matematika Bab 2 - Aljabar" 
                           value="{{ old('title', $bank->title) }}">
                    <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                        Gunakan judul yang jelas dan deskriptif untuk memudahkan pemilihan saat membuat sesi ujian.
                    </small>
                </div>

                <div style="margin-bottom:24px">
                    <label for="subject" class="form-label">
                        Mata Pelajaran <span style="color:var(--bad)">*</span>
                    </label>
                    <input id="subject" 
                           type="text" 
                           name="subject" 
                           class="form-control" 
                           maxlength="100" 
                           required 
                           placeholder="Contoh: Matematika / Bahasa Indonesia / IPA" 
                           value="{{ old('subject', $bank->subject) }}">
                    <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                        Nama mata pelajaran yang sesuai kurikulum.
                    </small>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:16px;border-top:1px solid var(--line);gap:12px">
                    <a href="{{ route('cbt.question-banks.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> {{ $bank->exists ? 'Simpan Perubahan' : 'Buat Bank Soal' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
