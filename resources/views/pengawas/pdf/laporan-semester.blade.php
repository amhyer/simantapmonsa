<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kepengawasan Semester</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111827; line-height: 1.4; }
        .header { text-align: center; border-bottom: 2px solid #1F3864; padding-bottom: 8px; margin-bottom: 12px; }
        .header h2 { margin: 0; font-size: 15px; color: #1F3864; text-transform: uppercase; }
        .header p { margin: 3px 0 0; font-size: 11px; color: #4B5563; }
        h3 { font-size: 12px; color: #1F3864; margin: 12px 0 6px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #CBD5E1; padding: 5px 7px; text-align: left; }
        th { background: #F1F5F9; font-weight: bold; }
    </style>
</head>
<body>
    @php
        $ttd = app(\App\Services\PembelajaranMendalam\TandaTanganPdfService::class)->resolveForPdf($sekolah);
        $namaPengawas = $ttd['nama_pengawas'] ?: $pengawas->nama_lengkap;
        $nipPengawas = $ttd['nip_pengawas'] ?: ($pengawas->nip ?? '');
    @endphp

    <div class="header">
        <h2>LAPORAN HASIL KEPENGAWASAN &amp; PEMANTAUAN PEMBELAJARAN MENDALAM</h2>
        <p>{{ $sekolah->nama_sekolah ?? 'Satuan Pendidikan Binaan' }} · Tahun Ajaran {{ $tahunAjaran }} · Semester {{ $semester }}</p>
    </div>

    <h3>I. Ringkasan Eksekutif Radar Mutu Satuan Pendidikan</h3>
    <table>
        <tr>
            <th>Indeks Kesehatan Akademik</th>
            <td><strong>{{ $kpi['indeks_kesehatan'] }}%</strong></td>
            <th>Rata-rata Kelengkapan PM (9 Dokumen)</th>
            <td><strong>{{ $kpi['rata_kelengkapan_pm'] }}%</strong> ({{ $kpi['total_guru'] }} Guru Aktif)</td>
            <th>SLA Supervisi Kepala Sekolah</th>
            <td><strong>{{ $kpi['sla_supervisi_kepsek'] }}%</strong> ({{ $kpi['disetujui_kepsek'] }} Disetujui)</td>
        </tr>
        <tr>
            <th>Peserta Didik &amp; Rombel</th>
            <td>{{ $kpi['total_siswa'] }} Siswa / {{ $kpi['total_rombel'] }} Rombel</td>
            <th>Realisasi Jurnal Mengajar</th>
            <td>{{ $kpi['total_jurnal'] }} Pertemuan Tercatat</td>
            <th>Frekuensi Visitasi Pengawas</th>
            <td>{{ $kpi['total_visitasi'] }} Visitasi (Rata-rata Skor {{ $kpi['rata_skor_visitasi'] }}/4.00)</td>
        </tr>
    </table>

    <h3>II. Rekapitulasi Supervisi Berjenjang Perangkat Pembelajaran Mendalam</h3>
    <table>
        <thead>
            <tr>
                <th style="width:28px">No</th>
                <th>Nama Guru</th>
                <th style="text-align:center">Skor PM</th>
                <th>Status Supervisi Kepsek</th>
                <th>Status Verifikasi Pengawas</th>
                <th>Catatan Pembinaan &amp; RTL Pengawas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($guruRows as $i => $row)
                @php $sup = $row['supervisi']; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['guru']->nama_lengkap }}</td>
                    <td style="text-align:center;font-weight:bold">{{ $row['persen'] }}% ({{ $row['terisi'] }}/9)</td>
                    <td>{{ $row['status_label'] }}</td>
                    <td>{{ $sup ? $sup->statusPengawasLabel() : 'Menunggu Verifikasi' }}</td>
                    <td>
                        {{ $sup?->catatan_pengawas ?: '-' }}
                        @if($sup?->rekomendasi_rtl)
                            <br><em>RTL: {{ $sup->rekomendasi_rtl }}</em>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center">Belum ada data guru.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>III. Riwayat Visitasi &amp; Observasi Kelas Pembelajaran Mendalam</h3>
    <table>
        <thead>
            <tr>
                <th style="width:28px">No</th>
                <th style="width:80px">Tanggal</th>
                <th>Jenis Pendampingan</th>
                <th>Guru / Sasaran</th>
                <th style="text-align:center">Skor Rubrik</th>
                <th>Temuan &amp; Rekomendasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($visitasiList as $idx => $v)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $v->tanggal_visitasi->format('d/m/Y') }}</td>
                    <td>{{ $v->jenisLabel() }}</td>
                    <td>{{ $v->guru?->nama_lengkap ?? 'Satuan Pendidikan' }}</td>
                    <td style="text-align:center;font-weight:bold">{{ $v->rataRataSkor() }} / 4.00</td>
                    <td><strong>Temuan:</strong> {{ $v->temuan }}<br><strong>Rekomendasi:</strong> {{ $v->rekomendasi }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center">Belum ada catatan visitasi pada semester ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table style="border:none;margin-top:16px">
        <tr>
            <td style="border:none;width:50%;text-align:center">
                <div>Mengetahui,</div>
                <div>Kepala {{ $sekolah->nama_sekolah ?? 'Sekolah' }}</div>
                <div style="height:55px;margin:4px 0">
                    @if(!empty($ttd['ttd_kepsek_uri']))
                        <img src="{{ $ttd['ttd_kepsek_uri'] }}" style="max-height:50px">
                    @endif
                </div>
                <div style="font-weight:bold;text-decoration:underline">{{ $ttd['nama_kepsek'] ?: '....................................' }}</div>
                <div>NIP. {{ $ttd['nip_kepsek'] ?: '....................................' }}</div>
            </td>
            <td style="border:none;width:50%;text-align:center">
                <div>{{ $ttd['kota'] ?: 'Makassar' }}, {{ now()->format('d/m/Y') }}</div>
                <div>Pengawas Sekolah Pembina,</div>
                <div style="height:55px;margin:4px 0">
                    @if(!empty($ttd['ttd_pengawas_uri']))
                        <img src="{{ $ttd['ttd_pengawas_uri'] }}" style="max-height:50px">
                    @endif
                </div>
                <div style="font-weight:bold;text-decoration:underline">{{ $namaPengawas }}</div>
                <div>NIP. {{ $nipPengawas ?: '....................................' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
