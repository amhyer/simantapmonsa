@extends('layouts.app')

@section('title', 'Edit Mata Pelajaran - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Edit Mata Pelajaran')
@section('page_subtitle', $mapel->nama)

@section('content')
    <div class="card" style="max-width:600px">
        <div class="card-header">
            <h3><i class="fas fa-edit" style="margin-right:8px;color:var(--navy)"></i>Edit Mata Pelajaran</h3>
            <form action="{{ route('admin.mapel.destroy', $mapel) }}" method="POST" onsubmit="return confirm('Hapus mata pelajaran ini?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash"></i> Hapus
                </button>
            </form>
        </div>
        <div class="card-body">
            @if(session('error'))
                <div class="note note-warn" style="margin-bottom:16px">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('admin.mapel.update', $mapel) }}" method="POST">
                @csrf @method('PUT')
                <div class="form-group">
                    <label class="form-label">Nama Mata Pelajaran <span style="color:var(--bad)">*</span></label>
                    <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                           value="{{ old('nama', $mapel->nama) }}" required>
                    @error('nama')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Kode</label>
                    <input type="text" name="kode" class="form-control @error('kode') is-invalid @enderror"
                           value="{{ old('kode', $mapel->kode) }}">
                    @error('kode')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div style="padding:10px 16px;background:#EEF2F9;border-radius:8px;font-size:13px;margin-bottom:16px">
                    <i class="fas fa-info-circle" style="color:var(--navy)"></i> Jenjang: <b>{{ $mapel->jenjang }}</b>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div style="display:flex;gap:16px;padding:12px 16px;background:#FAFBFD;border:1px solid #E4E7EC;border-radius:8px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                            <input type="radio" name="aktif" value="1" {{ old('aktif', $mapel->aktif) ? 'checked' : '' }}> Aktif
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                            <input type="radio" name="aktif" value="0" {{ old('aktif', $mapel->aktif) ? '' : 'checked' }}> Nonaktif
                        </label>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:20px">
                    <button type="submit" class="btn" style="background:var(--navy);color:#fff">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                    <a href="{{ route('admin.mapel.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
