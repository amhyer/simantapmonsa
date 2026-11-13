@php
    $cfg = $raporConfig ?? [
        'kementerian' => 'Kemendikdasmen',
        'ukuran_kertas' => 'A4',
        'margin_atas_cm' => 1.5,
        'kapitalisasi_nama' => 'asli',
        'format_desimal' => '0',
        'page_break_catatan' => false,
        'ttd_kepsek_data_uri' => null,
    ];
    $namaSiswa = match ($cfg['kapitalisasi_nama'] ?? 'asli') {
        'upper' => mb_strtoupper((string) $siswa->nama_peserta_didik),
        'title' => \Illuminate\Support\Str::title(mb_strtolower((string) $siswa->nama_peserta_didik)),
        default => $siswa->nama_peserta_didik,
    };
    $labelKementerian = ($cfg['kementerian'] ?? 'Kemendikdasmen') === 'Kemendikbudristek'
        ? 'KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI'
        : 'KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH';
    $pageSize = ($cfg['ukuran_kertas'] ?? 'A4') === 'F4' ? '215mm 330mm' : 'A4';
    $marginAtas = (float) ($cfg['margin_atas_cm'] ?? 1.5);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapor {{ $semester }} - {{ $namaSiswa }}</title>
    <style>
        @page { size: {{ $pageSize }}; margin: {{ $marginAtas }}cm 1.5cm 1.5cm 1.5cm; }
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 32px; padding-top: {{ max(0, $marginAtas - 1) }}cm; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 16px; }
        .kop .kementerian { font-size: 12px; font-weight: bold; letter-spacing: 0.4px; margin-bottom: 4px; }
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
        @media print { .no-print { display: none; } body { margin: 0; padding-top: 0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 16px;cursor:pointer">Cetak / Simpan PDF</button>
        <a href="{{ route('admin.cetak.nilai') }}" style="margin-left:8px">Kembali</a>
    </div>

    <div class="kop">
        <div class="kementerian">{{ $labelKementerian }}</div>
        <h2>{{ $sekolah->nama_sekolah ?? 'Sekolah Dasar' }}</h2>
        <p>{{ $sekolah->alamat ?? '' }}{{ isset($sekolah->kecamatan) ? ', Kec. ' . $sekolah->kecamatan : '' }}</p>
        <p>NPSN: {{ $sekolah->npsn ?? '-' }} &bull; Telp: {{ $sekolah->telepon ?? '-' }}</p>
    </div>

    <div class="judul">
        <h3>LAPORAN HASIL BELAJAR PESERTA DIDIK</h3>
        <p>Semester {{ $semester }} Tahun Ajaran {{ $tahunAjaran }}</p>
    </div>

    <table class="biodata">
        <tr><td style="width:200px">Nama Peserta Didik</td><td style="width:12px">:</td><td><strong>{{ $namaSiswa }}</strong></td></tr>
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
                    <td style="text-align:center">
                        @if($n->nilai_akhir !== null)
                            @php
                                $val = (float) $n->nilai_akhir;
                                $formatted = match ($cfg['format_desimal'] ?? '0') {
                                    '2' => number_format($val, 2, ',', '.'),
                                    'auto' => rtrim(rtrim(number_format($val, 2, ',', '.'), '0'), ','),
                                    default => number_format($val, 0, ',', '.'),
                                };
                            @endphp
                            {{ $formatted }}
                        @else
                            -
                        @endif
                    </td>
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

    <table class="biodata" style="margin-top:12px;{{ !empty($cfg['page_break_catatan']) ? 'page-break-before: always;' : '' }}">
        <tr><td style="width:200px">Sakit</td><td style="width:12px">:</td><td>{{ $hadir['S'] }} hari</td></tr>
        <tr><td>Izin</td><td>:</td><td>{{ $hadir['I'] }} hari</td></tr>
        <tr><td>Tanpa Keterangan</td><td>:</td><td>{{ $hadir['A'] }} hari</td></tr>
        <tr><td>Catatan Wali Kelas</td><td>:</td><td>{{ e($catatan->catatan ?? '-') }}</td></tr>
        @if(!empty($naikKelas))
            <tr>
                <td>Keputusan Kenaikan Kelas</td>
                <td>:</td>
                <td><strong>{{ $naikKelas->naik_kelas ? 'Naik ke kelas berikutnya' : 'Tinggal di kelas saat ini' }}</strong>{{ $naikKelas->catatan ? ' (' . e($naikKelas->catatan) . ')' : '' }}</td>
            </tr>
        @endif
    </table>

    <div class="ttd">
        <div>
            <p>Mengetahui,<br>Kepala Sekolah</p>
            @if(!empty($cfg['ttd_kepsek_data_uri']))
                <div style="height:64px;display:flex;align-items:center;justify-content:center;margin:4px 0">
                    <img src="{{ $cfg['ttd_kepsek_data_uri'] }}" alt="TTD Kepala Sekolah" style="max-height:60px;max-width:160px;object-fit:contain">
                </div>
            @else
                <br><br><br>
            @endif
            <p><strong><u>{{ $sekolah->kepala_sekolah ?? '........................' }}</u></strong></p>
        </div>
        <div>
            <p>{{ $tanggalRapor->tempat ?? '................' }}, {{ $tanggalRapor && $tanggalRapor->tanggal ? $tanggalRapor->tanggal->format('d-m-Y') : now()->format('d-m-Y') }}</p>
            <p>Wali Kelas</p>
            @if(!empty($ttdWaliUri))
                <div style="height:64px;display:flex;align-items:center;justify-content:center;margin:4px 0">
                    <img src="{{ $ttdWaliUri }}" alt="TTD Wali Kelas" style="max-height:60px;max-width:160px;object-fit:contain">
                </div>
            @else
                <br><br><br>
            @endif
            <p><strong><u>{{ $siswa->nama_guru ?? '........................' }}</u></strong></p>
        </div>
    </div>
</body>
</html>
