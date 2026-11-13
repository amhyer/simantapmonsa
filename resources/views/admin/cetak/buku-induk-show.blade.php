<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Induk Siswa - {{ $siswa->nama_peserta_didik }}</title>
    <style>
        @page { size: A4; margin: 1.5cm; }
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 28px; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 16px; }
        .kop h2 { margin: 0; font-size: 19px; text-transform: uppercase; }
        table.bio { width: 100%; font-size: 13px; margin-bottom: 16px; }
        table.bio td { padding: 3px 6px; }
        table.nilai { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-bottom: 16px; }
        table.nilai th, table.nilai td { border: 1px solid #000; padding: 5px 8px; }
        table.nilai th { background: #f0f0f0; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:14px">
        <button onclick="window.print()" style="padding:7px 14px;cursor:pointer">Cetak / Simpan PDF</button>
        <a href="{{ route('admin.cetak.nilai') }}" style="margin-left:8px">Kembali</a>
    </div>

    <div class="kop">
        <h2>BUKU INDUK PESERTA DIDIK</h2>
        <p style="margin:4px 0 0;font-size:13px">{{ $sekolah->nama_sekolah ?? 'Sekolah Dasar' }} &bull; NPSN: {{ $sekolah->npsn ?? '-' }}</p>
    </div>

    <table class="bio">
        <tr><td style="width:190px">Nama Lengkap</td><td style="width:10px">:</td><td><strong>{{ $siswa->nama_peserta_didik }}</strong></td></tr>
        <tr><td>NIS / NISN</td><td>:</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
        <tr><td>Jenis Kelamin</td><td>:</td><td>{{ $siswa->jenis_kelamin === 'P' ? 'Perempuan' : ($siswa->jenis_kelamin === 'L' ? 'Laki-laki' : ($siswa->jenis_kelamin ?? '-')) }}</td></tr>
        <tr><td>Kelas Terakhir</td><td>:</td><td>{{ $siswa->kelas ?? '-' }}</td></tr>
    </table>

    <h4 style="margin:12px 0 8px;text-decoration:underline">REKAPITULASI NILAI HASIL BELAJAR</h4>
    @forelse($riwayatNilai as $periode => $items)
        <p style="font-weight:bold;margin:10px 0 4px;font-size:13px">{{ $periode }}</p>
        <table class="nilai">
            <thead>
                <tr>
                    <th style="width:36px">No</th>
                    <th>Mata Pelajaran</th>
                    <th style="width:80px">Nilai Akhir</th>
                    <th style="width:70px">Predikat</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $n)
                    <tr>
                        <td style="text-align:center">{{ $i + 1 }}</td>
                        <td>{{ e($n->mata_pelajaran) }}</td>
                        <td style="text-align:center">{{ $n->nilai_akhir !== null ? number_format((float) $n->nilai_akhir, 0) : '-' }}</td>
                        <td style="text-align:center">{{ $n->predikat ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p style="font-size:13px">Belum ada riwayat nilai tercatat di buku induk.</p>
    @endforelse
</body>
</html>
