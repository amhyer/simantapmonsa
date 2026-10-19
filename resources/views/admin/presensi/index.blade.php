@extends('layouts.admin-with-tabs')

@section('title', 'Presensi Pegawai - SIMANTAP')
@section('sidebar')
    @include('admin.partials.sidebar')
@endsection
@section('page_title', 'Presensi Pegawai')
@section('page_subtitle', 'Atur area absensi dan pantau presensi guru, kepala sekolah, serta tendik')

@section('content')
    @php
        $hadirCount = $catatan->filter(fn ($row) => in_array($row->status, ['hadir', 'terlambat'], true))->count();
        $sudahMasuk = $catatan->filter(fn ($row) => $row->waktu_masuk !== null)->count();
        $sudahPulang = $catatan->filter(fn ($row) => $row->waktu_pulang !== null)->count();
    @endphp

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Pegawai Aktif</div><div class="value">{{ $pegawai->count() }}</div></div>
        <div class="stat ok"><div class="label">Hadir / Terlambat</div><div class="value">{{ $hadirCount }}</div></div>
        <div class="stat gold"><div class="label">Sudah Masuk</div><div class="value">{{ $sudahMasuk }}</div></div>
        <div class="stat primary"><div class="label">Sudah Pulang</div><div class="value">{{ $sudahPulang }}</div></div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3>📍 Area yang Diizinkan untuk Absensi</h3></div>
        <div class="card-body">
            <p style="margin:0 0 14px;color:#667085;font-size:13px">Pegawai hanya dapat mencatat masuk/pulang dari titik aktif dalam radius yang ditentukan. Gunakan lokasi perangkat admin atau isi koordinat secara manual.</p>
            <form action="{{ route('admin.presensi.lokasi.store') }}" method="POST" class="grid grid-4" style="align-items:end;gap:10px">
                @csrf
                <div><label>Nama titik</label><input name="nama" required maxlength="100" placeholder="Contoh: Area sekolah" style="width:100%"></div>
                <div><label>Latitude</label><input id="new-latitude" name="latitude" required type="number" step="any" min="-90" max="90" style="width:100%"></div>
                <div><label>Longitude</label><input id="new-longitude" name="longitude" required type="number" step="any" min="-180" max="180" style="width:100%"></div>
                <div><label>Radius (meter)</label><input name="radius_meter" required type="number" min="20" max="5000" value="150" style="width:100%"></div>
                <div><label>Akurasi GPS maks. (meter)</label><input name="akurasi_maksimal_meter" required type="number" min="10" max="1000" value="150" style="width:100%"></div>
                <div style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="aktif" value="1" checked><label style="margin:0">Aktif</label></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="btn btn-ghost btn-sm" id="get-admin-location">Gunakan lokasi perangkat</button>
                    <button type="submit" class="btn btn-sm">Tambah titik</button>
                </div>
            </form>
            @if($errors->any())
                <div class="note note-bad" style="margin-top:12px">{{ $errors->first() }}</div>
            @endif
        </div>
    </div>

    @if($lokasi->isNotEmpty())
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><h3>Kelola Titik Lokasi</h3></div>
            <div class="card-body tight">
                <div class="table-wrapper"><table>
                    <thead><tr><th>Lokasi</th><th>Koordinat</th><th>Radius</th><th>Akurasi GPS</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @foreach($lokasi as $zone)
                        <tr>
                            <td colspan="6">
                                <form action="{{ route('admin.presensi.lokasi.update', $zone) }}" method="POST" style="display:grid;grid-template-columns:1.2fr 1fr 1fr .8fr .8fr auto;gap:8px;align-items:end">
                                    @csrf @method('PUT')
                                    <div><label>Nama</label><input name="nama" value="{{ $zone->nama }}" required style="width:100%"></div>
                                    <div><label>Latitude / Longitude</label><div style="display:flex;gap:4px"><input name="latitude" type="number" step="any" value="{{ $zone->latitude }}" required style="width:50%"><input name="longitude" type="number" step="any" value="{{ $zone->longitude }}" required style="width:50%"></div></div>
                                    <div><label>Radius (m)</label><input name="radius_meter" type="number" min="20" max="5000" value="{{ $zone->radius_meter }}" required style="width:100%"></div>
                                    <div><label>Akurasi maks. (m)</label><input name="akurasi_maksimal_meter" type="number" min="10" max="1000" value="{{ $zone->akurasi_maksimal_meter }}" required style="width:100%"></div>
                                    <div><label>Status</label><select name="aktif" style="width:100%"><option value="1" @selected($zone->aktif)>Aktif</option><option value="0" @selected(!$zone->aktif)>Nonaktif</option></select></div>
                                    <div style="display:flex;gap:4px"><button class="btn btn-sm" type="submit">Simpan</button></div>
                                </form>
                                <form action="{{ route('admin.presensi.lokasi.destroy', $zone) }}" method="POST" style="margin-top:5px" onsubmit="return confirm('Hapus titik lokasi {{ addslashes($zone->nama) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm" style="color:#B42318">Hapus titik</button>
                                    <small style="color:#667085;margin-left:8px">{{ number_format($zone->latitude, 7) }}, {{ number_format($zone->longitude, 7) }}</small>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header" style="gap:12px;flex-wrap:wrap">
            <h3 style="margin-right:auto">Rekap Pegawai — {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</h3>
            <form method="GET" action="{{ route('admin.presensi.index') }}" style="display:flex;gap:8px;align-items:center">
                <input type="date" name="tanggal" value="{{ $tanggal }}" required>
                <button type="submit" class="btn btn-sm">Tampilkan</button>
            </form>
        </div>
        <div class="card-body tight">
            <p style="margin:0 0 12px;padding:0 14px;color:#667085;font-size:12px">Admin dapat memperbaiki status dan jam dengan mengisi alasan. Semua koreksi masuk ke Log Aktivitas.</p>
            <div class="table-wrapper"><table>
                <thead><tr><th>Nama / Peran</th><th>Masuk</th><th>Pulang</th><th>Status GPS</th><th>Koreksi Admin</th></tr></thead>
                <tbody>
                @forelse($pegawai as $person)
                    @php $record = $catatan->get($person->id); @endphp
                    <tr>
                        <td><strong>{{ $person->nama_lengkap }}</strong><br><small>{{ ucfirst($person->peran) }} · {{ $person->nama_pengguna }}</small></td>
                        <td>{{ $record?->waktu_masuk?->format('H:i:s') ?? '—' }}<br><small>{{ $record?->lokasiMasuk?->nama ?? '' }}</small></td>
                        <td>{{ $record?->waktu_pulang?->format('H:i:s') ?? '—' }}<br><small>{{ $record?->lokasiPulang?->nama ?? '' }}</small></td>
                        <td>
                            @if(!$record)<span class="tag tag-mut">Belum tercatat</span>
                            @else<span class="tag {{ in_array($record->status, ['hadir', 'terlambat'], true) ? 'tag-ok' : 'tag-warn' }}">{{ ucfirst($record->status) }}</span>@endif
                            @if($record?->metode === 'gps')<br><small>GPS</small>@elseif($record?->metode === 'admin')<br><small>Input/koreksi admin</small>@endif
                        </td>
                        <td style="min-width:310px">
                            <form method="POST" action="{{ route('admin.presensi.pegawai.koreksi') }}" style="display:grid;grid-template-columns:1fr 1fr;gap:5px">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $person->id }}">
                                <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                                <select name="status" required><option value="hadir" @selected(($record?->status ?? 'hadir') === 'hadir')>Hadir</option><option value="terlambat" @selected($record?->status === 'terlambat')>Terlambat</option><option value="izin" @selected($record?->status === 'izin')>Izin</option><option value="sakit" @selected($record?->status === 'sakit')>Sakit</option><option value="alpa" @selected($record?->status === 'alpa')>Alpa</option></select>
                                <input type="time" name="jam_masuk" value="{{ $record?->waktu_masuk?->format('H:i') }}" aria-label="Jam masuk">
                                <input type="time" name="jam_pulang" value="{{ $record?->waktu_pulang?->format('H:i') }}" aria-label="Jam pulang">
                                <input name="alasan_koreksi" required maxlength="500" placeholder="Alasan koreksi" value="{{ old('alasan_koreksi') }}">
                                <button class="btn btn-sm" type="submit" style="grid-column:1/-1">Simpan koreksi</button>
                            </form>
                            @if($record?->alasan_koreksi)<small>Catatan terakhir: {{ $record->alasan_koreksi }}</small>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:24px">Belum ada akun pegawai aktif.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const button = document.getElementById('get-admin-location');
    if (!button) return;
    button.addEventListener('click', () => {
        if (!navigator.geolocation) { alert('Browser tidak mendukung GPS.'); return; }
        button.disabled = true;
        button.textContent = 'Mengambil lokasi…';
        navigator.geolocation.getCurrentPosition((position) => {
            document.getElementById('new-latitude').value = position.coords.latitude.toFixed(7);
            document.getElementById('new-longitude').value = position.coords.longitude.toFixed(7);
            button.disabled = false;
            button.textContent = 'Gunakan lokasi perangkat';
        }, (error) => {
            alert(error.message || 'Gagal mendapatkan lokasi perangkat.');
            button.disabled = false;
            button.textContent = 'Gunakan lokasi perangkat';
        }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
    });
})();
</script>
@endpush
