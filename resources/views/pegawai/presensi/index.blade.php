@extends('layouts.app')

@section('title', 'Presensi Pegawai - SIMANTAP')
@section('sidebar')
    @if(auth()->user()->peran === 'guru')
        @include('guru.partials.sidebar')
    @elseif(auth()->user()->peran === 'kepsek')
        @include('kepsek.partials.sidebar')
    @else
        @include('pegawai.partials.sidebar')
    @endif
@endsection
@section('page_title', 'Presensi Pegawai')
@section('page_subtitle', 'Catat jam masuk dan jam pulang menggunakan lokasi sekolah')

@section('content')
    @php
        $sudahMasuk = (bool) $record?->waktu_masuk;
        $sudahPulang = (bool) $record?->waktu_pulang;
        $bisaGps = $lokasiAktif->isNotEmpty();
    @endphp

    <div class="grid grid-2" style="gap:16px;align-items:start">
        <div class="card" data-pegawai-attendance>
            <div class="card-header"><h3>🕒 Presensi Hari Ini</h3></div>
            <div class="card-body">
                <p style="margin-top:0;color:#667085">{{ now()->locale('id')->translatedFormat('l, d F Y') }} · Akun: <strong>{{ auth()->user()->nama_lengkap }}</strong> ({{ ucfirst(auth()->user()->peran) }})</p>

                @if(!$bisaGps)
                    <div class="note note-warn" style="margin-bottom:12px">Admin belum mengatur titik lokasi absensi yang aktif. Hubungi admin sekolah.</div>
                @endif

                <div class="grid grid-2" style="gap:12px;margin-bottom:16px">
                    <div class="stat-card {{ $sudahMasuk ? 'ok' : 'primary' }}">
                        <div class="stat-label">Jam Masuk</div>
                        <div class="stat-value" data-waktu-masuk>{{ $record?->waktu_masuk?->format('H:i:s') ?? '—' }}</div>
                        @if($record?->lokasiMasuk)<small data-lokasi-masuk>{{ $record->lokasiMasuk->nama }}</small>@else<small data-lokasi-masuk>—</small>@endif
                    </div>
                    <div class="stat-card {{ $sudahPulang ? 'ok' : 'gold' }}">
                        <div class="stat-label">Jam Pulang</div>
                        <div class="stat-value" data-waktu-pulang>{{ $record?->waktu_pulang?->format('H:i:s') ?? '—' }}</div>
                        @if($record?->lokasiPulang)<small data-lokasi-pulang>{{ $record->lokasiPulang->nama }}</small>@else<small data-lokasi-pulang>—</small>@endif
                    </div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="button" class="btn" data-pegawai-clock="masuk" data-endpoint="{{ route('presensi.pegawai.masuk') }}" @disabled(!$bisaGps || $sudahMasuk)>Catat Jam Masuk</button>
                    <button type="button" class="btn btn-ghost" data-pegawai-clock="pulang" data-endpoint="{{ route('presensi.pegawai.pulang') }}" @disabled(!$bisaGps || !$sudahMasuk || $sudahPulang)>Catat Jam Pulang</button>
                </div>
                <div data-pegawai-feedback role="status" aria-live="polite" style="margin-top:14px;padding:10px;border-radius:8px;background:#F2F4F7" data-kind="info">
                    @if($sudahPulang) Presensi hari ini sudah lengkap.
                    @elseif($sudahMasuk) Jam masuk tercatat. Jangan lupa mencatat jam pulang.
                    @else Pastikan GPS aktif dan izinkan browser mengakses lokasi.
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>📍 Area Presensi Aktif</h3></div>
            <div class="card-body">
                @if($lokasiAktif->isEmpty())
                    <p style="color:#667085;margin:0">Belum ada area aktif. Presensi lokasi belum dapat digunakan.</p>
                @else
                    <p style="color:#667085;margin-top:0">Perangkat harus berada di dalam salah satu radius berikut. Lokasi hanya diambil saat Anda menekan tombol masuk atau pulang.</p>
                    <ul style="padding-left:20px;margin-bottom:0">
                        @foreach($lokasiAktif as $zone)
                            <li style="margin:8px 0"><strong>{{ $zone->nama }}</strong> — radius {{ $zone->radius_meter }} m, akurasi GPS maksimal {{ $zone->akurasi_maksimal_meter }} m</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
