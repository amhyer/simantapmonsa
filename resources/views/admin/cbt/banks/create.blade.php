@extends('layouts.admin-with-tabs')

@section('title', 'Buat Bank Soal CBT - Admin')

@section('page_title', 'Buat Bank Soal CBT')
@section('page_subtitle', 'Penugasan bank soal baru untuk guru pengampu')

@section('content')
<div style="max-width:700px;margin:0 auto">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-folder-plus" style="margin-right:8px;color:var(--navy)"></i>Formulir Pembuatan Bank Soal</h3>
            <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('admin.cbt.banks.store') }}">
                @csrf

                <div style="margin-bottom:20px">
                    <label class="form-label">
                        Judul Bank Soal <span style="color:var(--bad)">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           class="form-control" 
                           maxlength="200" 
                           placeholder="Contoh: Asesmen Sumatif Akhir Semester IPA Kelas 8" 
                           value="{{ old('title') }}" 
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
                           placeholder="Contoh: IPA / Matematika / Bahasa Indonesia" 
                           value="{{ old('subject') }}" 
                           required>
                </div>

                <div style="margin-bottom:24px">
                    <label class="form-label">
                        Guru Pemilik / Pengampu <span style="color:var(--bad)">*</span>
                    </label>
                    <select name="guru_id" class="form-control" required style="width:100%">
                        <option value="">— Pilih Guru Pengampu —</option>
                        @foreach($gurus as $guru)
                            <option value="{{ $guru->id }}" @selected(old('guru_id') == $guru->id)>
                                {{ $guru->nama_lengkap }} (NIP: {{ $guru->nip ?? '-' }})
                            </option>
                        @endforeach
                    </select>
                    <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">
                        Guru ini akan memiliki kewenangan mengisi dan mengedit butir soal pada bank ini.
                    </small>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:18px;border-top:1px solid var(--line);gap:12px">
                    <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary" style="padding:10px 24px">
                        <i class="fas fa-save"></i> Simpan Bank Soal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
