@extends('layouts.app')

@section('title', 'Import Nilai Transkrip - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Import Nilai Transkrip')
@section('page_subtitle', 'Unggah CSV nilai akhir per siswa per mapel')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Format CSV</h3>
            <a href="{{ route('admin.transkrip.import.template') }}" class="btn btn-sm">Unduh Template</a>
        </div>
        <div class="card-body">
            <p style="font-size:14px;color:#344054;margin:0 0 8px">Header wajib: <code>nisn,kode_mapel,nilai_akhir</code>. Kolom <code>kode_mapel</code> diisi kode mapel (atau nama bila kode kosong). Nilai 0-100; baris tak valid dilewati.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Unggah File</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.transkrip.import.store') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                @csrf
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">File CSV (maks 2MB)</label>
                    <input type="file" name="file" accept=".csv,.txt" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Import</button>
                </div>
            </form>
            @error('file')
                <div style="color:#B42318;font-size:13px;margin-top:8px">{{ $message }}</div>
            @enderror
        </div>
    </div>
@endsection
