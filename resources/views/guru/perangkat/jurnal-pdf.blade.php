<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Jurnal Mengajar</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5px; color: #111827; margin: 16px; }
        .kop { border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 12px; text-align: center; }
        .kop h2 { margin: 0; font-size: 13px; text-transform: uppercase; }
        .kop p { margin: 2px 0 0; font-size: 9px; color: #374151; }
        h3 { text-align: center; margin: 0 0 8px; font-size: 12px; text-transform: uppercase; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #9CA3AF; padding: 5px; text-align: left; vertical-align: top; }
        table.data th { background: #F3F4F6; }
        .ttd { width: 100%; margin-top: 22px; border-collapse: collapse; }
        .ttd td { width: 50%; text-align: center; }
    </style>
</head>
<body>
    <div class="kop">
        <h2>{{ $kop['nama_sekolah'] }}</h2>
        <p>NPSN: {{ $kop['npsn'] }} • {{ $kop['alamat'] }}</p>
    </div>

    <h3>Rekapitulasi Jurnal Mengajar Harian</h3>
    <p style="text-align:center;margin:0 0 8px">
        Guru: <strong>{{ $guru->nama_lengkap }}</strong>
        @if($dari || $sampai)
            • Periode: {{ $dari ?: 'Awal' }} s/d {{ $sampai ?: 'Sekarang' }}
        @endif
    </p>

    <table class="data">
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th style="width:10%">Tanggal</th>
                <th style="width:8%">Jam</th>
                <th style="width:9%">Kelas</th>
                <th style="width:14%">Mapel</th>
                <th style="width:18%">Topik</th>
                <th style="width:21%">Kegiatan</th>
                <th style="width:7%">Hadir</th>
                <th style="width:9%">Tindak Lanjut</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $i => $j)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $j->tanggal->format('d/m/Y') }}</td>
                    <td>{{ $j->jam_ke ?: '-' }}</td>
                    <td>{{ $j->kelas }}</td>
                    <td>{{ $j->mata_pelajaran }}</td>
                    <td>{{ $j->topik }}</td>
                    <td>{{ $j->kegiatan }}</td>
                    <td>{{ $j->jumlah_hadir !== null ? $j->jumlah_hadir.'/'.($j->jumlah_tidak_hadir ?? 0) : '-' }}</td>
                    <td>{{ $j->tindak_lanjut ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center">Belum ada data jurnal pada rentang ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ttd">
        <tr>
            <td>Mengetahui,<br>Kepala Sekolah<br><br><br><br><strong>{{ $kop['kepala_sekolah'] }}</strong></td>
            <td>Guru Pengampu<br><br><br><br><br><strong>{{ $guru->nama_lengkap }}</strong></td>
        </tr>
    </table>
    <p style="text-align:right;font-size:8px;color:#6B7280;margin-top:10px">Dicetak melalui SIMANTAP pada {{ $dicetakPada }}</p>
</body>
</html>
