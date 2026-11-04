@extends('layouts.admin-with-tabs')

@section('title', 'Edit Bank Soal CBT - Admin')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Ubah Bank Soal CBT')
@section('page_subtitle', $bank->title)

@section('content')
<div style="max-width:700px;margin:0 auto">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-edit" style="margin-right:8px;color:var(--navy)"></i>Ubah Identitas Bank Soal</h3>
            <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.cbt.banks.update', $bank) }}">
                @csrf
                @method('PUT')

                <div style="margin-bottom:20px">
                    <label class="form-label">
                        Judul Bank Soal <span style="color:var(--bad)">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           class="form-control" 
                           maxlength="200" 
                           value="{{ old('title', $bank->title) }}" 
                           required>
                </div>

                <div style="margin-bottom:20px">
                    <label class="form-label">
                        Mata Pelajaran <span style="color:var(--bad)">*</span>
                    </label>
                    <input type="text" 
                           name="subject" 
                           class="form-control" 
                           maxlength="100" 
                           value="{{ old('subject', $bank->subject) }}" 
                           required>
                </div>

                <div style="margin-bottom:24px">
                    <label class="form-label">
                        Guru Pemilik / Pengampu <span style="color:var(--bad)">*</span>
                    </label>
                    <select name="guru_id" class="form-control" required style="width:100%">
                        @foreach($gurus as $guru)
                            <option value="{{ $guru->id }}" @selected(old('guru_id', $bank->guru_id) == $guru->id)>
                                {{ $guru->nama_lengkap }} (NIP: {{ $guru->nip ?? '-' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:18px;border-top:1px solid var(--line);gap:12px">
                    <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary" style="padding:10px 24px">
                        <i class="fas fa-save"></i> Perbarui Bank Soal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
