@extends('layouts.app')

@section('title', 'Presensi Saya & Kartu Identitas - SIMANTAP')
@section('sidebar')
    @if(auth()->user()->peran === 'guru')
        @include('guru.partials.sidebar')
    @elseif(auth()->user()->peran === 'kepsek')
        @include('kepsek.partials.sidebar')
    @else
        @include('pegawai.partials.sidebar')
    @endif
@endsection
@section('page_title', 'Presensi Mandiri & Kartu Identitas')
@section('page_subtitle', 'Pindai mandiri Kartu Identitas Anda saat jam masuk & pulang atau catat melalui lokasi sekolah')

@section('header_actions')
    <a href="{{ route('presensi.pegawai.kartu') }}" class="btn btn-sm">🖨️ Cetak Kartu Identitas Saya</a>
@endsection

@section('content')
    @php
        $sudahMasuk = (bool) $record?->waktu_masuk;
        $sudahPulang = (bool) $record?->waktu_pulang;
        $bisaGps = $lokasiAktif->isNotEmpty();
        $defaultJenis = !$sudahMasuk ? 'masuk' : 'pulang';
    @endphp

    <div class="grid grid-2" style="gap:16px;align-items:start;margin-bottom:16px">
        <div class="card" data-pegawai-attendance>
            <div class="card-header">
                <h3>📷 Scan Mandiri Kartu Identitas</h3>
                <span class="tag tag-primary">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </div>
            <div class="card-body">
                <div class="grid grid-2" style="gap:12px;margin-bottom:16px">
                    <div class="stat-card {{ $sudahMasuk ? 'ok' : 'primary' }}">
                        <div class="stat-label">Jam Masuk</div>
                        <div class="stat-value" data-waktu-masuk>{{ $record?->waktu_masuk?->format('H:i:s') ?? '—' }}</div>
                        <small data-lokasi-masuk>
                            @if($record?->lokasiMasuk)
                                {{ $record->lokasiMasuk->nama }}
                            @elseif($record?->waktu_masuk && str_starts_with((string) $record?->metode, 'qr'))
                                Scan Kartu Identitas
                            @else
                                —
                            @endif
                        </small>
                    </div>
                    <div class="stat-card {{ $sudahPulang ? 'ok' : 'gold' }}">
                        <div class="stat-label">Jam Pulang</div>
                        <div class="stat-value" data-waktu-pulang>{{ $record?->waktu_pulang?->format('H:i:s') ?? '—' }}</div>
                        <small data-lokasi-pulang>
                            @if($record?->lokasiPulang)
                                {{ $record->lokasiPulang->nama }}
                            @elseif($record?->waktu_pulang && str_starts_with((string) $record?->metode, 'qr'))
                                Scan Kartu Identitas
                            @else
                                —
                            @endif
                        </small>
                    </div>
                </div>

                <div data-presensi-qr
                     data-mode="self-pegawai"
                     data-endpoint="{{ route('presensi.pegawai.scan') }}"
                     data-default-jenis="{{ $defaultJenis }}"
                     data-attach-gps="{{ $bisaGps ? 'true' : 'false' }}"
                     style="padding:14px;border:1px solid #E4E7EC;border-radius:12px;background:#FAFBFD;margin-bottom:16px">
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
                        <strong style="font-size:13px">Mode Scan Kartu:</strong>
                        <label style="display:inline-flex;align-items:center;gap:6px;margin:0;cursor:pointer">
                            <input type="radio" name="jenis_scan_pegawai" value="masuk" data-qr-jenis @checked(!$sudahMasuk) @disabled($sudahMasuk)>
                            <span>Jam Masuk</span>
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;margin:0;cursor:pointer">
                            <input type="radio" name="jenis_scan_pegawai" value="pulang" data-qr-jenis @checked($sudahMasuk && !$sudahPulang) @disabled(!$sudahMasuk || $sudahPulang)>
                            <span>Jam Pulang</span>
                        </label>
                    </div>

                    <div data-qr-reader style="width:100%;max-width:420px;margin:8px auto"></div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <button type="button" class="btn btn-sm" data-qr-start @disabled($sudahPulang)>📷 Aktifkan Kamera Scan</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-qr-switch hidden>Ganti Kamera</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-qr-stop disabled>Hentikan Kamera</button>
                        <input type="text" data-qr-payload autocomplete="off" @disabled($sudahPulang) placeholder="Atau scan dengan pemindai QR USB…" style="flex:1;min-width:200px">
                        <button type="button" class="btn btn-ghost btn-sm" data-qr-submit-manual @disabled($sudahPulang)>Catat Scan</button>
                    </div>

                    <div data-qr-feedback role="status" aria-live="polite" style="margin-top:10px;padding:10px;border-radius:8px;background:#fff;border:1px solid #E4E7EC;font-size:13px" data-kind="info">
                        @if($sudahPulang)
                            Presensi masuk dan pulang hari ini sudah lengkap.
                        @else
                            Arahkan Kartu Identitas Anda ke kamera atau gunakan scanner QR untuk mencatat presensi mandiri.
                        @endif
                    </div>
                </div>

                <div style="border-top:1px solid #E4E7EC;padding-top:12px">
                    <div style="font-size:12.5px;font-weight:600;color:#475467;margin-bottom:8px">📍 Alternatif Presensi Berbasis Titik GPS Sekolah</div>
                    @if(!$bisaGps)
                        <p style="font-size:12px;color:#667085;margin:0 0 8px">Titik lokasi GPS belum diatur admin. Gunakan <strong>Scan Mandiri Kartu Identitas</strong> di atas.</p>
                    @endif
                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <button type="button" class="btn btn-ghost btn-sm" data-pegawai-clock="masuk" data-endpoint="{{ route('presensi.pegawai.masuk') }}" @disabled(!$bisaGps || $sudahMasuk)>Catat Masuk via GPS</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-pegawai-clock="pulang" data-endpoint="{{ route('presensi.pegawai.pulang') }}" @disabled(!$bisaGps || !$sudahMasuk || $sudahPulang)>Catat Pulang via GPS</button>
                    </div>
                    <div data-pegawai-feedback role="status" aria-live="polite" style="margin-top:10px;padding:8px 10px;border-radius:8px;background:#F2F4F7;font-size:12px" data-kind="info">
                        @if($sudahPulang) Presensi hari ini sudah lengkap.
                        @elseif($sudahMasuk) Jam masuk tercatat. Jangan lupa scan atau catat jam pulang.
                        @else Pilih Scan Kartu Identitas di atas atau pastikan GPS berada di dalam radius sekolah.
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div style="display:grid;gap:16px">
            <div class="card">
                <div class="card-header">
                    <h3>🪪 Kartu Identitas Saya</h3>
                    <a href="{{ route('presensi.pegawai.kartu') }}" class="btn btn-ghost btn-sm">🖨️ Cetak Kartu</a>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;align-items:center;gap:12px">
                    @include('presensi.partials.id-card', ['card' => $kartuSaya])
                    <p style="margin:0;font-size:12px;color:#667085;text-align:center">
                        Cetak kartu ini untuk dipindai setiap hari saat jam masuk &amp; jam pulang. Hanya kartu identitas milik akun Anda sendiri yang diterima pada pemindaian mandiri.
                    </p>
                </div>
            </div>

            @if($lokasiAktif->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h3>📍 Area Presensi Sekolah Aktif</h3></div>
                    <div class="card-body">
                        <ul style="padding-left:20px;margin:0;font-size:13px;color:#475467">
                            @foreach($lokasiAktif as $zone)
                                <li style="margin:6px 0"><strong>{{ $zone->nama }}</strong> — radius {{ $zone->radius_meter }} m (akurasi maks. {{ $zone->akurasi_maksimal_meter }} m)</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if(isset($riwayat) && $riwayat->isNotEmpty())
        <div class="card">
            <div class="card-header"><h3>📋 Riwayat Presensi Saya (10 Hari Terakhir)</h3></div>
            <div class="card-body tight">
                <div class="table-wrapper"><table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Metode</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($riwayat as $row)
                        <tr>
                            <td>{{ $row->tanggal?->translatedFormat('l, d M Y') }}</td>
                            <td>{{ $row->waktu_masuk?->format('H:i:s') ?? '—' }}</td>
                            <td>{{ $row->waktu_pulang?->format('H:i:s') ?? '—' }}</td>
                            <td>
                                @if($row->metode === 'qr' || $row->metode === 'qr+gps')
                                    <span class="tag tag-primary">Scan Kartu QR</span>
                                @elseif($row->metode === 'gps')
                                    <span class="tag tag-mut">GPS Lokasi</span>
                                @else
                                    <span class="tag tag-gold">{{ ucfirst((string) $row->metode) }}</span>
                                @endif
                            </td>
                            <td><span class="tag {{ in_array($row->status, ['hadir', 'terlambat'], true) ? 'tag-ok' : 'tag-warn' }}">{{ ucfirst($row->status) }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </div>
    @endif
@endsection
