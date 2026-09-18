@extends('layouts.app')

@section('title', 'Pengaturan - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Pengaturan Guru')
@section('page_subtitle', 'Atur kelas dan pengaturan pembelajaran')

@section('content')
    <div class="card">
        <div class="card-header"><h3>Pengaturan Kelas</h3></div>
        <div class="card-body">
            <form action="{{ route('guru.pengaturan.update') }}" method="POST">
                @csrf
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mata Pelajaran</label>
                        <input type="text" name="mata_pelajaran" value="{{ old('mata_pelajaran', $pengaturan->mata_pelajaran ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kelas</label>
                        <input type="text" name="kelas" value="{{ old('kelas', $pengaturan->kelas ?? auth()->user()->kelas_mata_pelajaran) }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">KKM</label>
                        <input type="number" name="kkm" value="{{ old('kkm', $pengaturan->kkm ?? 70) }}" min="0" max="100" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Semester</label>
                        <select name="semester" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="Ganjil" {{ ($pengaturan->semester ?? '') === 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                            <option value="Genap" {{ ($pengaturan->semester ?? '') === 'Genap' ? 'selected' : '' }}>Genap</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tahun Pelajaran</label>
                        <input type="text" name="tahun_pelajaran" value="{{ old('tahun_pelajaran', $pengaturan->tahun_pelajaran ?? date('Y') . '/' . (date('Y') + 1)) }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Fase</label>
                        <input type="text" name="fase" value="{{ old('fase', $pengaturan->fase ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <button type="submit" class="btn">💾 Simpan Pengaturan</button>
            </form>
        </div>
    </div>
@endsection
