<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Alur Tujuan Pembelajaran (ATP)</title>
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
    <h2>ALUR TUJUAN PEMBELAJARAN (ATP)</h2>
    <div class="sub">
        {{ $sekolah?->nama_sekolah ?? 'Sekolah Dasar' }} · {{ $mapel }} · Fase {{ $fase }}
        @if($kelasFilter) · Kelas {{ $kelasFilter }} @endif
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:36px">Urut</th>
                <th style="width:120px">Mata Pelajaran</th>
                <th style="width:52px">Kelas</th>
                <th style="width:52px">Sem</th>
                <th style="width:90px">Elemen</th>
                <th>Tujuan Pembelajaran</th>
                <th style="width:140px">Materi Pokok</th>
                <th style="width:42px">JP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tpList as $tp)
                <tr>
                    <td class="center">{{ $tp->urutan }}</td>
                    <td>{{ $tp->nama_mapel }}</td>
                    <td class="center">{{ $tp->kelas_target ?: '-' }}</td>
                    <td class="center">{{ $tp->semester_target ?: '-' }}</td>
                    <td>{{ $tp->elemen }}</td>
                    <td>{{ $tp->kode_tp ? '['.$tp->kode_tp.'] ' : '' }}{{ $tp->deskripsi }}</td>
                    <td>{{ $tp->materi_pokok ?: '-' }}</td>
                    <td class="center">{{ $tp->alokasi_jp }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="center">Belum ada data ATP.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('guru.pm.pdf._ttd')
</body>
</html>
