@extends('layouts.admin-with-tabs')

@section('title', 'Tambah Paket Tryout - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Tambah Paket Tryout')
@section('page_subtitle', 'Paket soal tryout baru')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Form Paket Tryout</h3>
            <a href="{{ route('admin.tryout.packages.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.tryout.packages.store') }}" method="POST" style="display:grid;gap:12px;max-width:640px">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px">
                    <div>
                        <label class="form-label">Kode Paket</label>
                        <input type="text" name="kode_paket" value="{{ old('kode_paket') }}" maxlength="50" required class="form-control">
                        @error('kode_paket')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nama Paket</label>
                        <input type="text" name="nama_paket" value="{{ old('nama_paket') }}" maxlength="255" required class="form-control">
                        @error('nama_paket')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Jenjang</label>
                        <select name="jenjang" required class="form-control">
                            @foreach(['SD', 'SMP', 'SMA', 'SMK'] as $j)
                                <option value="{{ $j }}" @selected(old('jenjang', 'SD') === $j)>{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Tipe Ujian</label>
                        <select name="tipe_ujian" required class="form-control">
                            @foreach(['USBN', 'UNBK', 'AKM'] as $t)
                                <option value="{{ $t }}" @selected(old('tipe_ujian') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Tahun Paket</label>
                        <input type="number" name="tahun_paket" value="{{ old('tahun_paket', date('Y')) }}" min="2020" max="2030" required class="form-control">
                        @error('tahun_paket')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Mata Pelajaran</label>
                    <select name="mata_pelajaran_id" class="form-control">
                        <option value="">— Umum —</option>
                        @foreach($mataPelajarans as $m)
                            <option value="{{ $m->id }}" @selected(old('mata_pelajaran_id') == $m->id)>{{ $m->nama }}</option>
                        @endforeach
                    </select>
                    @error('mata_pelajaran_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label class="form-label">Total Soal</label>
                        <input type="number" name="total_soal" value="{{ old('total_soal', 40) }}" min="1" required class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Waktu (menit)</label>
                        <input type="number" name="waktu_menit" value="{{ old('waktu_menit', 120) }}" min="1" required class="form-control">
                    </div>
                    <div>
                        <label class="form-label">KKM</label>
                        <input type="number" name="kkm" value="{{ old('kkm', 70) }}" min="0" max="100" step="0.01" required class="form-control">
                    </div>
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="form-control">{{ old('deskripsi') }}</textarea>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Paket</button>
                </div>
            </form>
        </div>
    </div>
@endsection

