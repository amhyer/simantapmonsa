@extends('layouts.app')

@section('title', 'Tambah Akun - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Tambah Akun')
@section('page_subtitle', 'Buat akun pengguna baru')

@section('header_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('nama_lengkap') <span style="color:#dc3545;font-size:12px">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Username *</label>
                        <input type="text" name="nama_pengguna" value="{{ old('nama_pengguna') }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('nama_pengguna') <span style="color:#dc3545;font-size:12px">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kata Sandi *</label>
                        <input type="password" name="kata_sandi" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('kata_sandi') <span style="color:#dc3545;font-size:12px">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Peran *</label>
                        <select name="peran" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="guru">Guru</option>
                            <option value="siswa">Siswa</option>
                            <option value="ortu">Orang Tua</option>
                            <option value="kepsek">Kepala Sekolah</option>
                        </select>
                        @error('peran') <span style="color:#dc3545;font-size:12px">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas / Mata Pelajaran</label>
                    <input type="text" name="kelas_mata_pelajaran" value="{{ old('kelas_mata_pelajaran') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn">💾 Simpan</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
