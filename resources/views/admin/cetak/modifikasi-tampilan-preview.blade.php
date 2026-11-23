@php
    $cfg = $raporConfig ?? [];
    $pageSize = ($cfg['ukuran_kertas'] ?? 'A4') === 'F4' ? '215mm 330mm' : 'A4';
    $marginAtas = (float) ($cfg['margin_atas_cm'] ?? 1.5);
    $labelKementerian = ($cfg['kementerian'] ?? 'Kemendikdasmen') === 'Kemendikbudristek'
        ? 'KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI'
        : 'KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH';
    $namaSample = $siswa->nama_peserta_didik ?? 'Contoh Peserta Didik';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pratinjau Tampilan Rapor - {{ $sekolah->nama_sekolah ?? 'SIMANTAP' }}</title>
    <style>
        @page { size: {{ $pageSize }}; margin: {{ $marginAtas }}cm 1.5cm 1.5cm 1.5cm; }
        body { color: #000; margin: 28px; }
        .judul { text-align: center; margin: 14px 0; }
        .judul h3 { margin: 0; text-decoration: underline; }
        table.biodata { width: 100%; font-size: 13px; margin-bottom: 10px; }
        table.nilai { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.nilai th, table.nilai td { border: 1px solid #000; padding: 6px 8px; }
        .ttd { display: flex; justify-content: space-between; margin-top: 28px; font-size: 13px; }
        .ttd div { width: 45%; text-align: center; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:14px;display:flex;gap:8px;align-items:center">
        <button type="button" onclick="window.print()" style="padding:8px 16px;cursor:pointer;background:#1F3864;color:#fff;border:none;border-radius:6px;font-weight:600">
            Cetak / Simpan PDF
        </button>
        <a href="{{ route('admin.cetak.modifikasi-tampilan', ['jenis' => $jenis === 'pts' ? 'semester' : 'pts']) }}" style="padding:8px 12px;border:1px solid #CBD5E1;border-radius:6px;text-decoration:none;color:#1E293B">
            Lihat Versi {{ $jenis === 'pts' ? 'Akhir Semester' : 'Tengah Semester (PTS)' }}
        </a>
        <a href="{{ route('admin.cetak.modifikasi-tampilan') }}" style="margin-left:auto;color:#1F3864">Kembali ke Editor</a>
    </div>

    @include('admin.cetak.partials.rapor-style-kop', ['jenis' => $jenis, 'forceApplyTampilan' => true])

    <div class="judul">
        <h3>{{ $jenis === 'pts' ? 'LAPORAN HASIL BELAJAR TENGAH SEMESTER (PTS)' : 'LAPORAN HASIL BELAJAR PESERTA DIDIK' }}</h3>
        <p>Pratinjau Tampilan Rapor &bull; {{ $sekolah->nama_sekolah ?? 'SIMANTAP' }}</p>
    </div>

    <table class="biodata">
        <tr><td style="width:180px">Nama Peserta Didik</td><td style="width:10px">:</td><td><strong>{{ $namaSample }}</strong></td></tr>
        <tr><td>NIS / NISN</td><td>:</td><td>{{ $siswa->nis ?? '2025001' }} / {{ $siswa->nisn ?? '0123456789' }}</td></tr>
        <tr><td>Kelas</td><td>:</td><td>{{ $siswa->kelas ?? '6.A' }}</td></tr>
    </table>

    <table class="nilai">
        <thead>
            <tr>
                <th style="width:36px">No</th>
                <th>Muatan Pelajaran</th>
                <th style="width:80px">Nilai</th>
                <th style="width:75px">Predikat</th>
                <th>Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
            <tr><td style="text-align:center">1</td><td>Pendidikan Agama dan Budi Pekerti</td><td style="text-align:center">90</td><td style="text-align:center">A</td><td>Menunjukkan penguasaan sangat baik dalam praktik ibadah dan akhlak mulia.</td></tr>
            <tr><td style="text-align:center">2</td><td>Pendidikan Pancasila</td><td style="text-align:center">88</td><td style="text-align:center">A</td><td>Mampu menerapkan nilai gotong royong dan musyawarah di lingkungan kelas.</td></tr>
            <tr><td style="text-align:center">3</td><td>Bahasa Indonesia</td><td style="text-align:center">86</td><td style="text-align:center">B</td><td>Baik dalam memahami teks narasi serta menyajikan gagasan secara runtut.</td></tr>
            <tr><td style="text-align:center">4</td><td>Matematika</td><td style="text-align:center">84</td><td style="text-align:center">B</td><td>Cakap dalam operasi pecahan, rasio, dan pemecahan masalah bangun ruang.</td></tr>
        </tbody>
    </table>

    @if($jenis === 'semester' || !empty($tampilanRapor['pts_tabel_ekskul']))
        <table class="nilai" style="margin-top:12px">
            <thead>
                <tr>
                    <th style="width:36px">No</th>
                    <th>Kegiatan Ekstrakurikuler</th>
                    <th style="width:110px">Predikat</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="text-align:center">1</td><td>Pramuka</td><td style="text-align:center">Sangat Baik</td><td>Aktif berpartisipasi dalam kegiatan kepramukaan mingguan.</td></tr>
            </tbody>
        </table>
    @endif

    <div class="ttd" style="{{ ($jenis === 'pts' && empty($tampilanRapor['pts_ttd_kepsek'])) ? 'justify-content:flex-end;' : '' }}">
        @if($jenis === 'semester' || !empty($tampilanRapor['pts_ttd_kepsek']))
            <div>
                <p>Mengetahui,<br>Kepala Sekolah</p>
                @if(!empty($cfg['ttd_kepsek_data_uri']))
                    <img src="{{ $cfg['ttd_kepsek_data_uri'] }}" alt="TTD Kepsek" style="max-height:55px;margin:4px auto;display:block">
                @else
                    <br><br><br>
                @endif
                <p><strong><u>{{ $sekolah->kepala_sekolah ?? 'Nama Kepala Sekolah' }}</u></strong></p>
            </div>
        @endif
        <div>
            <p>Wali Kelas</p>
            <br><br><br>
            <p><strong><u>Nama Wali Kelas</u></strong></p>
        </div>
    </div>
</body>
</html>
