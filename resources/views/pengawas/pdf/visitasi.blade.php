<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Visitasi &amp; Observasi Kelas</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; line-height: 1.45; }
        .header { text-align: center; border-bottom: 2px solid #1F3864; padding-bottom: 10px; margin-bottom: 14px; }
        .header h2 { margin: 0; font-size: 15px; text-transform: uppercase; color: #1F3864; }
        .header p { margin: 3px 0 0; font-size: 11px; color: #4B5563; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #CBD5E1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #F1F5F9; font-weight: bold; }
        .box { border: 1px solid #CBD5E1; padding: 8px 10px; border-radius: 4px; margin-bottom: 10px; background: #FAFAFA; }
        .box-title { font-weight: bold; color: #1F3864; margin-bottom: 4px; }
    </style>
</head>
<body>
    @php
        $ttd = app(\App\Services\PembelajaranMendalam\TandaTanganPdfService::class)->resolveForPdf($sekolah);
        $namaPengawas = $ttd['nama_pengawas'] ?: $pengawas->nama_lengkap;
        $nipPengawas = $ttd['nip_pengawas'] ?: ($pengawas->nip ?? '');
    @endphp

    <div class="header">
        <h2>BERITA ACARA VISITASI &amp; INSTRUMEN OBSERVASI KELAS PEMBELAJARAN MENDALAM</h2>
        <p>{{ $sekolah->nama_sekolah ?? 'Satuan Pendidikan Binaan' }} · Tahun Ajaran {{ $visitasi->tahun_ajaran }} · Semester {{ $visitasi->semester }}</p>
    </div>

    <table>
        <tr>
            <th style="width:28%">Tanggal Visitasi</th>
            <td>{{ $visitasi->tanggal_visitasi->format('d/m/Y') }}</td>
            <th style="width:25%">Pengawas Pembina</th>
            <td>{{ $namaPengawas }}</td>
        </tr>
        <tr>
            <th>Jenis Pendampingan</th>
            <td>{{ $visitasi->jenisLabel() }}</td>
            <th>Sasaran / Guru</th>
            <td>{{ $visitasi->guru?->nama_lengkap ?? 'Seluruh Satuan Pendidikan (Supervisi Manajerial)' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:36px">No</th>
                <th>Dimensi / Indikator Observasi Pembelajaran Mendalam</th>
                <th style="width:90px;text-align:center">Skor (1–4)</th>
                <th>Keterangan Rubrik</th>
            </tr>
        </thead>
        <tbody>
            @foreach([
                ['1. Prinsip Berkesadaran (Mindful Learning)', $visitasi->skor_berkesadaran],
                ['2. Prinsip Bermakna (Meaningful Learning)', $visitasi->skor_bermakna],
                ['3. Prinsip Menggembirakan (Joyful Learning)', $visitasi->skor_menggembirakan],
                ['4. Pengalaman Belajar: Memahami', $visitasi->skor_memahami],
                ['5. Pengalaman Belajar: Mengaplikasi', $visitasi->skor_mengaplikasi],
                ['6. Pengalaman Belajar: Merefleksi', $visitasi->skor_merefleksi],
            ] as $idx => [$label, $skor])
                <tr>
                    <td style="text-align:center">{{ $idx + 1 }}</td>
                    <td>{{ $label }}</td>
                    <td style="text-align:center;font-weight:bold">{{ $skor }}</td>
                    <td>{{ \App\Models\PembelajaranMendalam\VisitasiPengawas::SKALA_RUBRIK[$skor] ?? '-' }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="2" style="text-align:right">Rata-rata Skor &amp; Predikat Keterlaksanaan</th>
                <th style="text-align:center">{{ $visitasi->rataRataSkor() }} / 4.00</th>
                <th>{{ $visitasi->predikatObservasi() }} ({{ $visitasi->persenSkor() }}%)</th>
            </tr>
        </tbody>
    </table>

    <div class="box">
        <div class="box-title">A. Temuan Lapangan / Praktik Baik</div>
        <div>{!! nl2br(e($visitasi->temuan)) !!}</div>
    </div>

    <div class="box">
        <div class="box-title">B. Rekomendasi Pembinaan Pengawas</div>
        <div>{!! nl2br(e($visitasi->rekomendasi)) !!}</div>
    </div>

    @if($visitasi->tindak_lanjut)
        <div class="box">
            <div class="box-title">C. Kesepakatan Rencana Tindak Lanjut (RTL)</div>
            <div>{!! nl2br(e($visitasi->tindak_lanjut)) !!}</div>
        </div>
    @endif

    <table style="border:none;margin-top:20px">
        <tr>
            <td style="border:none;width:50%;text-align:center">
                <div>Mengetahui,</div>
                <div>Kepala {{ $sekolah->nama_sekolah ?? 'Sekolah' }}</div>
                <div style="height:60px;margin:6px 0">
                    @if(!empty($ttd['ttd_kepsek_uri']))
                        <img src="{{ $ttd['ttd_kepsek_uri'] }}" style="max-height:55px">
                    @endif
                </div>
                <div style="font-weight:bold;text-decoration:underline">{{ $ttd['nama_kepsek'] ?: '....................................' }}</div>
                <div>NIP. {{ $ttd['nip_kepsek'] ?: '....................................' }}</div>
            </td>
            <td style="border:none;width:50%;text-align:center">
                <div>{{ $ttd['kota'] ?: 'Makassar' }}, {{ $visitasi->tanggal_visitasi->format('d/m/Y') }}</div>
                <div>Pengawas Sekolah Pembina,</div>
                <div style="height:60px;margin:6px 0">
                    @if(!empty($ttd['ttd_pengawas_uri']))
                        <img src="{{ $ttd['ttd_pengawas_uri'] }}" style="max-height:55px">
                    @endif
                </div>
                <div style="font-weight:bold;text-decoration:underline">{{ $namaPengawas }}</div>
                <div>NIP. {{ $nipPengawas ?: '....................................' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
