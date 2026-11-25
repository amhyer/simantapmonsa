<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Program Tahunan (Prota)</title>
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
    <h2>PROGRAM TAHUNAN (PROTA)</h2>
    <div class="sub">
        {{ $sekolah?->nama_sekolah ?? \App\Support\SchoolLevel::labelSekolah($sekolah?->jenjang) }} · {{ $mapel }} · Kelas {{ $tingkat }} · Tahun Ajaran {{ $tahunAjaran }}
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:28px">No</th>
                <th style="width:110px">Mata Pelajaran</th>
                <th style="width:48px">Sem</th>
                <th style="width:65px">Kode TP</th>
                <th>Materi / Topik Pembelajaran</th>
                <th style="width:45px">JP</th>
                <th style="width:120px">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $row->nama_mapel }}</td>
                    <td class="center">{{ $row->semester }}</td>
                    <td class="center">{{ $row->tp?->kode_tp ?: '-' }}</td>
                    <td>{{ $row->materi }}</td>
                    <td class="center">{{ $row->alokasi_jp }}</td>
                    <td>{{ $row->keterangan ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">Belum ada data Program Tahunan.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('guru.pm.pdf._ttd')
</body>
</html>
