@extends('layouts.app')

@section('title', 'Ubah Siswa - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Ubah Siswa')
@section('page_subtitle', 'Perbarui data ' . $siswa->nama_peserta_didik)

@section('header_actions')
    <a href="{{ route('guru.siswa.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('guru.siswa.update', $siswa) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Lengkap *</label>
                        <input type="text" name="nama" value="{{ old('nama', $siswa->nama_peserta_didik) }}" required
                            style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">NIS *</label>
                        <input type="text" name="nis" value="{{ old('nis', $siswa->nis) }}" required
                            style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Jenis Kelamin *</label>
                        <select name="jenis_kelamin" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="L" {{ $siswa->jenis_kelamin === 'L' ? 'selected' : '' }}>Laki-laki</option>
                            <option value="P" {{ $siswa->jenis_kelamin === 'P' ? 'selected' : '' }}>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas *</label>
                        <input type="text" name="kelas" value="{{ old('kelas', $siswa->kelas) }}" required
                            style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Orang Tua</label>
                    <input type="text" name="nama_orang_tua" value="{{ old('nama_orang_tua', $siswa->nama_orang_tua) }}"
                        style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn">💾 Simpan</button>
                    <a href="{{ route('guru.siswa.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
