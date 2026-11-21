<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Supervisi Pembelajaran Mendalam</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        h2 { margin: 0 0 3px; font-size: 13px; text-align: center; }
        .sub { text-align: center; font-size: 9.5px; color: #4B5563; margin-bottom: 10px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #374151; padding: 4px 5px; }
        table.data th { background: #F3F4F6; text-align: center; }
        .center { text-align: center; }
        .ok { color: #065F46; font-weight: bold; }
        .miss { color: #991B1B; }
    </style>
</head>
<body>
    <h2>REKAPITULASI SUPERVISI AKADEMIK PEMBELAJARAN MENDALAM</h2>
    <div class="sub">
        {{ $sekolah?->nama_sekolah ?? 'Sekolah Dasar' }} · Tahun Ajaran {{ $tahunAjaran }} · Semester {{ $semester }}
        · Rata-rata Kelengkapan: {{ $kpi['rata_kelengkapan'] }}% · Disetujui: {{ $kpi['disetujui'] }}/{{ $kpi['total_guru'] }} Guru
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:24px">No</th>
                <th>Nama Guru</th>
                <th>CP</th>
                <th>TP</th>
                <th>ATP</th>
                <th>KKTP</th>
                <th>Prota</th>
                <th>Prosem</th>
                <th>RPM</th>
                <th>Jurnal</th>
                <th>Koku</th>
                <th>Skor</th>
                <th>Status Supervisi</th>
                <th>Catatan Kepsek</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                @php $c = $row['counts']; @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td><strong>{{ $row['guru']->nama_lengkap }}</strong></td>
                    @foreach(['cp','tp','atp','kktp','prota','prosem','rpm','jurnal','kokurikuler'] as $k)
                        <td class="center {{ $c[$k] > 0 ? 'ok' : 'miss' }}">{{ $c[$k] > 0 ? $c[$k] : '-' }}</td>
                    @endforeach
                    <td class="center"><strong>{{ $row['persen'] }}%</strong></td>
                    <td class="center">{{ $row['status_label'] }}</td>
                    <td>{{ $row['supervisi']?->catatan_kepsek ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="14" class="center">Belum ada data guru.</td></tr>
            @endforelse
        </tbody>
    </table>

    @php
        $ttd = app(\App\Services\PembelajaranMendalam\TandaTanganPdfService::class)->resolveForPdf($sekolah ?? null, $kepsek ?? null);
    @endphp
    <table style="width:100%;border:none;margin-top:20px;page-break-inside:avoid">
        <tr>
            <td style="width:60%;border:none"></td>
            <td style="width:40%;border:none;text-align:center;vertical-align:top">
                <div>{{ $ttd['kota'] ? $ttd['kota'].', ' : '' }}{{ now()->format('d/m/Y') }}</div>
                <div>Kepala Sekolah</div>
                <div style="position:relative;height:64px;margin:4px auto;width:200px">
                    @if($ttd['stempel_uri'])
                        <img src="{{ $ttd['stempel_uri'] }}" alt="Stempel" style="position:absolute;left:16px;top:0;height:60px;max-width:85px;opacity:0.85">
                    @endif
                    @if($ttd['ttd_kepsek_uri'])
                        <img src="{{ $ttd['ttd_kepsek_uri'] }}" alt="TTD Kepsek" style="position:relative;height:54px;max-width:140px;margin-top:4px">
                    @endif
                </div>
                <div style="font-weight:bold;text-decoration:underline">{{ $ttd['nama_kepsek'] ?: ($kepsek?->nama_lengkap ?? '...........................') }}</div>
                @if($ttd['nip_kepsek'])
                    <div style="font-size:8.5px">NIP. {{ $ttd['nip_kepsek'] }}</div>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
