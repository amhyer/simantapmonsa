@extends('layouts.app')

@section('title', 'Ubah Kelas Digital')
@section('sidebar') @include('guru.partials.sidebar') @endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Ubah Kelas Digital</h1>
        <p class="page-subtitle">{{ $kelasDigital->nama_kelas }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('guru.kelas-digital.show', $kelasDigital) }}" class="btn btn-outline btn-sm">Kembali ke Kelas</a>
    </div>
</div>

<div class="card" style="max-width:760px">
    <div class="card-body">
        <form method="POST" action="{{ route('guru.kelas-digital.update', $kelasDigital) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="nama_kelas">Nama Kelas Digital <span style="color:#dc2626">*</span></label>
                <input id="nama_kelas" type="text" name="nama_kelas" class="form-input" value="{{ old('nama_kelas', $kelasDigital->nama_kelas) }}" required>
                @error('nama_kelas') <p style="color:#dc2626;font-size:.78rem;margin-top:.25rem">{{ $message }}</p> @enderror
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
                <div class="form-group">
                    <label class="form-label" for="mata_pelajaran">Mata Pelajaran <span style="color:#dc2626">*</span></label>
                    <input id="mata_pelajaran" list="daftar-mapel-kd" type="text" name="mata_pelajaran" class="form-input" value="{{ old('mata_pelajaran', $kelasDigital->mata_pelajaran) }}" required>
                    <datalist id="daftar-mapel-kd">
                        @foreach($mapelOptions as $mapel)
                            <option value="{{ $mapel }}"></option>
                        @endforeach
                    </datalist>
                </div>

                <div class="form-group">
                    <label class="form-label" for="rombel_id">Rombel</label>
                    <select id="rombel_id" name="rombel_id" class="form-select">
                        <option value="">-- Pilih Rombel Sekolah --</option>
                        @foreach($rombelList as $rombel)
                            <option value="{{ $rombel->id }}" @selected(old('rombel_id', $kelasDigital->rombel_id) == $rombel->id)>
                                {{ $rombel->nama_rombel }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="kelas">Label Kelas</label>
                <input id="kelas" type="text" name="kelas" class="form-input" value="{{ old('kelas', $kelasDigital->kelas) }}">
            </div>

            <div class="form-group">
                <label class="form-label" for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" rows="3" class="form-textarea">{{ old('deskripsi', $kelasDigital->deskripsi) }}</textarea>
            </div>

            <div class="form-group" style="display:flex;align-items:center;gap:.5rem">
                <input type="hidden" name="aktif" value="0">
                <input id="aktif" type="checkbox" name="aktif" value="1" @checked(old('aktif', $kelasDigital->aktif))>
                <label for="aktif" style="font-size:.875rem;color:#334155">Kelas aktif (tampil di akun siswa)</label>
            </div>

            <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1.25rem">
                <a href="{{ route('guru.kelas-digital.show', $kelasDigital) }}" class="btn btn-outline">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
