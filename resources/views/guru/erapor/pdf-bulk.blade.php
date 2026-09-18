<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>e-Rapor Bulk</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.4; color: #000; }
        .container { padding: 15px; }
        .kop { text-align: center; border-bottom: 3px double #1F3864; padding-bottom: 10px; margin-bottom: 15px; }
        .kop h1 { font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .kop h2 { font-size: 13px; font-weight: bold; margin-bottom: 3px; }
        .kop p { font-size: 10px; color: #333; }
        .identitas { margin-bottom: 15px; font-size: 11px; }
        .identitas .row { display: flex; margin-bottom: 2px; }
        .identitas .label { width: 130px; font-weight: 600; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #1F3864; border-left: 3px solid #B8860B; padding-left: 8px; margin: 12px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background: #1F3864; color: #fff; font-weight: bold; text-align: center; font-size: 9.5px; }
        td.ctr { text-align: center; }
        .deskripsi-box { border: 1px solid #999; padding: 8px; border-radius: 4px; background: #FBF4E4; margin-bottom: 10px; font-size: 10.5px; }
        .page-break { page-break-after: always; }
        .ttd { margin-top: 25px; display: flex; justify-content: space-between; text-align: center; font-size: 10px; }
        .ttd .spacer { height: 55px; }
        .ttd-col { width: 30%; }
    </style>
</head>
<body>
    @foreach($data as $index => $d)
        <div class="container">
            <div class="kop">
                <h1>{{ strtoupper($d['pengaturan']->pengaturan['sekolah'] ?? 'SEKOLAH') }}</h1>
                <h2>LAPORAN HASIL BELAJAR PESERTA DIDIK</h2>
                <p>{{ $d['pengaturan']->pengaturan['alamat'] ?? '' }}</p>
                <p>NPSN: {{ $d['pengaturan']->pengaturan['npsn'] ?? '-' }} | TP: {{ $d['pengaturan']->tahun_pelajaran ?? '-' }} | Semester: {{ $d['pengaturan']->semester ?? '-' }}</p>
            </div>

            <div class="section-title">A. IDENTITAS</div>
            <div class="identitas">
                <div class="row"><div class="label">Nama</div><div>: {{ $d['siswa']->nama_peserta_didik }}</div></div>
                <div class="row"><div class="label">NISN/NIS</div><div>: {{ $d['siswa']->nisn ?? '-' }} / {{ $d['siswa']->nis }}</div></div>
                <div class="row"><div class="label">JK / Kelas</div><div>: {{ $d['siswa']->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }} / {{ $d['siswa']->kelas }}</div></div>
            </div>

            <div class="section-title">B. NILAI</div>
            <table>
                <thead><tr><th>No</th><th>Mapel</th><th>Formatif</th><th>Sumatif</th><th>SA</th><th>Akhir</th><th>Pred</th></tr></thead>
                <tbody>
                    @forelse($d['nilaiMapel'] as $i => $n)
                        <tr>
                            <td class="ctr">{{ $i + 1 }}</td>
                            <td>{{ $n->mata_pelajaran }}</td>
                            <td class="ctr">{{ number_format($n->nilai_formatif, 1) }}</td>
                            <td class="ctr">{{ number_format($n->nilai_sumatif, 1) }}</td>
                            <td class="ctr">{{ number_format($n->nilai_sumatif_akhir, 1) }}</td>
                            <td class="ctr"><b>{{ number_format($n->nilai_akhir, 1) }}</b></td>
                            <td class="ctr"><b>{{ $n->predikat }}</b></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ctr">Belum ada nilai</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if($d['nilaiMapel']->whereNotNull('deskripsi_capaian')->count() > 0)
                <div class="section-title">C. DESKRIPSI</div>
                @foreach($d['nilaiMapel'] as $n)
                    @if($n->deskripsi_capaian)
                        <div class="deskripsi-box"><b>{{ $n->mata_pelajaran }}:</b> {{ $n->deskripsi_capaian }}</div>
                    @endif
                @endforeach
            @endif

            <div class="section-title">D. KEHADIRAN</div>
            <table>
                <thead><tr><th>Hadir</th><th>Sakit</th><th>Izin</th><th>Alpa</th><th>Total</th><th>%</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="ctr">{{ $d['rekapHadir']['hadir'] }}</td>
                        <td class="ctr">{{ $d['rekapHadir']['sakit'] }}</td>
                        <td class="ctr">{{ $d['rekapHadir']['izin'] }}</td>
                        <td class="ctr">{{ $d['rekapHadir']['alpa'] }}</td>
                        <td class="ctr">{{ $d['rekapHadir']['total'] }}</td>
                        <td class="ctr"><b>{{ $d['rekapHadir']['total'] > 0 ? round($d['rekapHadir']['hadir'] / $d['rekapHadir']['total'] * 100, 1) : 0 }}%</b></td>
                    </tr>
                </tbody>
            </table>

            <div class="ttd">
                <div class="ttd-col">Orang Tua<div class="spacer"></div>(....)</div>
                <div class="ttd-col">&nbsp;</div>
                <div class="ttd-col">{{ $d['pengaturan']->pengaturan['kota'] ?? 'Makassar' }}, {{ date('d F Y') }}<br>Guru Kelas<div class="spacer"></div><b><u>{{ $d['guru']->nama_lengkap }}</u></b></div>
            </div>
        </div>
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
