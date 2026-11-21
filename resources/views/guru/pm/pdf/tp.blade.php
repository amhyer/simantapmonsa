<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tujuan Pembelajaran (TP)</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h2 { margin: 0 0 4px; font-size: 14px; text-align: center; }
        .sub { text-align: center; font-size: 10px; color: #4B5563; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #374151; padding: 5px 6px; vertical-align: top; }
        th { background: #F3F4F6; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h2>DAFTAR TUJUAN PEMBELAJARAN (TP)</h2>
    <div class="sub">
        {{ $sekolah?->nama_sekolah ?? 'Sekolah Dasar' }}
        @if($mapelFilter) · {{ $mapelFilter }} @endif
        @if($faseFilter) · Fase {{ $faseFilter }} @endif
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:28px">No</th>
                <th style="width:110px">Mata Pelajaran</th>
                <th style="width:44px">Fase</th>
                <th style="width:90px">Elemen</th>
                <th style="width:58px">Kode</th>
                <th>Rumusan Tujuan Pembelajaran</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tpList as $i => $tp)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $tp->nama_mapel }}</td>
                    <td class="center">{{ $tp->fase }}</td>
                    <td>{{ $tp->elemen }}</td>
                    <td class="center">{{ $tp->kode_tp ?: '-' }}</td>
                    <td>{{ $tp->deskripsi }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center">Belum ada data Tujuan Pembelajaran.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('guru.pm.pdf._ttd')
</body>
</html>
