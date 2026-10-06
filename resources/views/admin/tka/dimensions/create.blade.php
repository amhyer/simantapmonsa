@extends('layouts.admin-with-tabs')

@section('title', 'Tambah Dimensi TKA - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Tambah Dimensi TKA')
@section('page_subtitle', 'Dimensi penilaian baru')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Dimensi</h3>
            <a href="{{ route('admin.tka.dimensions.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.tka.dimensions.store') }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                    <div>
                        <label class="form-label">Kode Dimensi</label>
                        <input type="text" name="kode_dimensi" value="{{ old('kode_dimensi') }}" maxlength="20" required class="form-control">
                        @error('kode_dimensi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Dimensi</label>
                        <input type="text" name="nama_dimensi" value="{{ old('nama_dimensi') }}" maxlength="255" required class="form-control">
                        @error('nama_dimensi')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Kategori</label>
                    <select name="kategori" required class="form-control">
                        @foreach(['akhlak_beragama' => 'Akhlak Beragama', 'kebinekaan_global' => 'Kebinekaan Global', 'berkebhinekaan' => 'Berkebinekaan', 'mandiri' => 'Mandiri', 'bernalar_kritis' => 'Bernalar Kritis', 'kreatif' => 'Kreatif'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('kategori') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('kategori')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="form-control">{{ old('deskripsi') }}</textarea>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Dimensi</button>
                </div>
            </form>
        </div>
    </div>
@endsection

