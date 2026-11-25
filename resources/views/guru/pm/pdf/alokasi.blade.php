<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Alokasi Waktu Struktur Kurikulum</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h2 { margin: 0 0 4px; font-size: 14px; text-align: center; }
        .sub { text-align: center; font-size: 10px; color: #4B5563; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #374151; padding: 5px 6px; }
        th { background: #F3F4F6; text-align: center; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h2>ALOKASI WAKTU STRUKTUR KURIKULUM</h2>
    <div class="sub">
        {{ $sekolah?->nama_sekolah ?? \App\Support\SchoolLevel::labelSekolah($sekolah?->jenjang) }} · Tahun Ajaran {{ $tahunAjaran }} · Permendikdasmen No. 13 Tahun 2025 (JP/Minggu)
    </div>
    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Mata Pelajaran</th>
                @foreach($tingkatList as $t)
                    <th colspan="2">Kelas {{ $t }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($tingkatList as $t)
                    <th>Intra</th>
                    <th>Ko</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($namaMapelList as $i => $mapel)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $mapel }}</td>
                    @foreach($tingkatList as $t)
                        <td class="center">{{ $matrix[$mapel][$t]['intra'] ?? 0 }}</td>
                        <td class="center">{{ $matrix[$mapel][$t]['ko'] ?? 0 }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ 2 + count($tingkatList) * 2 }}" class="center">Belum ada data alokasi waktu.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('guru.pm.pdf._ttd')
</body>
</html>
