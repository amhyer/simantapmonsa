@extends('layouts.app')

@section('title', 'Ubah Kuis - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Ubah Kuis')
@section('page_subtitle', $kuis->judul)

@section('header_actions')
    <a href="{{ route('guru.kuis.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <form action="{{ route('guru.kuis.update', $kuis) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Informasi Kuis</h3></div>
            <div class="card-body">
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-edit-judul" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Judul *</label>
                        <input id="fld-kuis-edit-judul" type="text" name="judul" value="{{ old('judul', $kuis->judul) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label for="fld-kuis-edit-tanggal" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tanggal *</label>
                        <input id="fld-kuis-edit-tanggal" type="date" name="tanggal" value="{{ old('tanggal', $kuis->tanggal) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-edit-mata-pelajaran" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mata Pelajaran *</label>
                        <input id="fld-kuis-edit-mata-pelajaran" type="text" name="mata_pelajaran" value="{{ old('mata_pelajaran', $kuis->mata_pelajaran) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label for="fld-kuis-edit-kelas" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas *</label>
                        <input id="fld-kuis-edit-kelas" type="text" name="kelas" value="{{ old('kelas', $kuis->kelas) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-edit-jenis" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Jenis *</label>
                        <select id="fld-kuis-edit-jenis" name="jenis" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            @foreach(['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS'] as $j)
                                <option value="{{ $j }}" {{ $kuis->jenis === $j ? 'selected' : '' }}>{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fld-kuis-edit-kkm" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">KKM *</label>
                        <input id="fld-kuis-edit-kkm" type="number" name="kkm" value="{{ old('kkm', $kuis->kkm) }}" min="0" max="100" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-edit-mode" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mode *</label>
                        <select id="fld-kuis-edit-mode" name="mode" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="harian" {{ $kuis->mode === 'harian' ? 'selected' : '' }}>Harian</option>
                            <option value="tka" {{ $kuis->mode === 'tka' ? 'selected' : '' }}>TKA</option>
                        </select>
                    </div>
                    <div>
                        <label for="fld-kuis-edit-sumber" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Sumber *</label>
                        <select id="fld-kuis-edit-sumber" name="sumber" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="lokal" {{ $kuis->sumber === 'lokal' ? 'selected' : '' }}>Lokal</option>
                            <option value="luar" {{ $kuis->sumber === 'luar' ? 'selected' : '' }}>Luar</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-edit-batas-waktu" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Batas Waktu Pengumpulan</label>
                        <input id="fld-kuis-edit-batas-waktu" type="datetime-local" name="batas_waktu" value="{{ old('batas_waktu', $kuis->batas_waktu?->format('Y-m-d\TH:i')) }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        <div style="font-size:11px;color:#667085;margin-top:4px">Waktu tutup kuis. Kosongkan bila tanpa batas waktu.</div>
                    </div>
                    <div>
                        <label for="fld-kuis-edit-cara-nilai" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Cara Nilai *</label>
                        <select id="fld-kuis-edit-cara-nilai" name="cara_nilai" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="siswa" {{ $kuis->cara_nilai === 'siswa' ? 'selected' : '' }}>Dikerjakan Siswa</option>
                            <option value="guru" {{ $kuis->cara_nilai === 'guru' ? 'selected' : '' }}>Dinilai Guru</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="fld-kuis-edit-tautan" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tautan Soal</label>
                    <input id="fld-kuis-edit-tautan" type="url" name="tautan" value="{{ old('tautan', $kuis->tautan_soal) }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn">💾 Simpan</button>
            <a href="{{ route('guru.kuis.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
@endsection
