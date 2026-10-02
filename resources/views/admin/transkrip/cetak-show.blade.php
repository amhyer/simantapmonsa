<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transkrip Nilai - {{ $siswa->nama_peserta_didik }}</title>
    <style>
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 32px; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 16px; }
        .kop h2 { margin: 0; font-size: 20px; text-transform: uppercase; }
        .kop p { margin: 2px 0; font-size: 13px; }
        .judul { text-align: center; margin: 16px 0; }
        .judul h3 { margin: 0; font-size: 17px; text-decoration: underline; }
        .biodata { width: 100%; font-size: 14px; margin-bottom: 12px; }
        .biodata td { padding: 2px 6px; vertical-align: top; }
        table.nilai { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 8px; }
        table.nilai th, table.nilai td { border: 1px solid #000; padding: 6px 8px; }
        table.nilai th { background: #f0f0f0; }
        .ttd { display: flex; justify-content: space-between; margin-top: 32px; font-size: 14px; }
        .ttd div { text-align: center; width: 45%; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 16px;cursor:pointer">Cetak / Simpan PDF</button>
        <a href="{{ route('admin.transkrip.cetak') }}" style="margin-left:8px">Kembali</a>
    </div>

    <div class="kop">
        <h2>{{ $sekolah->nama_sekolah ?? 'Sekolah Dasar' }}</h2>
        <p>{{ $sekolah->alamat ?? '' }}{{ isset($sekolah->kecamatan) ? ', Kec. ' . $sekolah->kecamatan : '' }}</p>
        <p>NPSN: {{ $sekolah->npsn ?? '-' }} &bull; Telp: {{ $sekolah->telepon ?? '-' }}</p>
    </div>

    <div class="judul">
        <h3>TRANSKRIP NILAI HASIL BELAJAR</h3>
        <p>Nomor Ijazah: {{ $siswa->nomor_ijazah ?? '........................' }}</p>
    </div>

    <table class="biodata">
        <tr><td style="width:200px">Nama Peserta Didik</td><td style="width:12px">:</td><td><strong>{{ $siswa->nama_peserta_didik }}</strong></td></tr>
        <tr><td>NIS / NISN</td><td>:</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
        <tr><td>Tempat, Tanggal Lahir</td><td>:</td><td>{{ $siswa->tempat_lahir ?? '-' }}, {{ $siswa->tanggal_lahir ? \Carbon\Carbon::parse($siswa->tanggal_lahir)->format('d-m-Y') : '-' }}</td></tr>
        <tr><td>Tahun Pelajaran Lulus</td><td>:</td><td>{{ $setting['tahun_lulus'] ?? '-' }}</td></tr>
    </table>

    <table class="nilai">
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Mata Pelajaran</th>
                <th style="width:110px">Nilai Akhir</th>
                <th style="width:90px">Predikat</th>
            </tr>
        </thead>
        <tbody>
            @forelse($nilai as $i => $n)
                <tr>
                    <td style="text-align:center">{{ $i + 1 }}</td>
                    <td>{{ e($n->nama_mapel) }}</td>
                    <td style="text-align:center">{{ number_format((float) $n->nilai_akhir, 2, ',', '.') }}</td>
                    <td style="text-align:center">{{ $n->predikat ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center">Belum ada nilai transkrip.</td>
                </tr>
            @endforelse
            @if($nilai->count())
                <tr>
                    <td colspan="2" style="text-align:right"><strong>Rata-rata</strong></td>
                    <td style="text-align:center"><strong>{{ number_format((float) $rata, 2, ',', '.') }}</strong></td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    @if(!empty($setting['keterangan']))
        <p style="font-size:13px;margin-top:12px">Keterangan: {{ e($setting['keterangan']) }}</p>
    @endif

    <div class="ttd">
        <div>
            <p>Mengetahui,<br>Kepala Sekolah</p>
            <br><br><br>
            <p><strong><u>{{ $sekolah->kepala_sekolah ?? '........................' }}</u></strong><br>
            NIP. {{ $setting['nip_kepala_sekolah'] ?? '........................' }}</p>
        </div>
        <div>
            <p>{{ e($setting['tempat'] ?? '................') }}, {{ now()->format('d-m-Y') }}</p>
            <br><br><br><br>
            <p><strong><u>&nbsp;</u></strong></p>
        </div>
    </div>
</body>
</html>
