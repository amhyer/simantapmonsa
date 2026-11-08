@extends('layouts.admin-with-tabs')

@section('title', 'Dashboard Presensi Mesin - SIMANTAP')
@section('page_title', 'Dashboard Presensi Mesin')
@section('page_subtitle', 'Ringkasan kehadiran pegawai dari mesin fingerprint')

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Pegawai Aktif</div><div class="value">{{ $rekap['total_pegawai'] }}</div></div>
        <div class="stat ok"><div class="label">Hadir / Terlambat</div><div class="value">{{ $rekap['hadir'] }}</div></div>
        <div class="stat warn"><div class="label">Terlambat</div><div class="value">{{ $rekap['terlambat'] }}</div></div>
        <div class="stat"><div class="label">Belum Tercatat</div><div class="value">{{ $rekap['belum_tercatat'] }}</div></div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:16px">
        <div class="card">
            <div class="card-header"><h3>Status Kehadiran</h3></div>
            <div class="card-body">
                <p style="margin:0 0 8px">Izin / Sakit / Cuti hari ini: <strong>{{ $rekap['izin_sakit_cuti'] }}</strong> orang</p>
                <p style="margin:0">Belum pulang hari ini: <strong>{{ $rekap['belum_pulang'] }}</strong> orang</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Pembaca Mesin</h3></div>
            <div class="card-body">
                <p style="margin:0;font-size:14px">Engine aktif: <strong>{{ app(\App\Services\MesinPresensi\AttendanceDatabaseReader::class)->engineTerpilih() }}</strong></p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="gap:12px;flex-wrap:wrap">
            <h3 style="margin-right:auto">Rekap Kehadiran — {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</h3>
            <form method="GET" action="{{ route('admin.presensi.dashboard.mesin') }}" style="display:flex;gap:8px;align-items:center">
                <input type="date" name="tanggal" value="{{ $tanggal }}" required>
                <button type="submit" class="btn btn-sm">Tampilkan</button>
            </form>
        </div>
        <div class="card-body tight">
            <p style="margin:0 0 12px;padding:0 14px;color:#667085;font-size:12px">Ringkasan kehadiran pegawai dari hasil tarik mesin fingerprint. Gunakan tombol Koreksi pada halaman Presensi untuk memperbaiki status.</p>
            <div class="table-wrapper"><table>
                <thead><tr><th>Nama / Peran</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Sumber</th></tr></thead>
                <tbody>
                @forelse($pegawai as $person)
                    @php $record = $catatan->get($person->id); @endphp
                    <tr>
                        <td><strong>{{ $person->nama_lengkap }}</strong><br><small>{{ ucfirst($person->peran) }} · {{ $person->nama_pengguna }}</small></td>
                        <td>
                            @if(!$record)<span class="tag tag-mut">Belum tercatat</span>
                            @elseif(in_array($record->status, ['hadir', 'terlambat'], true))<span class="tag tag-ok">{{ ucfirst($record->status) }}</span>
                            @else<span class="tag tag-warn">{{ ucfirst($record->status) }}</span>@endif
                        </td>
                        <td>{{ $record?->waktu_masuk?->format('H:i') ?? '—' }}</td>
                        <td>{{ $record?->waktu_pulang?->format('H:i') ?? '—' }}</td>
                        <td><small>{{ $record?->metode === 'mesin' ? 'Mesin Fingerprint' : ucfirst((string) $record?->metode) }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:24px">Belum ada akun pegawai aktif.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
@endsection
