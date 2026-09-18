@extends('layouts.app')

@section('title', 'Input Nilai Baru - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Input Nilai Baru')
@section('page_subtitle', 'Tambahkan penilaian baru')

@section('content')
<div class="card" style="max-width:700px">
    <div class="card-header"><h3>📝 Form Input Nilai</h3><a href="{{ route('guru.nilai.index') }}" class="btn btn-ghost btn-sm">← Kembali</a></div>
    <div class="card-body">
        <form method="POST" action="{{ route('guru.nilai.store') }}">
            @csrf
            <div style="margin-bottom:14px">
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Siswa *</label>
                <select name="siswa_id" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                    <option value="">Pilih Siswa</option>
                    @foreach($siswa as $s)
                        <option value="{{ $s->id }}">{{ $s->nama_peserta_didik }} ({{ $s->kelas }})</option>
                    @endforeach
                </select>
                @error('siswa_id') <span style="color:#DC2626;font-size:12px">{{ $message }}</span> @enderror
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Jenis *</label>
                    <select name="jenis" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        @foreach(['Tugas','Ulangan Harian','Praktik','PTS','PAS'] as $j)
                            <option value="{{ $j }}">{{ $j }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Tanggal *</label>
                    <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Judul Penilaian *</label>
                <input type="text" name="judul_penilaian" required placeholder="Contoh: Ulangan Harian Bab 1" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
            <div style="margin-bottom:14px">
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Mata Pelajaran *</label>
                <input type="text" name="mata_pelajaran" required placeholder="Contoh: Matematika" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
            <div style="margin-bottom:14px">
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Nilai (0-100) *</label>
                <input type="number" name="nilai" min="0" max="100" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
            <div style="display:flex;gap:8px">
                <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">💾 Simpan Nilai</button>
                <a href="{{ route('guru.nilai.index') }}" class="btn btn-ghost btn-sm">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
