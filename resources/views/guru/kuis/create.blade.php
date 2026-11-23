@extends('layouts.app')

@section('title', 'Buat Kuis - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Buat Kuis')
@section('page_subtitle', 'Susun asesmen baru')

@section('header_actions')
    <a href="{{ route('guru.kuis.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <form action="{{ route('guru.kuis.store') }}" method="POST">
        @csrf
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Informasi Kuis</h3></div>
            <div class="card-body">
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-create-judul" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Judul *</label>
                        <input id="fld-kuis-create-judul" type="text" name="judul" value="{{ old('judul') }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('judul') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="fld-kuis-create-tanggal" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tanggal *</label>
                        <input id="fld-kuis-create-tanggal" type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('tanggal') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-create-mata-pelajaran" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mata Pelajaran *</label>
                        <input id="fld-kuis-create-mata-pelajaran" type="text" name="mata_pelajaran" value="{{ old('mata_pelajaran') }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('mata_pelajaran') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="fld-kuis-create-kelas" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas *</label>
                        <input id="fld-kuis-create-kelas" type="text" name="kelas" value="{{ old('kelas', auth()->user()->kelas_mata_pelajaran) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        @error('kelas') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-create-jenis" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Jenis *</label>
                        <select id="fld-kuis-create-jenis" name="jenis" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            @foreach(['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS'] as $j)
                                <option value="{{ $j }}">{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fld-kuis-create-kkm" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">KKM *</label>
                        <input id="fld-kuis-create-kkm" type="number" name="kkm" value="{{ old('kkm', $pengaturan->kkm ?? 70) }}" min="0" max="100" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-create-mode" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mode *</label>
                        <select id="fld-kuis-create-mode" name="mode" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="harian">Harian</option>
                            <option value="tka">TKA</option>
                        </select>
                    </div>
                    <div>
                        <label for="fld-kuis-create-sumber" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Sumber *</label>
                        <select id="fld-kuis-create-sumber" name="sumber" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="lokal">Lokal</option>
                            <option value="luar">Luar</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label for="fld-kuis-create-batas-waktu" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Batas Waktu Pengumpulan</label>
                        <input id="fld-kuis-create-batas-waktu" type="datetime-local" name="batas_waktu" value="{{ old('batas_waktu') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        <div style="font-size:11px;color:#667085;margin-top:4px">Waktu tutup kuis. Kosongkan bila tanpa batas waktu.</div>
                    </div>
                    <div>
                        <label for="fld-kuis-create-cara-nilai" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Cara Nilai *</label>
                        <select id="fld-kuis-create-cara-nilai" name="cara_nilai" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="siswa">Dikerjakan Siswa</option>
                            <option value="guru">Dinilai Guru</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="fld-kuis-create-tautan" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tautan Soal (opsional)</label>
                    <input id="fld-kuis-create-tautan" type="url" name="tautan" value="{{ old('tautan') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px" placeholder="https://...">
                </div>
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn">💾 Simpan Kuis</button>
            <a href="{{ route('guru.kuis.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
@endsection
