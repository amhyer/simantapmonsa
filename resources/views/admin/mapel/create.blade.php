@extends('layouts.app')

@section('title', 'Tambah Mata Pelajaran - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Tambah Mata Pelajaran')
@section('page_subtitle', 'Jenjang: ' . $jenjangSekolah)

@section('content')
    <div class="card" style="max-width:600px">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle" style="margin-right:8px;color:var(--navy)"></i>Tambah Mata Pelajaran {{ $jenjangSekolah }}</h3>
        </div>
        <div class="card-body">
            @if(session('error'))
                <div class="note note-warn" style="margin-bottom:16px">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('admin.mapel.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Nama Mata Pelajaran <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                           value="{{ old('nama') }}" placeholder="Contoh: Bahasa Indonesia" required autofocus>
                    @error('nama')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Kode</label>
                    <input type="text" name="kode" class="form-control @error('kode') is-invalid @enderror"
                           value="{{ old('kode') }}" placeholder="Contoh: BIND (opsional)">
                    @error('kode')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div style="padding:10px 16px;background:#EEF2F9;border-radius:8px;font-size:13px;margin-bottom:16px">
                    <i class="fas fa-info-circle" style="color:var(--navy)"></i> Jenjang otomatis: <b>{{ $jenjangSekolah }}</b> (sesuai identitas sekolah)
                </div>

                <div style="display:flex;gap:10px;margin-top:20px">
                    <button type="submit" class="btn" style="background:var(--navy);color:#fff">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                    <a href="{{ route('admin.mapel.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
