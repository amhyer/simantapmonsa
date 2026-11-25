<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>KKTP - Kriteria Ketercapaian Tujuan Pembelajaran</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h2 { margin: 0 0 4px; font-size: 14px; text-align: center; }
        .sub { text-align: center; font-size: 10px; color: #4B5563; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #374151; padding: 5px 6px; vertical-align: top; }
        th { background: #F3F4F6; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h2>KRITERIA KETERCAPAIAN TUJUAN PEMBELAJARAN (KKTP)</h2>
    <div class="sub">
        {{ $sekolah?->nama_sekolah ?? \App\Support\SchoolLevel::labelSekolah($sekolah?->jenjang) }} · {{ $mapel }} · Kelas {{ $tingkat }} (Fase {{ $fase }})
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:90px">Interval</th>
                <th style="width:130px">Predikat</th>
                <th>Keterangan / Tindak Lanjut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rentangPredikat as $r)
                <tr>
                    <td class="center"><strong>{{ $r['interval'] }}</strong></td>
                    <td>{{ $r['predikat'] }}</td>
                    <td>{{ $r['keterangan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:28px">No</th>
                <th style="width:110px">Mapel</th>
                <th>Tujuan Pembelajaran</th>
                <th style="width:95px">Pendekatan</th>
                <th>Kriteria Ketercapaian</th>
                <th>Rubrik</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tpList as $i => $tp)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $tp->nama_mapel }}</td>
                    <td>{{ $tp->kode_tp ? '['.$tp->kode_tp.'] ' : '' }}{{ $tp->deskripsi }}</td>
                    <td>{{ $pendekatanLabels[$tp->kktpItem?->pendekatan ?? 'deskripsi'] ?? 'Deskripsi Kriteria' }}</td>
                    <td>{!! nl2br(e($tp->kktpItem?->kriteria ?? '-')) !!}</td>
                    <td>{!! nl2br(e($tp->kktpItem?->rubrik ?? '-')) !!}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center">Belum ada data KKTP per TP.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('guru.pm.pdf._ttd')
</body>
</html>
