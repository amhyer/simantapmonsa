@php
    $cfg = $raporConfig ?? [];
    $labelKementerian = ($cfg['kementerian'] ?? 'Kemendikdasmen') === 'Kemendikbudristek'
        ? 'KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI'
        : 'KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH';
    $pageSize = ($cfg['ukuran_kertas'] ?? 'A4') === 'F4' ? '215mm 330mm' : 'A4';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor Tengah Semester (PTS) - {{ $siswa->nama_peserta_didik }}</title>
    <style>
        @page { size: {{ $pageSize }}; margin: 1.5cm; }
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 28px; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 14px; }
        .kop .kementerian { font-size: 11px; font-weight: bold; }
        .kop h2 { margin: 2px 0; font-size: 19px; text-transform: uppercase; }
        .kop p { margin: 2px 0; font-size: 12px; }
        .judul { text-align: center; margin: 14px 0; }
        .judul h3 { margin: 0; font-size: 16px; text-decoration: underline; }
        table.nilai { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        table.nilai th, table.nilai td { border: 1px solid #000; padding: 6px 8px; }
        table.nilai th { background: #f0f0f0; }
        .ttd { display: flex; justify-content: space-between; margin-top: 28px; font-size: 13px; }
        .ttd div { width: 45%; text-align: center; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:14px">
        <button onclick="window.print()" style="padding:7px 14px;cursor:pointer">Cetak / Simpan PDF</button>
        <a href="{{ route('admin.cetak.nilai') }}" style="margin-left:8px">Kembali</a>
    </div>

    <div class="kop">
        <div class="kementerian">{{ $labelKementerian }}</div>
        <h2>{{ $sekolah->nama_sekolah ?? 'Sekolah Dasar' }}</h2>
        <p>{{ $sekolah->alamat ?? '' }} &bull; NPSN: {{ $sekolah->npsn ?? '-' }}</p>
    </div>

    <div class="judul">
        <h3>LAPORAN HASIL BELAJAR TENGAH SEMESTER (PTS)</h3>
        <p>Semester {{ $semester }} Tahun Ajaran {{ $tahunAjaran }}</p>
    </div>

    <table style="width:100%;font-size:13px;margin-bottom:10px">
        <tr><td style="width:180px">Nama Peserta Didik</td><td style="width:10px">:</td><td><strong>{{ $siswa->nama_peserta_didik }}</strong></td></tr>
        <tr><td>NIS / NISN</td><td>:</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
        <tr><td>Kelas</td><td>:</td><td>{{ $siswa->kelas ?? '-' }}</td></tr>
    </table>

    <table class="nilai">
        <thead>
            <tr>
                <th style="width:36px">No</th>
                <th>Muatan Pelajaran</th>
                <th style="width:90px">Formatif</th>
                <th style="width:90px">Sumatif</th>
                <th style="width:90px">Rata-rata</th>
            </tr>
        </thead>
        <tbody>
            @forelse($nilai as $i => $n)
                @php
                    $vals = array_filter([$n->formatif, $n->sumatif], fn ($v) => $v !== null);
                    $avg = count($vals) ? array_sum($vals) / count($vals) : $n->nilai_akhir;
                @endphp
                <tr>
                    <td style="text-align:center">{{ $i + 1 }}</td>
                    <td>{{ e($n->mata_pelajaran) }}</td>
                    <td style="text-align:center">{{ $n->formatif !== null ? number_format((float) $n->formatif, 0) : '-' }}</td>
                    <td style="text-align:center">{{ $n->sumatif !== null ? number_format((float) $n->sumatif, 0) : '-' }}</td>
                    <td style="text-align:center"><strong>{{ $avg !== null ? number_format((float) $avg, 0) : '-' }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center">Belum ada data nilai tengah semester.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="font-size:13px;margin-top:12px">
        Kehadiran: Sakit {{ $hadir['S'] ?? 0 }} hari &bull; Izin {{ $hadir['I'] ?? 0 }} hari &bull; Tanpa Keterangan {{ $hadir['A'] ?? 0 }} hari
    </p>

    <div class="ttd">
        <div>
            <p>Mengetahui,<br>Kepala Sekolah</p>
            @if(!empty($cfg['ttd_kepsek_data_uri']))
                <img src="{{ $cfg['ttd_kepsek_data_uri'] }}" alt="TTD Kepsek" style="max-height:55px;margin:4px auto;display:block">
            @else
                <br><br><br>
            @endif
            <p><strong><u>{{ $sekolah->kepala_sekolah ?? '........................' }}</u></strong></p>
        </div>
        <div>
            <p>Wali Kelas</p>
            @if(!empty($ttdWaliUri))
                <img src="{{ $ttdWaliUri }}" alt="TTD Wali Kelas" style="max-height:55px;margin:4px auto;display:block">
            @else
                <br><br><br>
            @endif
            <p><strong><u>{{ $siswa->nama_guru ?? '........................' }}</u></strong></p>
        </div>
    </div>
</body>
</html>
