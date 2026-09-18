<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>e-Rapor - {{ $siswa->nama_peserta_didik }}</title>
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
        tr:nth-child(even) td { background: #F8FAFC; }
        .deskripsi-box { border: 1px solid #999; padding: 8px; border-radius: 4px; background: #FBF4E4; margin-bottom: 10px; font-size: 10.5px; text-align: justify; }
        .ttd { margin-top: 25px; display: flex; justify-content: space-between; text-align: center; font-size: 10px; }
        .ttd .spacer { height: 55px; }
        .ttd-col { width: 30%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="kop">
            <h1>{{ strtoupper($pengaturan->pengaturan['sekolah'] ?? 'SEKOLAH') }}</h1>
            <h2>LAPORAN HASIL BELAJAR PESERTA DIDIK</h2>
            <p>{{ $pengaturan->pengaturan['alamat'] ?? '' }}</p>
            <p>NPSN: {{ $pengaturan->pengaturan['npsn'] ?? '-' }} | Tahun Pelajaran: {{ $pengaturan->tahun_pelajaran ?? '-' }} | Semester: {{ $pengaturan->semester ?? '-' }}</p>
        </div>

        <div class="section-title">A. IDENTITAS PESERTA DIDIK</div>
        <div class="identitas">
            <div class="row"><div class="label">Nama Lengkap</div><div>: {{ $siswa->nama_peserta_didik }}</div></div>
            <div class="row"><div class="label">NISN</div><div>: {{ $siswa->nisn ?? '-' }}</div></div>
            <div class="row"><div class="label">NIS</div><div>: {{ $siswa->nis }}</div></div>
            <div class="row"><div class="label">Jenis Kelamin</div><div>: {{ $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</div></div>
            <div class="row"><div class="label">Tempat, Tgl Lahir</div><div>: {{ $siswa->tempat_lahir ?? '-' }}, {{ $siswa->tanggal_lahir ? \Carbon\Carbon::parse($siswa->tanggal_lahir)->translatedFormat('d F Y') : '-' }}</div></div>
            <div class="row"><div class="label">Agama</div><div>: {{ $siswa->agama ?? '-' }}</div></div>
            <div class="row"><div class="label">Kelas</div><div>: {{ $siswa->kelas }}</div></div>
        </div>

        <div class="section-title">B. NILAI MATA PELAJARAN</div>
        <table>
            <thead>
                <tr>
                    <th style="width:25px">No</th>
                    <th>Mata Pelajaran</th>
                    <th style="width:60px">Formatif</th>
                    <th style="width:60px">Sumatif</th>
                    <th style="width:70px">Sumatif Akhir</th>
                    <th style="width:60px">Nilai Akhir</th>
                    <th style="width:50px">Predikat</th>
                </tr>
            </thead>
            <tbody>
                @forelse($nilaiMapel as $i => $n)
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
                    <tr><td colspan="7" class="ctr" style="padding:12px;color:#666">Belum ada nilai</td></tr>
                @endforelse
            </tbody>
        </table>

        @if($nilaiMapel->whereNotNull('deskripsi_capaian')->count() > 0)
            <div class="section-title">C. DESKRIPSI CAPAIAN KOMPETENSI</div>
            @foreach($nilaiMapel as $n)
                @if($n->deskripsi_capaian)
                    <div class="deskripsi-box"><b>{{ $n->mata_pelajaran }}:</b> {{ $n->deskripsi_capaian }}</div>
                @endif
            @endforeach
        @endif

        @if($dimensi->count() > 0)
            <div class="section-title">D. PROFIL LULUSAN (8 Dimensi)</div>
            <table>
                <thead><tr><th style="width:25px">No</th><th>Dimensi</th><th style="width:60px">Skor</th><th style="width:180px">Predikat</th></tr></thead>
                <tbody>
                    @foreach($dimensi as $i => $d)
                        <tr><td class="ctr">{{ $i + 1 }}</td><td>{{ $d->dimensi }}</td><td class="ctr"><b>{{ $d->skor }}</b></td><td>{{ $d->predikat }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title">E. REKAPITULASI KEHADIRAN</div>
        <table>
            <thead><tr><th>Hadir</th><th>Sakit</th><th>Izin</th><th>Alpa</th><th>Total</th><th>% Hadir</th></tr></thead>
            <tbody>
                <tr>
                    <td class="ctr">{{ $rekapHadir['hadir'] }}</td>
                    <td class="ctr">{{ $rekapHadir['sakit'] }}</td>
                    <td class="ctr">{{ $rekapHadir['izin'] }}</td>
                    <td class="ctr">{{ $rekapHadir['alpa'] }}</td>
                    <td class="ctr">{{ $rekapHadir['total'] }}</td>
                    <td class="ctr"><b>{{ $rekapHadir['total'] > 0 ? round($rekapHadir['hadir'] / $rekapHadir['total'] * 100, 1) : 0 }}%</b></td>
                </tr>
            </tbody>
        </table>

        @if($catatan->count() > 0)
            <div class="section-title">F. CATATAN GURU</div>
            <ul style="font-size:10.5px;padding-left:20px">
                @foreach($catatan as $c)
                    <li style="margin-bottom:3px">{{ $c->catatan }} <i style="color:#666">({{ \Carbon\Carbon::parse($c->tanggal)->format('d/m/Y') }})</i></li>
                @endforeach
            </ul>
        @endif

        <div class="ttd">
            <div class="ttd-col">
                Mengetahui,<br>Orang Tua/Wali
                <div class="spacer"></div>
                (................................)<br>
                <small>{{ $ayah->nama ?? $ibu->nama ?? '-' }}</small>
            </div>
            <div class="ttd-col">&nbsp;</div>
            <div class="ttd-col">
                {{ $pengaturan->pengaturan['kota'] ?? 'Makassar' }}, {{ date('d F Y') }}<br>
                Guru Kelas
                <div class="spacer"></div>
                <b><u>{{ $guru->nama_lengkap }}</u></b><br>
                <small>NIP. {{ $guru->rekaman['nip'] ?? '-' }}</small>
            </div>
        </div>

        <div style="text-align:center;margin-top:20px;font-size:10px">
            Mengetahui, Kepala Sekolah
            <div style="height:55px"></div>
            <b><u>{{ $pengaturan->pengaturan['kepsek'] ?? '-' }}</u></b><br>
            <small>NIP. {{ $pengaturan->pengaturan['nipKepsek'] ?? '-' }}</small>
        </div>
    </div>
</body>
</html>
