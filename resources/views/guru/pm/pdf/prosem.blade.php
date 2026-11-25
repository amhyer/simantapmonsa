<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Program Semester (Prosem Matriks)</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111827; }
        h2 { margin: 0 0 3px; font-size: 13px; text-align: center; }
        .sub { text-align: center; font-size: 9.5px; color: #4B5563; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #374151; padding: 3px 4px; }
        th { background: #F3F4F6; text-align: center; }
        .center { text-align: center; }
        .libur { background: #FEE2E2; color: #B42318; }
        .isi { background: #DCFCE7; color: #166534; font-weight: bold; }
    </style>
</head>
<body>
    <h2>PROGRAM SEMESTER {{ $semester }} (PROSEM MATRIKS)</h2>
    <div class="sub">
        {{ $sekolah?->nama_sekolah ?? \App\Support\SchoolLevel::labelSekolah($sekolah?->jenjang) }} · {{ $mapel }} · Kelas {{ $tingkat }} · Tahun Ajaran {{ $tahunAjaran }}
    </div>
    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Bab</th>
                <th rowspan="2">TP</th>
                <th rowspan="2">Materi / Topik</th>
                <th rowspan="2">JP</th>
                @foreach($ringkasan['rows'] as $kal)
                    <th colspan="{{ $kal->jumlah_minggu }}">{{ strtoupper(substr($kal->namaBulan(), 0, 3)) }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($ringkasan['rows'] as $kal)
                    @php $libur = is_array($kal->minggu_libur) ? array_map('intval', $kal->minggu_libur) : []; @endphp
                    @for($m = 1; $m <= $kal->jumlah_minggu; $m++)
                        <th class="{{ in_array($m, $libur, true) ? 'libur' : '' }}">{{ $m }}</th>
                    @endfor
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($matriksRows as $i => $row)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $row['bab'] }}</td>
                    <td class="center">{{ $row['kode_tp'] }}</td>
                    <td>{{ $row['materi'] }}</td>
                    <td class="center"><strong>{{ $row['total_jp'] }}</strong></td>
                    @foreach($ringkasan['rows'] as $kal)
                        @php $libur = is_array($kal->minggu_libur) ? array_map('intval', $kal->minggu_libur) : []; @endphp
                        @for($m = 1; $m <= $kal->jumlah_minggu; $m++)
                            @php
                                $isLibur = in_array($m, $libur, true);
                                $val = $row['sel'][$kal->bulan.'_'.$m] ?? null;
                            @endphp
                            <td class="center {{ $isLibur ? 'libur' : ($val ? 'isi' : '') }}">
                                {{ $isLibur ? '×' : ($val ?? '') }}
                            </td>
                        @endfor
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="40" class="center">Belum ada data Program Semester.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('guru.pm.pdf._ttd')
</body>
</html>
