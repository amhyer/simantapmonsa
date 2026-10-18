@extends('layouts.app')

@section('title', 'Kehadiran - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Kehadiran Siswa')
@section('page_subtitle', 'Catat kehadiran melalui jadwal pelajaran yang menjadi tugas Anda')

@section('content')
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
    <a href="{{ route('presensi.pegawai.index') }}" class="btn btn-sm btn-ghost">Presensi Masuk / Pulang Saya</a>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>🗓️ Jadwal Mengajar Hari Ini</h3><span class="tag tag-primary">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></div>
    <div class="card-body">
        @if($jadwalHariIni->isNotEmpty())
            <div class="grid grid-3" style="gap:12px">
                @foreach($jadwalHariIni as $jadwal)
                    <div style="border:1px solid #E4E7EC;border-radius:10px;padding:14px;background:#fff">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:start">
                            <strong>{{ $jadwal->mata_pelajaran }}</strong>
                            @if($jadwal->presensi_mapel_terpisah)<span class="tag tag-gold">Absensi mapel</span>@endif
                        </div>
                        <div style="color:#667085;font-size:13px;margin:8px 0">{{ $jadwal->rombel?->nama_rombel ?? $jadwal->kelas }} · {{ $jadwal->jam_mulai }}–{{ $jadwal->jam_selesai }}</div>
                        <a href="{{ route('guru.kehadiran.jadwal', $jadwal) }}" class="btn btn-sm">Buka absensi &amp; scan QR</a>
                    </div>
                @endforeach
            </div>
        @else
            <p style="margin:0;color:#667085">Belum ada jadwal mengajar untuk hari ini. Jika jadwal belum muncul, minta admin melengkapi data jadwal, rombel, dan guru pengampu.</p>
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

<div class="card">
    <div class="card-header"><h3>📋 Riwayat Kehadiran</h3></div>
    <div class="card-body tight">
        @if($kehadiran->count())
            <div style="overflow-x:auto">
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
