<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor P5BK - {{ $siswa->nama_peserta_didik }}</title>
    <style>
        @page { size: A4; margin: 1.5cm; }
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 28px; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 16px; }
        .kop h2 { margin: 0; font-size: 18px; text-transform: uppercase; }
        table.p5 { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 12px; }
        table.p5 th, table.p5 td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        table.p5 th { background: #f0f0f0; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:14px">
        <button onclick="window.print()" style="padding:7px 14px;cursor:pointer">Cetak / Simpan PDF</button>
        <a href="{{ route('admin.cetak.nilai') }}" style="margin-left:8px">Kembali</a>
    </div>

    <div class="kop">
        <h2>RAPOR PROJEK PENGUATAN PROFIL PELAJAR PANCASILA (P5BK)</h2>
        <p style="margin:4px 0 0;font-size:13px">{{ $sekolah->nama_sekolah ?? 'Sekolah Dasar' }} &bull; Semester {{ $semester }} {{ $tahunAjaran }}</p>
    </div>

    <table style="width:100%;font-size:13px;margin-bottom:10px">
        <tr><td style="width:180px">Nama Peserta Didik</td><td style="width:10px">:</td><td><strong>{{ $siswa->nama_peserta_didik }}</strong></td></tr>
        <tr><td>NIS / NISN</td><td>:</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
        <tr><td>Kelas</td><td>:</td><td>{{ $siswa->kelas ?? '-' }}</td></tr>
    </table>

    <table class="p5">
        <thead>
            <tr>
                <th style="width:36px">No</th>
                <th>Tema Projek</th>
                <th>Dimensi</th>
                <th style="width:90px">Capaian</th>
                <th>Catatan Proses</th>
            </tr>
        </thead>
        <tbody>
            @forelse($penilaian as $i => $p)
                <tr>
                    <td style="text-align:center">{{ $i + 1 }}</td>
                    <td>{{ e($p->tema) }}</td>
                    <td>{{ e($p->dimensi) }}</td>
                    <td style="text-align:center"><strong>{{ $p->predikat }}</strong></td>
                    <td>{{ e($p->catatan ?? '-') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center">Belum ada penilaian P5BK untuk periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="font-size:11.5px;margin-top:12px">
        Keterangan Capaian: <strong>BB</strong> = Belum Berkembang, <strong>MB</strong> = Mulai Berkembang, <strong>BSH</strong> = Berkembang Sesuai Harapan, <strong>SB</strong> = Sangat Berkembang.
    </p>
</body>
</html>
