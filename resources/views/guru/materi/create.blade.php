@extends('layouts.app')

@section('title', 'Tambah Materi - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Tambah Materi')
@section('page_subtitle', 'Buat materi pembelajaran baru')

@section('header_actions')
    <a href="{{ route('guru.materi.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    <form action="{{ route('guru.materi.store') }}" method="POST">
        @csrf
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Informasi Materi</h3></div>
            <div class="card-body">
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Judul *</label>
                        <input type="text" name="judul" value="{{ old('judul') }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tanggal *</label>
                        <input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mata Pelajaran *</label>
                        <input type="text" name="mata_pelajaran" value="{{ old('mata_pelajaran', auth()->user()->mata_pelajaran ?? '') }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas *</label>
                        <input type="text" name="kelas" value="{{ old('kelas', auth()->user()->kelas_mata_pelajaran) }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tautan Sumber (opsional)</label>
                    <input type="url" name="tautan" value="{{ old('tautan') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px" placeholder="https://...">
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Isi Materi</h3></div>
            <div class="card-body">
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tujuan Pembelajaran *</label>
                    <textarea name="tujuan" rows="3" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-size:14px">{{ old('tujuan') }}</textarea>
                </div>
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Materi Pokok *</label>
                    <textarea name="konten" rows="8" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-size:14px">{{ old('konten') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Pembelajaran Mendalam</h3></div>
            <div class="card-body">
                <div style="margin-bottom:12px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Pilih PM</label>
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <label style="display:flex;gap:6px;align-items:center;cursor:pointer"><input type="checkbox" name="pm[]" value="berkesadaran"> Berkesadaran</label>
                        <label style="display:flex;gap:6px;align-items:center;cursor:pointer"><input type="checkbox" name="pm[]" value="bermakna"> Bermakna</label>
                        <label style="display:flex;gap:6px;align-items:center;cursor:pointer"><input type="checkbox" name="pm[]" value="menggembirakan"> Menggembirakan</label>
                    </div>
                </div>
                <div style="margin-bottom:12px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Dimensi Profil Lulusan</label>
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        @foreach(['Keimanan dan Ketakwaan', 'Kewargaan', 'Penalaran Kritis', 'Kreativitas', 'Kolaborasi', 'Kemandirian', 'Kesehatan', 'Komunikasi'] as $i => $d)
                            <label style="display:flex;gap:6px;align-items:center;cursor:pointer"><input type="checkbox" name="dimensi[]" value="{{ $i }}"> {{ $d }}</label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <button type="submit" class="btn">💾 Simpan Materi</button>
            <a href="{{ route('guru.materi.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
@endsection
