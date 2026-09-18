@extends('layouts.app')

@section('title', 'Ubah Materi - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Ubah Materi')
@section('page_subtitle', $materi->judul)

@section('header_actions')
    <a href="{{ route('guru.materi.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <form action="{{ route('guru.materi.update', $materi) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Informasi Materi</h3></div>
            <div class="card-body">
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Judul *</label>
                        <input type="text" name="judul" value="{{ old('judul', $materi->judul) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tanggal *</label>
                        <input type="date" name="tanggal" value="{{ old('tanggal', $materi->tanggal) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mata Pelajaran *</label>
                        <input type="text" name="mata_pelajaran" value="{{ old('mata_pelajaran', $materi->mata_pelajaran) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas *</label>
                        <input type="text" name="kelas" value="{{ old('kelas', $materi->kelas) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tautan Sumber</label>
                    <input type="url" name="tautan" value="{{ old('tautan', $materi->tautan_sumber) }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
        </div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Isi Materi</h3></div>
            <div class="card-body">
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tujuan Pembelajaran *</label>
                    <textarea name="tujuan" rows="3" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-size:14px">{{ old('tujuan', $materi->tujuan_pembelajaran) }}</textarea>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Materi Pokok *</label>
                    <textarea name="konten" rows="8" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-size:14px">{{ old('konten', $materi->materi_pokok) }}</textarea>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn">💾 Simpan</button>
            <a href="{{ route('guru.materi.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
@endsection
