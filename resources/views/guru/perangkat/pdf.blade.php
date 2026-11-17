<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $perangkat->judul }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #111827; line-height: 1.45; margin: 18px; }
        .kop { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; text-align: center; }
        .kop h2 { margin: 0; font-size: 14px; text-transform: uppercase; letter-spacing: .3px; }
        .kop p { margin: 2px 0 0; font-size: 9.5px; color: #374151; }
        .judul { text-align: center; margin-bottom: 12px; }
        .judul h3 { margin: 0; font-size: 12.5px; text-transform: uppercase; }
        .judul span { font-size: 10px; color: #4B5563; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.meta td { padding: 3px 6px; vertical-align: top; }
        table.data { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        table.data th, table.data td { border: 1px solid #9CA3AF; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.data th { background: #F3F4F6; font-weight: bold; }
        .box { border: 1px solid #D1D5DB; padding: 8px 10px; margin-bottom: 10px; }
        .box-title { font-weight: bold; margin-bottom: 4px; color: #1F2937; }
        .ttd { width: 100%; margin-top: 26px; border-collapse: collapse; }
        .ttd td { width: 50%; text-align: center; vertical-align: top; padding: 4px; }
        .ttd-space { height: 52px; }
        .footer-note { margin-top: 14px; font-size: 8.5px; color: #6B7280; text-align: right; }
    </style>
</head>
<body>
    <div class="kop">
        <h2>{{ $kop['nama_sekolah'] }}</h2>
        <p>NPSN: {{ $kop['npsn'] }} • {{ $kop['alamat'] }}</p>
    </div>

    <div class="judul">
        <h3>{{ $perangkat->jenisLabel() }}</h3>
        <span>{{ $perangkat->judul }}</span>
    </div>

    <table class="meta">
        <tr>
            <td style="width:20%"><strong>Mata Pelajaran</strong></td>
            <td style="width:30%">: {{ $perangkat->mata_pelajaran }}</td>
            <td style="width:20%"><strong>Tahun Ajaran</strong></td>
            <td style="width:30%">: {{ $perangkat->tahun_ajaran ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Kelas / Fase</strong></td>
            <td>: {{ $perangkat->rombel?->nama_rombel ?? $perangkat->kelas }}{{ $perangkat->fase ? ' / Fase '.$perangkat->fase : '' }}</td>
            <td><strong>Semester</strong></td>
            <td>: {{ $perangkat->semester ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Guru Penyusun</strong></td>
            <td>: {{ $perangkat->guru?->nama_lengkap ?? '-' }}</td>
            <td><strong>Alokasi Waktu</strong></td>
            <td>: {{ $perangkat->alokasi_waktu ?: '-' }}</td>
        </tr>
    </table>

    @php $k = $perangkat->konten ?? []; @endphp

    @if($perangkat->ringkasan)
        <div class="box">
            <div class="box-title">Ringkasan Dokumen</div>
            <div>{{ $perangkat->ringkasan }}</div>
        </div>
    @endif

    @if($perangkat->jenis === 'cp')
        <div class="box"><div class="box-title">Elemen</div><div>{{ $k['elemen'] ?? '-' }}</div></div>
        <div class="box"><div class="box-title">Rumusan Capaian Pembelajaran (CP)</div><div>{!! nl2br(e($k['capaian_pembelajaran'] ?? '-')) !!}</div></div>
        <div class="box"><div class="box-title">Kompetensi Inti</div><div>{!! nl2br(e($k['kompetensi_inti'] ?? '-')) !!}</div></div>
        <div class="box"><div class="box-title">Konten / Materi Esensial</div><div>{!! nl2br(e($k['konten_materi'] ?? '-')) !!}</div></div>

    @elseif($perangkat->jenis === 'tp')
        <div class="box"><strong>Elemen:</strong> {{ $k['elemen'] ?? '-' }} | <strong>Rujukan CP:</strong> {{ $k['capaian_rujukan'] ?? '-' }}</div>
        <table class="data">
            <thead><tr><th style="width:14%">Kode TP</th><th style="width:48%">Rumusan Tujuan Pembelajaran</th><th style="width:26%">Materi Pokok</th><th style="width:12%">JP</th></tr></thead>
            <tbody>
                @forelse($k['daftar_tp'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['kode'] ?? '-' }}</td>
                        <td>{{ $row['deskripsi'] ?? '-' }}</td>
                        <td>{{ $row['materi'] ?? '-' }}</td>
                        <td>{{ $row['jp'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">-</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="box"><div class="box-title">Indikator Ketercapaian</div><div>{{ $k['indikator_ketercapaian'] ?? '-' }}</div></div>

    @elseif($perangkat->jenis === 'atp')
        <div class="box"><strong>Elemen:</strong> {{ $k['elemen'] ?? '-' }} | <strong>Prasyarat:</strong> {{ $k['prasyarat'] ?? '-' }}</div>
        <table class="data">
            <thead><tr><th>No</th><th>Kode</th><th>Tujuan Pembelajaran</th><th>Materi Pokok</th><th>JP</th><th>Sem</th><th>Dimensi Profil</th></tr></thead>
            <tbody>
                @forelse($k['alur_item'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['urutan'] ?? '-' }}</td>
                        <td>{{ $row['kode_tp'] ?? '-' }}</td>
                        <td>{{ $row['tujuan_pembelajaran'] ?? '-' }}</td>
                        <td>{{ $row['materi_pokok'] ?? '-' }}</td>
                        <td>{{ $row['alokasi_jp'] ?? '-' }}</td>
                        <td>{{ $row['semester'] ?? '-' }}</td>
                        <td>{{ $row['dimensi_profil'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">-</td></tr>
                @endforelse
            </tbody>
        </table>

    @elseif($perangkat->jenis === 'prota')
        <table class="data">
            <thead><tr><th>Semester</th><th>Unit</th><th>Materi / Topik Pokok</th><th>Alokasi JP</th><th>Keterangan</th></tr></thead>
            <tbody>
                @forelse($k['daftar_unit'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['semester'] ?? '-' }}</td>
                        <td>{{ $row['nomor_unit'] ?? '-' }}</td>
                        <td>{{ $row['materi_topik'] ?? '-' }}</td>
                        <td>{{ $row['alokasi_jp'] ?? '-' }}</td>
                        <td>{{ $row['keterangan'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">-</td></tr>
                @endforelse
            </tbody>
        </table>

    @elseif($perangkat->jenis === 'promes')
        <div class="box"><strong>Bulan Efektif:</strong> {{ $k['bulan_efektif'] ?? '-' }}</div>
        <table class="data">
            <thead><tr><th>Bulan</th><th>Minggu</th><th>Materi / Topik</th><th>JP</th><th>Keterangan</th></tr></thead>
            <tbody>
                @forelse($k['daftar_minggu'] ?? [] as $row)
                    <tr>
                        <td>{{ $row['bulan'] ?? '-' }}</td>
                        <td>{{ $row['minggu_ke'] ?? '-' }}</td>
                        <td>{{ $row['materi_topik'] ?? '-' }}</td>
                        <td>{{ $row['alokasi_jp'] ?? '-' }}</td>
                        <td>{{ $row['keterangan'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">-</td></tr>
                @endforelse
            </tbody>
        </table>

    @elseif($perangkat->jenis === 'modul_ajar')
        <div class="box"><div class="box-title">Tujuan Pembelajaran</div><div>{!! nl2br(e($k['tujuan_pembelajaran'] ?? '-')) !!}</div></div>
        <div class="box">
            <div class="box-title">Desain Pembelajaran Mendalam</div>
            <div><strong>Prinsip:</strong>
                {{ !empty($k['pm']['berkesadaran']) ? '[✓] Berkesadaran  ' : '' }}
                {{ !empty($k['pm']['bermakna']) ? '[✓] Bermakna  ' : '' }}
                {{ !empty($k['pm']['menggembirakan']) ? '[✓] Menggembirakan' : '' }}
            </div>
            <div><strong>Dimensi Profil Lulusan:</strong> {{ !empty($k['dimensi']) ? implode(', ', $k['dimensi']) : '-' }}</div>
        </div>
        <table class="data">
            <thead><tr><th>Tahap Pengalaman Belajar</th><th>Deskripsi Kegiatan</th></tr></thead>
            <tbody>
                <tr><td><strong>Memahami</strong></td><td>{!! nl2br(e($k['pengalaman']['memahami'] ?? '-')) !!}</td></tr>
                <tr><td><strong>Mengaplikasi</strong></td><td>{!! nl2br(e($k['pengalaman']['mengaplikasi'] ?? '-')) !!}</td></tr>
                <tr><td><strong>Merefleksi</strong></td><td>{!! nl2br(e($k['pengalaman']['merefleksi'] ?? '-')) !!}</td></tr>
            </tbody>
        </table>
        <table class="data">
            <thead><tr><th>Langkah Pembelajaran</th><th>Uraian</th></tr></thead>
            <tbody>
                <tr><td><strong>Pendahuluan</strong></td><td>{!! nl2br(e($k['pendahuluan'] ?? '-')) !!}</td></tr>
                <tr><td><strong>Kegiatan Inti</strong></td><td>{!! nl2br(e($k['kegiatan_inti'] ?? '-')) !!}</td></tr>
                <tr><td><strong>Penutup</strong></td><td>{!! nl2br(e($k['penutup'] ?? '-')) !!}</td></tr>
                <tr><td><strong>Asesmen</strong></td><td>{!! nl2br(e($k['asesmen'] ?? '-')) !!}</td></tr>
                <tr><td><strong>Media &amp; Sumber</strong></td><td>{!! nl2br(e($k['media_sumber'] ?? '-')) !!}</td></tr>
                <tr><td><strong>Refleksi Guru</strong></td><td>{!! nl2br(e($k['refleksi_guru'] ?? '-')) !!}</td></tr>
            </tbody>
        </table>

    @elseif($perangkat->jenis === 'kkm')
        <table class="data">
            <thead><tr><th>Kompleksitas</th><th>Daya Dukung</th><th>Intake</th><th>Nilai KKM / KKTP</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $k['kompleksitas'] ?? '-' }}</td>
                    <td>{{ $k['daya_dukung'] ?? '-' }}</td>
                    <td>{{ $k['intake'] ?? '-' }}</td>
                    <td><strong>{{ $k['nilai_kkm'] ?? '-' }}</strong></td>
                </tr>
            </tbody>
        </table>
        <table class="data">
            <thead><tr><th>Predikat</th><th>Rentang &amp; Deskripsi KKTP</th></tr></thead>
            <tbody>
                <tr><td>Perlu Bimbingan</td><td>{{ $k['interval_perlu_bimbingan'] ?? '-' }}</td></tr>
                <tr><td>Cukup</td><td>{{ $k['interval_cukup'] ?? '-' }}</td></tr>
                <tr><td>Baik (Tuntas)</td><td>{{ $k['interval_baik'] ?? '-' }}</td></tr>
                <tr><td>Sangat Baik</td><td>{{ $k['interval_sangat_baik'] ?? '-' }}</td></tr>
            </tbody>
        </table>
    @endif

    <table class="ttd">
        <tr>
            <td>Mengetahui,<br>Kepala Sekolah<div class="ttd-space"></div><strong>{{ $kop['kepala_sekolah'] }}</strong></td>
            <td>Guru Mata Pelajaran / Penyusun<div class="ttd-space"></div><strong>{{ $perangkat->guru?->nama_lengkap ?? '-' }}</strong></td>
        </tr>
    </table>

    <div class="footer-note">Dicetak melalui SIMANTAP pada {{ $dicetakPada }}</div>
</body>
</html>
