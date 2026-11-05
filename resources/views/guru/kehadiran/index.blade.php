@extends('layouts.app')

@section('title', 'Kehadiran Siswa - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Kehadiran Siswa (Scan Kartu Identitas)')
@section('page_subtitle', 'Pindai Kartu Identitas Siswa sesuai jadwal mengajar Anda pada hari ini')

@section('header_actions')
    <a href="{{ route('presensi.pegawai.index') }}" class="btn btn-sm btn-ghost">🪪 Kartu Identitas &amp; Scan Mandiri Saya</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3>🗓️ Jadwal Mengajar Saya Hari Ini</h3>
        <span class="tag tag-primary">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
    </div>
    <div class="card-body">
        @if($jadwalHariIni->isNotEmpty())
            <div class="grid grid-3" style="gap:12px">
                @foreach($jadwalHariIni as $jadwal)
                    <div style="border:1.5px solid {{ $jadwal->sedang_dibuka ? '#A6F4C5' : '#E4E7EC' }};border-radius:12px;padding:14px;background:{{ $jadwal->sedang_dibuka ? '#F6FEF9' : '#fff' }}">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:start;flex-wrap:wrap">
                            <strong style="font-size:14px">{{ $jadwal->mata_pelajaran }}</strong>
                            @if($jadwal->presensi_mapel_terpisah)
                                <span class="tag tag-gold">Absensi Mapel</span>
                            @else
                                <span class="tag tag-primary">Absensi Kelas</span>
                            @endif
                        </div>
                        <div style="color:#475467;font-size:13px;margin:8px 0 6px">
                            <strong>{{ $jadwal->rombel?->nama_rombel ?? $jadwal->kelas }}</strong> · {{ $jadwal->jam_mulai }}–{{ $jadwal->jam_selesai }}
                        </div>
                        <div style="font-size:12px;color:#667085;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap">
                            <span>Hadir tercatat: <strong>{{ $jadwal->jumlah_hadir_hari_ini ?? 0 }}</strong> siswa</span>
                            @if($jadwal->sedang_dibuka)
                                <span class="tag tag-ok">Jam Buka</span>
                            @endif
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="{{ route('guru.kehadiran.jadwal', $jadwal) }}" class="btn btn-sm">📷 Scan Kartu Siswa</a>
                            <a href="{{ route('guru.kehadiran.kartu-kelas', $jadwal) }}" class="btn btn-ghost btn-sm">🪪 Kartu Kelas</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p style="margin:0;color:#667085">Belum ada jadwal mengajar untuk hari ini ({{ now()->locale('id')->translatedFormat('l') }}). Hanya guru yang terjadwal masuk pada hari ini yang dapat memindai kartu identitas siswa pada jam pelajarannya.</p>
        @endif
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px">
    @php
        $totalHadir = $kehadiran->where('status', 'H')->count();
        $totalSakit = $kehadiran->where('status', 'S')->count();
        $totalIzin = $kehadiran->where('status', 'I')->count();
        $totalAlpa = $kehadiran->where('status', 'A')->count();
    @endphp
    <div class="stat-card ok"><div class="stat-value">{{ $totalHadir }}</div><div class="stat-label">Hadir</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $totalSakit }}</div><div class="stat-label">Sakit</div></div>
    <div class="stat-card primary"><div class="stat-value">{{ $totalIzin }}</div><div class="stat-label">Izin</div></div>
    <div class="stat-card bad"><div class="stat-value">{{ $totalAlpa }}</div><div class="stat-label">Alpa</div></div>
</div>

@if(isset($kehadiranMapel) && $kehadiranMapel->isNotEmpty())
    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3>📘 Riwayat Absensi Mata Pelajaran (Scan Kartu Siswa)</h3></div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tanggal</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Mata Pelajaran / Kelas</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Jam Hadir</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Metode</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kehadiranMapel as $km)
                            @php $statusMap = ['H'=>['Hadir','#D1FAE5','#065F46'],'S'=>['Sakit','#FEF3C7','#92400E'],'I'=>['Izin','#DBEAFE','#1E40AF'],'A'=>['Alpa','#FEE2E2','#991B1B']]; @endphp
                            @php [$label,$bg,$fg] = $statusMap[$km->status] ?? ['?','#F3F4F6','#6B7280']; @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px">{{ \Carbon\Carbon::parse($km->tanggal)->format('d/m/Y') }}</td>
                                <td style="padding:8px 12px;font-size:12px"><strong>{{ $km->mata_pelajaran }}</strong> · {{ $km->kelas ?? '-' }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $km->siswa?->nama_peserta_didik ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center;font-size:12px">{{ $km->waktu_hadir?->format('H:i:s') ?? '—' }}</td>
                                <td style="padding:8px 12px;text-align:center"><span class="tag {{ $km->metode === 'qr' ? 'tag-primary' : 'tag-mut' }}">{{ strtoupper((string) $km->metode) }}</span></td>
                                <td style="padding:8px 12px;text-align:center"><span style="background:{{ $bg }};color:{{ $fg }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header"><h3>📋 Riwayat Kehadiran Harian</h3></div>
    <div class="card-body tight">
        @if($kehadiran->count())
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tanggal</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Jam Masuk</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Status</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Keterangan</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kehadiran->take(50) as $k)
                            @php $statusMap = ['H'=>['Hadir','#D1FAE5','#065F46'],'S'=>['Sakit','#FEF3C7','#92400E'],'I'=>['Izin','#DBEAFE','#1E40AF'],'A'=>['Alpa','#FEE2E2','#991B1B']]; @endphp
                            @php [$label,$bg,$fg] = $statusMap[$k->status] ?? ['?','#F3F4F6','#6B7280']; @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px">{{ \Carbon\Carbon::parse($k->tanggal)->format('d/m/Y') }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $k->nama_siswa ?? $k->siswa?->nama_peserta_didik ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center">{{ $k->waktu_masuk?->format('H:i:s') ?? '—' }}</td>
                                <td style="padding:8px 12px;text-align:center"><span style="background:{{ $bg }};color:{{ $fg }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $label }}</span></td>
                                <td style="padding:8px 12px;font-size:12px;color:#667085">{{ $k->keterangan ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center">
                                    <form action="{{ route('guru.kehadiran.destroy', $k->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm" style="color:#DC2626">🗑</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center;padding:40px;color:#888">
                <div style="font-size:48px;margin-bottom:12px">🗓️</div>
                <h4>Belum ada data kehadiran</h4>
            </div>
        @endif
    </div>
</div>
@endsection
