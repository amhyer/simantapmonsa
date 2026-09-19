@extends('layouts.app')

@section('title', 'Logo dan TTD - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Logo dan TTD')
@section('page_subtitle', 'Unggah logo pemda, logo sekolah, tanda tangan kepala sekolah, dan kop sekolah (maks 2MB per file)')

@section('content')
    @php
        $dokumen = [
            'logo_pemda' => 'Logo Pemda',
            'logo_sekolah' => 'Logo Sekolah',
            'ttd_kepsek' => 'Tanda Tangan Kepala Sekolah',
            'kop_sekolah' => 'Kop Sekolah',
        ];
    @endphp

    <form action="{{ route('admin.referensi.logo-ttd.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-2" style="margin-bottom:16px">
            @foreach($dokumen as $key => $label)
                <div class="card">
                    <div class="card-header">
                        <h3>{{ $label }}</h3>
                    </div>
                    <div class="card-body" style="text-align:center">
                        @if(!empty($pengaturan[$key]))
                            <img src="{{ asset('storage/' . $pengaturan[$key]) }}" alt="{{ $label }}" style="max-width:180px;max-height:140px;border:1px solid #E4E7EC;border-radius:8px;padding:8px;margin-bottom:10px;background:#fff">
                        @else
                            <div class="empty-state" style="padding:16px">
                                <div class="icon">🖼️</div>
                                <p style="font-size:12px">Belum ada file</p>
                            </div>
                        @endif
                        <input type="file" name="{{ $key }}" accept=".jpeg,.jpg,.png" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                    </div>
                </div>
            @endforeach
        </div>
        <button type="submit" class="btn btn-sm">Simpan Semua</button>
        <span style="font-size:12px;color:#667085;margin-left:8px">File lama otomatis terhapus saat diganti.</span>
    </form>
@endsection
