@extends('layouts.admin-with-tabs')

@section('title', 'Tambah Bank Soal - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Tambah Bank Soal')
@section('page_subtitle', 'Bank soal CBT baru')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Bank Soal</h3>
            <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.cbt.banks.store') }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Kode Bank</label>
                        <input type="text" name="kode_bank" value="{{ old('kode_bank') }}" maxlength="50" required class="form-control">
                        @error('kode_bank')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Bank</label>
                        <input type="text" name="nama_bank" value="{{ old('nama_bank') }}" maxlength="255" required class="form-control">
                        @error('nama_bank')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Mata Pelajaran</label>
                        <select name="mata_pelajaran_id" required class="form-control">
                            <option value="">— Pilih —</option>
                            @foreach($mataPelajarans as $m)
                                <option value="{{ $m->id }}" @selected(old('mata_pelajaran_id') == $m->id)>{{ $m->nama }}</option>
                            @endforeach
                        </select>
                        @error('mata_pelajaran_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Semester</label>
                        <select name="semester_id" required class="form-control">
                            <option value="">— Pilih —</option>
                            @foreach($semesters as $s)
                                <option value="{{ $s->id }}" @selected(old('semester_id') == $s->id)>{{ $s->label }}</option>
                            @endforeach
                        </select>
                        @error('semester_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Guru Pengampu</label>
                        <select name="guru_id" required class="form-control">
                            <option value="">— Pilih —</option>
                            @foreach($gurus as $g)
                                <option value="{{ $g->id }}" @selected(old('guru_id') == $g->id)>{{ $g->nama_lengkap }}</option>
                            @endforeach
                        </select>
                        @error('guru_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Tingkat Kesulitan</label>
                        <select name="tingkat_kesulitan" required class="form-control">
                            @foreach(['mudah', 'sedang', 'sulit'] as $t)
                                <option value="{{ $t }}" @selected(old('tingkat_kesulitan', 'sedang') === $t)>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Kategori Soal</label>
                        <input type="text" name="kategori_soal" value="{{ old('kategori_soal') }}" maxlength="100" class="form-control">
                    </div>
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="form-control">{{ old('deskripsi') }}</textarea>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Bank Soal</button>
                </div>
            </form>
        </div>
    </div>
@endsection

