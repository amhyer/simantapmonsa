@php
    $cfg = $raporConfig ?? [];
    $opts = $printOpts ?? [
        'kertas' => $cfg['ukuran_kertas'] ?? 'A4',
        'margin_kiri' => 15,
        'margin_kanan' => 15,
        'margin_atas' => ($cfg['margin_atas_cm'] ?? 1.5) * 10,
        'margin_bawah' => 12,
    ];
    $pageSize = ($opts['kertas'] ?? 'A4') === 'F4' ? '215mm 330mm' : 'A4';
    $labelKementerian = ($cfg['kementerian'] ?? 'Kemendikdasmen') === 'Kemendikbudristek'
        ? 'KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI'
        : 'KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapor {{ $semester }} - {{ $siswa->nama_peserta_didik }}</title>
    <style>
        @page {
            size: {{ $pageSize }};
            margin: {{ $opts['margin_atas'] }}mm {{ $opts['margin_kanan'] }}mm {{ $opts['margin_bawah'] }}mm {{ $opts['margin_kiri'] }}mm;
        }
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 24px auto; max-width: 210mm; font-size: 12.5px; line-height: 1.38; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 12px; }
        .kop .kementerian { font-size: 11px; font-weight: bold; letter-spacing: 0.4px; margin-bottom: 2px; }
        .kop h2 { margin: 0; font-size: 17px; text-transform: uppercase; }
        .kop p { margin: 2px 0; font-size: 11.5px; }
        .identitas-grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 12px; }
        .identitas-grid > tbody > tr > td { vertical-align: top; padding: 0; }
        .id-sub { width: 100%; border-collapse: collapse; }
        .id-sub td { padding: 1.5px 4px; vertical-align: top; }
        .id-sub .lbl { width: 92px; }
        .id-sub .lbl-r { width: 88px; }
        .id-sub .sep { width: 10px; text-align: center; }
        .judul { text-align: center; margin: 10px 0 8px; }
        .judul h3 { margin: 0; font-size: 14.5px; letter-spacing: 0.4px; }
        table.nilai, table.box-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.nilai th, table.nilai td, table.box-table th, table.box-table td { border: 1px solid #000; padding: 5px 7px; vertical-align: top; }
        table.nilai th, table.box-table th { background: #f2f2f2; text-align: center; font-weight: bold; }
        tr.klp-row td { background: #fafafa; padding: 4px 7px; }
        .desc-cell { text-align: justify; line-height: 1.35; }
        .two-col-block { width: 100%; border-collapse: collapse; }
        .two-col-block > tbody > tr > td { padding: 0; }
        .keputusan-box { margin-top: 12px; border: 1px solid #000; padding: 7px 10px; text-align: center; font-size: 12.5px; }
        .ttd-grid { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; page-break-inside: avoid; }
        .ttd-grid td { text-align: center; vertical-align: top; padding: 0 6px; }
        .ttd-grid p { margin: 2px 0; }
        .ttd-space { height: 56px; display: flex; align-items: center; justify-content: center; margin: 4px 0; }
        .ttd-img { max-height: 54px; max-width: 150px; object-fit: contain; }
        .nip { font-size: 11px; }
        .rapor-footer { display: flex; justify-content: space-between; margin-top: 18px; padding-top: 6px; border-top: 1px solid #999; font-size: 10.5px; font-style: italic; color: #333; }
        .no-print { margin-bottom: 16px; display: flex; gap: 8px; align-items: center; }
        .no-print button, .no-print a { padding: 7px 14px; font-family: system-ui, sans-serif; font-size: 13px; border-radius: 6px; text-decoration: none; cursor: pointer; }
        .no-print button { background: #1E3A5F; color: #fff; border: none; font-weight: 600; }
        .no-print a { background: #EEF2F9; color: #1E3A5F; }
        @media print { .no-print { display: none; } body { margin: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Cetak / Simpan PDF</button>
        <a href="{{ route('admin.cetak.nilai', ['kelas' => $siswa->kelas, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}">Kembali ke Daftar Kelas</a>
    </div>

    @include('admin.cetak.partials.rapor-lembar')
</body>
</html>
