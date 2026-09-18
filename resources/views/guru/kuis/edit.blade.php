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
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Judul *</label>
                        <input type="text" name="judul" value="{{ old('judul', $kuis->judul) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tanggal *</label>
                        <input type="date" name="tanggal" value="{{ old('tanggal', $kuis->tanggal) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mata Pelajaran *</label>
                        <input type="text" name="mata_pelajaran" value="{{ old('mata_pelajaran', $kuis->mata_pelajaran) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas *</label>
                        <input type="text" name="kelas" value="{{ old('kelas', $kuis->kelas) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Jenis *</label>
                        <select name="jenis" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            @foreach(['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS'] as $j)
                                <option value="{{ $j }}" {{ $kuis->jenis === $j ? 'selected' : '' }}>{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">KKM *</label>
                        <input type="number" name="kkm" value="{{ old('kkm', $kuis->kkm) }}" min="0" max="100" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mode *</label>
                        <select name="mode" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="harian" {{ $kuis->mode === 'harian' ? 'selected' : '' }}>Harian</option>
                            <option value="tka" {{ $kuis->mode === 'tka' ? 'selected' : '' }}>TKA</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Sumber *</label>
                        <select name="sumber" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="lokal" {{ $kuis->sumber === 'lokal' ? 'selected' : '' }}>Lokal</option>
                            <option value="luar" {{ $kuis->sumber === 'luar' ? 'selected' : '' }}>Luar</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Batas Waktu (menit)</label>
                        <input type="number" name="batas_waktu" value="{{ old('batas_waktu', $kuis->batas_waktu) }}" min="0" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Cara Nilai *</label>
                        <select name="cara_nilai" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="siswa" {{ $kuis->cara_nilai === 'siswa' ? 'selected' : '' }}>Dikerjakan Siswa</option>
                            <option value="guru" {{ $kuis->cara_nilai === 'guru' ? 'selected' : '' }}>Dinilai Guru</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tautan Soal</label>
                    <input type="url" name="tautan" value="{{ old('tautan', $kuis->tautan_soal) }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn">💾 Simpan</button>
            <a href="{{ route('guru.kuis.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
@endsection
