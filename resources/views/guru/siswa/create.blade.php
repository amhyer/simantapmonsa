@extends('layouts.app')

@section('title', 'Tambah Siswa - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Tambah Siswa')
@section('page_subtitle', 'Tambahkan peserta didik baru')

@section('header_actions')
    <a href="{{ route('guru.siswa.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('guru.siswa.store') }}" method="POST">
                @csrf
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Lengkap *</label>
                        <input type="text" name="nama" value="{{ old('nama') }}" required
                            style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('nama') <span style="color:#B42318;font-size:12px">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">NIS *</label>
                        <input type="text" name="nis" value="{{ old('nis') }}" required
                            style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('nis') <span style="color:#B42318;font-size:12px">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Jenis Kelamin *</label>
                        <select name="jenis_kelamin" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas *</label>
                        <input type="text" name="kelas" value="{{ old('kelas', auth()->user()->kelas_mata_pelajaran) }}" required
                            style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Orang Tua (opsional)</label>
                    <input type="text" name="nama_orang_tua" value="{{ old('nama_orang_tua') }}"
                        style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px"
                        placeholder="Kode akan dibuat otomatis jika kosong">
                </div>
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn">💾 Simpan</button>
                    <a href="{{ route('guru.siswa.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
