@extends('layouts.app')

@section('title', 'Foto Siswa - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Foto Siswa')
@section('page_subtitle', 'Unggah foto massal — nama file harus sama dengan NIS atau NISN (mis. 2401001.jpg)')

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total siswa</div><div class="value">{{ $total }}</div></div>
        <div class="stat ok"><div class="label">Sudah berfoto</div><div class="value">{{ $denganFoto }}</div></div>
        <div class="stat gold"><div class="label">Belum berfoto</div><div class="value">{{ $total - $denganFoto }}</div></div>
    </div>

    @if(session('foto_tidak_cocok'))
        <div class="card" style="margin-bottom:16px;border-left:4px solid #B8860B">
            <div class="card-body">
                <b>File yang tidak cocok dengan NIS/NISN mana pun:</b>
                <div style="font-size:13px;color:#667085;margin-top:6px">{{ implode(', ', session('foto_tidak_cocok')) }}</div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3>Unggah Foto Massal</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.referensi.foto-siswa.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div style="margin-bottom:12px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Pilih foto (maks 50 file, tiap file maks 2MB, format jpg/png)</label>
                    <input type="file" name="foto[]" accept=".jpeg,.jpg,.png" multiple required style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                </div>
                <button type="submit" class="btn btn-sm">Unggah & Pasangkan</button>
                <span style="font-size:12px;color:#667085;margin-left:8px">Foto lama siswa otomatis terhapus saat diganti.</span>
            </form>
        </div>
    </div>
@endsection
