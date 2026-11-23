@extends('layouts.app')

@section('title', 'Buat Kelas Digital')
@section('sidebar') @include('guru.partials.sidebar') @endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Buat Kelas Digital Baru</h1>
        <p class="page-subtitle">Tautkan kelas digital ke rombel dan mata pelajaran agar siswa otomatis melihat materi dan tugas yang sesuai.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('guru.kelas-digital.index') }}" class="btn btn-outline btn-sm">Kembali</a>
    </div>
</div>

<div class="card" style="max-width:760px">
    <div class="card-body">
        <form method="POST" action="{{ route('guru.kelas-digital.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="nama_kelas">Nama Kelas Digital <span style="color:var(--bad)">*</span></label>
                <input id="nama_kelas" type="text" name="nama_kelas" class="form-input" value="{{ old('nama_kelas') }}" placeholder="Contoh: Matematika Kelas 6A — Semester Ganjil" required>
                @error('nama_kelas') <p style="color:var(--bad);font-size:.78rem;margin-top:.25rem">{{ $message }}</p> @enderror
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
                <div class="form-group">
                    <label class="form-label" for="mata_pelajaran">Mata Pelajaran <span style="color:var(--bad)">*</span></label>
                    <input id="mata_pelajaran" list="daftar-mapel-kd" type="text" name="mata_pelajaran" class="form-input" value="{{ old('mata_pelajaran') }}" placeholder="Contoh: Matematika" required>
                    <datalist id="daftar-mapel-kd">
                        @foreach($mapelOptions as $mapel)
                            <option value="{{ $mapel }}"></option>
                        @endforeach
                    </datalist>
                    @error('mata_pelajaran') <p style="color:var(--bad);font-size:.78rem;margin-top:.25rem">{{ $message }}</p> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="rombel_id">Rombel (Disarankan)</label>
                    <select id="rombel_id" name="rombel_id" class="form-select">
                        <option value="">-- Pilih Rombel Sekolah --</option>
                        @foreach($rombelList as $rombel)
                            <option value="{{ $rombel->id }}" @selected(old('rombel_id') == $rombel->id)>
                                {{ $rombel->nama_rombel }} {{ $rombel->tingkat ? '(Tingkat '.$rombel->tingkat.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('rombel_id') <p style="color:var(--bad);font-size:.78rem;margin-top:.25rem">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="kelas">Label Kelas (otomatis mengikuti Rombel jika dikosongkan)</label>
                <input id="kelas" type="text" name="kelas" class="form-input" value="{{ old('kelas') }}" placeholder="Contoh: Kelas 6A">
                @error('kelas') <p style="color:var(--bad);font-size:.78rem;margin-top:.25rem">{{ $message }}</p> @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="deskripsi">Deskripsi Singkat Kelas</label>
                <textarea id="deskripsi" name="deskripsi" rows="3" class="form-textarea" placeholder="Gambaran capaian belajar, aturan pengumpulan tugas, atau informasi kelas.">{{ old('deskripsi') }}</textarea>
                @error('deskripsi') <p style="color:var(--bad);font-size:.78rem;margin-top:.25rem">{{ $message }}</p> @enderror
            </div>

            <div class="form-group" style="display:flex;align-items:center;gap:.5rem">
                <input type="hidden" name="aktif" value="0">
                <input id="aktif" type="checkbox" name="aktif" value="1" @checked(old('aktif', true))>
                <label for="aktif" style="font-size:.875rem;color:#334155">Aktifkan kelas agar langsung tampil di akun siswa</label>
            </div>

            <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1.25rem">
                <a href="{{ route('guru.kelas-digital.index') }}" class="btn btn-outline">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Kelas Digital</button>
            </div>
        </form>
    </div>
</div>
@endsection
