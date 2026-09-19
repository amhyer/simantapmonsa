@extends('layouts.app')

@section('title', 'Push Sekolah ke Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Push Data Sekolah ke Dapodik')
@section('page_subtitle', 'Kirim data profil sekolah ke server Dapodik Pusat')

@section('content')
    <div class="card" style="max-width:600px;margin-bottom:24px">
        <div class="card-header">
            <h3><x-lucide-upload-cloud class="w-5 h-5 mr-2" /> Push Data Sekolah ke Dapodik</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:16px">
                <x-lucide-info class="w-5 h-5 mr-2" />
                <strong>Informasi:</strong> Push ini akan mengirim data profil sekolah (NPSN, nama, alamat, kontak, kepala sekolah, dll) ke server Dapodik pusat. Pastikan data sekolah sudah lengkap dan benar.
            </div>

            <div class="card" style="margin-bottom:16px;background:#FAFBFD;border:1px solid #E4E7EC">
                <div class="card-header">
                    <h4>Data yang Akan Dikirim</h4>
                </div>
                <div class="card-body" style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px">
                    <div><b>NPSN:</b> {{ $sekolah->npsn ?? '-' }}</div>
                    <div><b>Nama Sekolah:</b> {{ $sekolah->nama_sekolah ?? '-' }}</div>
                    <div><b>Alamat:</b> {{ $sekolah->alamat ?? '-' }}</div>
                    <div><b>Kecamatan:</b> {{ $sekolah->kecamatan ?? '-' }}</div>
                    <div><b>Kota/Kabupaten:</b> {{ $sekolah->kota ?? '-' }}</div>
                    <div><b>Provinsi:</b> {{ $sekolah->provinsi ?? '-' }}</div>
                    <div><b>Kode Pos:</b> {{ $sekolah->kode_pos ?? '-' }}</div>
                    <div><b>Telepon:</b> {{ $sekolah->telepon ?? '-' }}</div>
                    <div><b>Email:</b> {{ $sekolah->email ?? '-' }}</div>
                    <div><b>Kepala Sekolah:</b> {{ $sekolah->kepala_sekolah ?? '-' }}</div>
                    <div><b>NPSN:</b> {{ $sekolah->npsn ?? '-' }}</div>
                    <div><b>Status:</b> {{ $sekolah->status ?? '-' }}</div>
                    <div><b>Jenis Sekolah:</b> {{ $sekolah->jenis_sekolah ?? '-' }}</div>
                    <div><b>Jenjang:</b> {{ $sekolah->jenjang ?? '-' }}</div>
                    <div><b>Akreditasi:</b> {{ $sekolah->akreditasi ?? '-' }}</div>
                    <div><b>Kecamatan:</b> {{ $sekolah->kecamatan ?? '-' }}</div>
                    <div><b>Kode Pos:</b> {{ $sekolah->kode_pos ?? '-' }}</div>
                    <div><b>Telepon:</b> {{ $sekolah->telepon ?? '-' }}</div>
                    <div><b>Email:</b> {{ $sekolah->email ?? '-' }}</div>
                    <div><b>Kepala Sekolah:</b> {{ $sekolah->kepala_sekolah ?? '-' }}</div>
                    <div><b>NPSN:</b> {{ $sekolah->npsn ?? '-' }}</div>
                    <div><b>Status:</b> {{ $sekolah->status ?? '-' }}</div>
                    <div><b>Jenis Sekolah:</b> {{ $sekolah->jenis_sekolah ?? '-' }}</div>
                    <div><b>Jenjang:</b> {{ $sekolah->jenjang ?? '-' }}</div>
                    <div><b>Akreditasi:</b> {{ $sekolah->akreditasi ?? '-' }}</div>
                    <div><b>Kecamatan:</b> {{ $sekolah->kecamatan ?? '-' }}</div>
                    <div><b>Kode Pos:</b> {{ $sekolah->kode_pos ?? '-' }}</div>
                    <div><b>Telepon:</b> {{ $sekolah->telepon ?? '-' }}</div>
                    <div><b>Email:</b> {{ $sekolah->email ?? '-' }}</div>
                    <div><b>Kepala Sekolah:</b> {{ $sekolah->kepala_sekolah ?? '-' }}</div>
                    <div><b>NPSN:</b> {{ $sekolah->npsn ?? '-' }}</div>
                    <div><b>Status:</b> {{ $sekolah->status ?? '-' }}</div>
                    <div><b>Jenis Sekolah:</b> {{ $sekolah->jenis_sekolah ?? '-' }}</div>
                    <div><b>Jenjang:</b> {{ $sekolah->jenjang ?? '-' }}</div>
                    <div><b>Akreditasi:</b> {{ $sekolah->akreditasi ?? '-' }}</div>
                    <div><b>Kecamatan:</b> {{ $sekolah->kecamatan ?? '-' }}</div>
                    <div><b>Kode Pos:</b> {{ $sekolah->kode_pos ?? '-' }}</div>
                    <div><b>Telepon:</b> {{ $sekolah->telepon ?? '-' }}</div>
                    <div><b>Email:</b> {{ $sekolah->email ?? '-' }}</div>
                    <div><b>Kepala Sekolah:</b> {{ $sekolah->kepala_sekolah ?? '-' }}</div>
                    <div><b>NPSN:</b> {{ $sekolah->npsn ?? '-' }}</div>
                    <div><b>Status:</b> {{ $sekolah->status ?? '-' }}</div>
                    <div><b>Jenis Sekolah:</b> {{ $sekolah->jenis_sekolah ?? '-' }}</div>
                    <div><b>Jenjang:</b> {{ $sekolah->jenjang ?? '-' }}</div>
                    <div><b>Akreditasi:</b> {{ $sekolah->akreditasi ?? '-' }}</div>
                    <div><b>Kecamatan:</b> {{ $sekolah->kecamatan ?? '-' }}</div>
                    <div><b>Kode Pos:</b> {{ $sekolah->kode_pos ?? '-' }}</div>
                    <div><b>Telepon:</b> {{ $sekolah->telepon ?? '-' }}</div>
                    <div><b>Email:</b> {{ $sekolah->email ?? '-' }}</div>
                    <div><b>Kepala Sekolah:</b> {{ $sekolah->kepala_sekolah ?? '-' }}</div>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.dapodik.push.sekolah') }}" method="POST">
            @csrf
            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px">
                <a href="{{ route('admin.dapodik.index') }}" class="btn btn-ghost btn-sm">Batal</a>
                <button type="submit" class="btn" onclick="return confirm('Yakin ingin push data sekolah ke Dapodik?')">
                    <x-lucide-upload-cloud class="w-4 h-4 mr-2" /> Push Sekarang
                </button>
            </div>
        </form>

        @if(session('success'))
            <div class="alert alert-success" style="margin-top:16px">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger" style="margin-top:16px">{{ session('error') }}</div>
        @endif
    </div>
@endsection