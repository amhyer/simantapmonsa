<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor {{ $semester }} - {{ $siswa->nama_peserta_didik }}</title>
    <style>
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 32px; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 16px; }
        .kop h2 { margin: 0; font-size: 20px; text-transform: uppercase; }
        .kop p { margin: 2px 0; font-size: 13px; }
        .judul { text-align: center; margin: 16px 0; }
        .judul h3 { margin: 0; font-size: 17px; text-decoration: underline; }
        .biodata { width: 100%; font-size: 14px; margin-bottom: 12px; }
        .biodata td { padding: 2px 6px; vertical-align: top; }
        table.nilai { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
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
        <a href="{{ route('admin.cetak.nilai') }}" style="margin-left:8px">Kembali</a>
    </div>

    <div class="kop">
        <h2>{{ $sekolah->nama_sekolah ?? 'Sekolah Dasar' }}</h2>
        <p>{{ $sekolah->alamat ?? '' }}{{ isset($sekolah->kecamatan) ? ', Kec. ' . $sekolah->kecamatan : '' }}</p>
        <p>NPSN: {{ $sekolah->npsn ?? '-' }} &bull; Telp: {{ $sekolah->telepon ?? '-' }}</p>
    </div>

    <div class="judul">
        <h3>LAPORAN HASIL BELAJAR PESERTA DIDIK</h3>
        <p>Semester {{ $semester }} Tahun Ajaran {{ $tahunAjaran }}</p>
    </div>

    <table class="biodata">
        <tr><td style="width:200px">Nama Peserta Didik</td><td style="width:12px">:</td><td><strong>{{ $siswa->nama_peserta_didik }}</strong></td></tr>
        <tr><td>NIS / NISN</td><td>:</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
        <tr><td>Kelas</td><td>:</td><td>{{ $siswa->kelas ?? '-' }}</td></tr>
    </table>

    <table class="nilai">
        <thead>
            <tr>
                <th style="width:36px">No</th>
                <th>Muatan Pelajaran</th>
                <th style="width:80px">Nilai</th>
                <th style="width:70px">Predikat</th>
                <th>Deskripsi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($nilai as $i => $n)
                <tr>
                    <td style="text-align:center">{{ $i + 1 }}</td>
                    <td>{{ e($n->mata_pelajaran) }}</td>
                    <td style="text-align:center">{{ $n->nilai_akhir !== null ? number_format((float) $n->nilai_akhir, 0) : '-' }}</td>
                    <td style="text-align:center">{{ $n->predikat ?? '-' }}</td>
                    <td>{{ e($n->deskripsi_capaian ?? '-') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center">Belum ada nilai rapor untuk periode ini.</td>
                </tr>
            @endforelse
            @if($nilai->whereNotNull('nilai_akhir')->count())
                <tr>
                    <td colspan="2" style="text-align:right"><strong>Rata-rata</strong></td>
                    <td style="text-align:center"><strong>{{ number_format($rata, 2, ',', '.') }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="biodata" style="margin-top:12px">
        <tr><td style="width:200px">Sakit</td><td style="width:12px">:</td><td>{{ $hadir['S'] }} hari</td></tr>
        <tr><td>Izin</td><td>:</td><td>{{ $hadir['I'] }} hari</td></tr>
        <tr><td>Tanpa Keterangan</td><td>:</td><td>{{ $hadir['A'] }} hari</td></tr>
        <tr><td>Catatan Wali Kelas</td><td>:</td><td>{{ e($catatan->catatan ?? '-') }}</td></tr>
    </table>

    <div class="ttd">
        <div>
            <p>Mengetahui,<br>Kepala Sekolah</p>
            <br><br><br>
            <p><strong><u>{{ $sekolah->kepala_sekolah ?? '........................' }}</u></strong></p>
        </div>
        <div>
            <p>{{ $tanggalRapor->tempat ?? '................' }}, {{ $tanggalRapor && $tanggalRapor->tanggal ? $tanggalRapor->tanggal->format('d-m-Y') : now()->format('d-m-Y') }}</p>
            <p>Wali Kelas</p>
            <br><br><br>
            <p><strong><u>{{ $siswa->nama_guru ?? '........................' }}</u></strong></p>
        </div>
    </div>
</body>
</html>
