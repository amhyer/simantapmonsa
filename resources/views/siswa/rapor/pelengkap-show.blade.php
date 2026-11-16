<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelengkap Rapor & Biodata — {{ $siswa->nama_peserta_didik }}</title>
    <style>
        @page { size: A4; margin: 2cm; }
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 24px; line-height: 1.5; }
        .no-print { margin-bottom: 16px; }
        .cover-box { border: 3px double #000; padding: 36px 24px; text-align: center; margin-bottom: 32px; }
        .cover-box h1 { font-size: 22px; margin: 0 0 8px; text-transform: uppercase; }
        .cover-box h2 { font-size: 17px; margin: 0 0 24px; text-transform: uppercase; }
        .nama-box { border: 2px solid #000; padding: 14px; max-width: 360px; margin: 18px auto; font-size: 16px; font-weight: bold; }
        table.bio { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 12px; }
        table.bio td { padding: 4px 6px; vertical-align: top; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 16px;cursor:pointer;font-weight:bold">Cetak / Simpan PDF</button>
    </div>

    <div class="cover-box">
        <h1>RAPOR PESERTA DIDIK</h1>
        <h2>SEKOLAH DASAR (SD)</h2>
        <p style="margin:12px 0;font-size:15px"><strong>{{ $sekolah->nama_sekolah ?? config('app.name', 'SEKOLAH DASAR') }}</strong></p>
        <p style="font-size:13px">NPSN: {{ $sekolah->npsn ?? '-' }}</p>

        <div style="margin-top:28px;font-size:14px">Nama Peserta Didik:</div>
        <div class="nama-box">{{ $siswa->nama_peserta_didik }}</div>

        <div style="margin-top:12px;font-size:14px">NISN / NIS:</div>
        <div class="nama-box" style="font-size:15px">{{ $siswa->nisn ?? '-' }} / {{ $siswa->nis ?? '-' }}</div>

        <p style="margin-top:28px;font-size:14px;font-weight:bold">KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH<br>REPUBLIK INDONESIA</p>
    </div>

    @php
        $rek = is_array($siswa->rekaman) ? $siswa->rekaman : [];
        $pel = is_array($rek['pelengkap'] ?? null) ? $rek['pelengkap'] : [];
    @endphp
    <h3 style="text-align:center;text-decoration:underline;margin-bottom:16px">KETERANGAN TENTANG DIRI PESERTA DIDIK</h3>
    <table class="bio">
        <tr><td style="width:36px">1.</td><td style="width:220px">Nama Peserta Didik (Lengkap)</td><td style="width:14px">:</td><td><strong>{{ $siswa->nama_peserta_didik }}</strong></td></tr>
        <tr><td>2.</td><td>Nomor Induk / NISN</td><td>:</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
        <tr><td>3.</td><td>Tempat, Tanggal Lahir</td><td>:</td><td>{{ $siswa->tempat_lahir ?? '-' }}, {{ $siswa->tanggal_lahir ? $siswa->tanggal_lahir->translatedFormat('d F Y') : '-' }}</td></tr>
        <tr><td>4.</td><td>Jenis Kelamin</td><td>:</td><td>{{ $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</td></tr>
        <tr><td>5.</td><td>Agama</td><td>:</td><td>{{ $siswa->agama ?? '-' }}</td></tr>
        <tr><td>6.</td><td>Status dalam Keluarga</td><td>:</td><td>{{ $pel['status_dalam_keluarga'] ?? 'Anak Kandung' }}</td></tr>
        <tr><td>7.</td><td>Anak ke</td><td>:</td><td>{{ $siswa->anak_ke ?? ($pel['anak_ke'] ?? '-') }}</td></tr>
        <tr><td>8.</td><td>Alamat Peserta Didik</td><td>:</td><td>{{ $siswa->alamat ?? '-' }}</td></tr>
        <tr><td>9.</td><td>Nomor Telepon Rumah / HP</td><td>:</td><td>{{ $siswa->telepon ?? ($pel['telepon_ortu'] ?? '-') }}</td></tr>
        <tr><td>10.</td><td>Sekolah Asal</td><td>:</td><td>{{ $pel['asal_sekolah'] ?? '-' }}</td></tr>
        <tr><td>11.</td><td>Diterima di Sekolah ini (Kelas)</td><td>:</td><td>{{ $pel['diterima_di_kelas'] ?? ($siswa->kelas ?? '-') }}</td></tr>
        <tr><td>12.</td><td>Nama Orang Tua (Ayah / Ibu)</td><td>:</td><td>{{ ($pel['nama_ayah'] ?? null) || ($pel['nama_ibu'] ?? null) ? trim(($pel['nama_ayah'] ?? '-') . ' / ' . ($pel['nama_ibu'] ?? '-')) : ($siswa->nama_orang_tua ?? '-') }}</td></tr>
        <tr><td>13.</td><td>Pekerjaan Orang Tua (Ayah / Ibu)</td><td>:</td><td>{{ ($pel['pekerjaan_ayah'] ?? '-') . ' / ' . ($pel['pekerjaan_ibu'] ?? '-') }}</td></tr>
        <tr><td>14.</td><td>Alamat & Telepon Orang Tua</td><td>:</td><td>{{ ($pel['alamat_ortu'] ?? ($siswa->alamat ?? '-')) . ' (' . ($pel['telepon_ortu'] ?? ($siswa->telepon ?? '-')) . ')' }}</td></tr>
        <tr><td>15.</td><td>Wali Peserta Didik</td><td>:</td><td>{{ $pel['nama_wali'] ?? '-' }} ({{ $pel['pekerjaan_wali'] ?? '-' }})</td></tr>
    </table>
</body>
</html>
