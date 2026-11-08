<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Presensi {{ $namaBulan }} {{ $tahun }} - {{ $sekolah->nama_sekolah ?? 'SIMANTAP_MONSA' }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <style>
        @page {
            size: landscape;
            margin: 8mm 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            background: #f1f5f9;
            margin: 0;
            padding: 0;
            font-size: 9.5pt;
            line-height: 1.25;
        }

        /* Toolbar layar (disembunyikan penuh saat cetak) */
        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #0f172a;
            color: #fff;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.25);
            font-family: Inter, system-ui, -apple-system, sans-serif;
        }

        .toolbar-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .toolbar-form select,
        .toolbar-form input[type="date"] {
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 6px 9px;
            font-size: 12px;
            color-scheme: dark;
        }

        .toolbar-field {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 2px 8px;
        }

        .toolbar-field label {
            font-size: 11.5px;
            font-weight: 600;
            color: #cbd5e1;
            white-space: nowrap;
        }

        .toolbar-field input[type="date"] {
            border: none;
            background: transparent;
            padding: 4px 2px;
            outline: none;
        }

        .toolbar-toggle {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            color: #e2e8f0;
            background: #1e293b;
            border: 1px solid #475569;
            border-radius: 6px;
            padding: 6px 11px;
            cursor: pointer;
            user-select: none;
            transition: background 0.15s, border-color 0.15s;
        }

        .toolbar-toggle:hover {
            border-color: #64748b;
            background: #26354d;
        }

        body.tampilkan-waktu .toolbar-toggle {
            background: #065f46;
            border-color: #10b981;
            color: #ecfdf5;
        }

        .toolbar-toggle input[type="checkbox"] {
            width: 14px;
            height: 14px;
            accent-color: #10b981;
            cursor: pointer;
            margin: 0;
        }

        .btn-toolbar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            padding: 7px 13px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-filter {
            background: #2563eb;
            color: #fff;
        }

        .btn-print {
            background: #059669;
            color: #fff;
        }

        .btn-back {
            background: #475569;
            color: #fff;
        }

        /* Lembar Daftar Presensi */
        .print-sheet {
            max-width: 1420px;
            margin: 16px auto;
            background: #fff;
            padding: 20px 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .kop-surat {
            border-bottom: 3px double #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-align: center;
        }

        .kop-logo {
            width: 58px;
            height: 58px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .kop-teks {
            flex: 1;
        }

        .kop-teks h4 {
            margin: 0;
            font-size: 11pt;
            font-weight: normal;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .kop-teks h3 {
            margin: 1px 0;
            font-size: 12pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .kop-teks h2 {
            margin: 1px 0;
            font-size: 13.5pt;
            font-weight: 900;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .kop-teks p {
            margin: 1px 0 0;
            font-size: 8.5pt;
            color: #222;
        }

        .judul-laporan {
            text-align: center;
            margin-bottom: 10px;
        }

        .judul-laporan h3 {
            margin: 0;
            font-size: 11.5pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .judul-laporan .subjudul {
            font-size: 9.5pt;
            margin-top: 2px;
        }

        .info-mode-waktu {
            display: none;
            color: #065f46;
        }

        body.tampilkan-waktu .info-mode-waktu {
            display: inline;
        }

        /* Tabel Matriks Tanggal 1 s/d Akhir Bulan */
        table.tabel-matriks {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.5pt;
        }

        table.tabel-matriks th,
        table.tabel-matriks td {
            border: 1px solid #000;
            padding: 3px 2px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        table.tabel-matriks th {
            background-color: #eaeaea;
            font-weight: bold;
            text-align: center;
            font-size: 7.5pt;
        }

        table.tabel-matriks .col-no {
            width: 24px;
            text-align: center;
        }

        table.tabel-matriks .col-nama {
            width: 150px;
            text-align: left;
            padding-left: 5px;
            padding-right: 4px;
        }

        body.tampilkan-waktu table.tabel-matriks .col-nama {
            width: 132px;
        }

        table.tabel-matriks .col-tgl {
            text-align: center;
            padding: 2px 1px;
        }

        table.tabel-matriks .col-total {
            width: 54px;
            text-align: center;
            font-weight: bold;
        }

        table.tabel-matriks .col-rekap-sm {
            width: 22px;
            text-align: center;
            font-weight: bold;
        }

        table.tabel-matriks .minggu {
            background-color: #e5e7eb;
        }

        .nama-utama {
            font-weight: bold;
            font-size: 8pt;
            line-height: 1.15;
        }

        .nip-kecil {
            font-size: 6.8pt;
            color: #333;
        }

        .jam-utama {
            font-weight: bold;
            font-size: 6.8pt;
            line-height: 1.15;
            display: block;
        }

        body.tampilkan-waktu .jam-dengan-waktu {
            border-top: 1px dotted #94a3b8;
            margin-top: 1px;
            padding-top: 1px;
        }

        .detail-waktu {
            display: none;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6pt;
            line-height: 1.15;
            letter-spacing: -0.2px;
        }

        body.tampilkan-waktu .detail-waktu {
            display: block;
        }

        .waktu-baris {
            display: block;
            white-space: nowrap;
        }

        .waktu-masuk {
            color: #065f46;
            font-weight: 700;
        }

        .waktu-pulang {
            color: #1e40af;
            font-weight: 700;
            border-top: 1px dotted #cbd5e1;
        }

        .detail-kosong .waktu-baris {
            color: #94a3b8;
            font-weight: normal;
        }

        .detail-kosong .waktu-baris + .waktu-baris {
            border-top: 1px dotted #e2e8f0;
        }

        .status-izin {
            font-weight: bold;
            color: #1d4ed8;
        }

        .status-sakit {
            font-weight: bold;
            color: #b45309;
        }

        .status-alpa {
            font-weight: bold;
            color: #b91c1c;
        }

        tr.baris-total td {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 8pt;
            padding: 5px 6px;
        }

        .catatan-bawah {
            margin-top: 6px;
            font-size: 8pt;
            color: #222;
        }

        .ket-waktu {
            display: none;
            margin-top: 2px;
            color: #111;
        }

        body.tampilkan-waktu .ket-waktu {
            display: block;
        }

        .ttd-blok {
            margin-top: 18px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
            font-size: 9.5pt;
        }

        .ttd-item {
            width: 250px;
            text-align: center;
        }

        .ttd-space {
            height: 52px;
        }

        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
            margin: 0;
        }

        .ttd-nip {
            margin: 2px 0 0;
            font-size: 8.5pt;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-toolbar {
                display: none !important;
            }

            .print-sheet {
                max-width: 100%;
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .info-mode-waktu {
                color: #000;
            }

            table.tabel-matriks th,
            table.tabel-matriks .minggu,
            tr.baris-total td {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            table.tabel-matriks tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="{{ !empty($tampilkanWaktu) ? 'tampilkan-waktu' : '' }}">
    @php
        $kotaCetak = trim((string) preg_replace('/^kota\s+/i', '', $sekolah->kota ?? 'Makassar')) ?: 'Makassar';
        $tanggalCetakFormatted = \Carbon\Carbon::parse($tanggalCetak)->locale('id')->translatedFormat('d F Y');
    @endphp

    {{-- Toolbar Pengaturan di Layar (Tidak ikut tercetak) --}}
    <div class="print-toolbar">
        <form action="{{ route('admin.presensi.laporan.cetak') }}" method="GET" class="toolbar-form" id="cetakFilterForm">
            <strong style="font-size:13px;margin-right:2px">Daftar Presensi:</strong>

            <select name="kategori" aria-label="Kategori">
                <option value="guru" @selected($kategori === 'guru')>Guru</option>
                <option value="tendik" @selected($kategori === 'tendik')>Tenaga Kependidikan</option>
                <option value="pegawai" @selected($kategori === 'pegawai')>Semua Pegawai</option>
                <option value="siswa" @selected($kategori === 'siswa')>Siswa</option>
            </select>

            <select name="bulan" aria-label="Bulan">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected((int) $bulan === $m)>
                        {{ \Carbon\Carbon::create(2026, $m, 1)->locale('id')->translatedFormat('F') }}
                    </option>
                @endfor
            </select>

            <select name="tahun" aria-label="Tahun">
                @for($y = (int) date('Y') - 3; $y <= (int) date('Y') + 1; $y++)
                    <option value="{{ $y }}" @selected((int) $tahun === $y)>{{ $y }}</option>
                @endfor
            </select>

            @if($kategori !== 'siswa')
                <select name="user_id" aria-label="Pegawai">
                    <option value="">Semua Pegawai</option>
                    @foreach($pegawaiFilterList as $p)
                        <option value="{{ $p->id }}" @selected((int) $selectedUserId === (int) $p->id)>
                            {{ $p->nama_lengkap }}
                        </option>
                    @endforeach
                </select>
            @else
                <select name="kelas" aria-label="Kelas">
                    <option value="">Semua Kelas</option>
                    @foreach($kelasList as $k)
                        <option value="{{ $k }}" @selected($kelas === $k)>Kelas {{ $k }}</option>
                    @endforeach
                </select>
            @endif

            <div class="toolbar-field">
                <label for="inputTanggalCetak">Tanggal Cetak:</label>
                <input type="date" id="inputTanggalCetak" name="tanggal_cetak" value="{{ $tanggalCetak }}">
            </div>

            <label class="toolbar-toggle" for="toggleWaktu">
                <input type="checkbox" id="toggleWaktu" name="tampilkan_waktu" value="1" @checked(!empty($tampilkanWaktu))>
                <span>{{ $kategori === 'siswa' ? 'Tampilkan Jam Masuk' : 'Tampilkan Jam Masuk & Pulang' }}</span>
            </label>

            <button type="submit" class="btn-toolbar btn-filter">Tampilkan</button>
        </form>

        <div style="display:flex;gap:8px;align-items:center">
            <button type="button" id="btnPrintDoc" class="btn-toolbar btn-print">
                Cetak Daftar Presensi
            </button>
            <a id="btnBackLaporan"
               href="{{ route('admin.presensi.laporan', array_filter(['kategori' => $kategori, 'periode' => 'bulanan', 'bulan' => $bulan, 'tahun' => $tahun, 'user_id' => $selectedUserId, 'kelas' => $kelas, 'tanggal_cetak' => $tanggalCetak, 'tampilkan_waktu' => !empty($tampilkanWaktu) ? 1 : null])) }}"
               class="btn-toolbar btn-back">
                Kembali
            </a>
        </div>
    </div>

    {{-- Lembar Cetak (Hanya Daftar Presensi) --}}
    <div class="print-sheet">
        <div class="kop-surat">
            <img src="{{ asset('images/logo-128.png') }}" alt="Logo" class="kop-logo">
            <div class="kop-teks">
                <h4>PEMERINTAH KOTA MAKASSAR</h4>
                <h3>DINAS PENDIDIKAN</h3>
                <h2>{{ $sekolah->nama_sekolah ?? 'UPTD SD NEGERI UNGGULAN MONGISIDI 1' }}</h2>
                <p>{{ $sekolah->alamat ?? 'Jl. Mongisidi No. 1' }}, Kec. {{ $sekolah->kecamatan ?? 'Makassar' }}, Kota {{ $sekolah->kota ?? 'Makassar' }} | NPSN: {{ $sekolah->npsn ?? '-' }}</p>
            </div>
        </div>

        <div class="judul-laporan">
            <h3>DAFTAR PRESENSI {{ strtoupper($kategori === 'siswa' ? 'SISWA' : ($kategori === 'tendik' ? 'TENAGA KEPENDIDIKAN' : ($kategori === 'guru' ? 'GURU' : 'PEGAWAI'))) }}</h3>
            <div class="subjudul">
                Bulan: <strong>{{ $namaBulan }} {{ $tahun }}</strong> (Tanggal 1 s.d. {{ $daysInMonth }})
                @if($kategori === 'siswa' && $kelas)
                    | Kelas: <strong>{{ $kelas }}</strong>
                @endif
                <span class="info-mode-waktu">
                    | Rincian: <strong>{{ $kategori === 'siswa' ? 'Jam Masuk & Status' : 'Jam Masuk, Jam Pulang & Jumlah Jam' }}</strong>
                </span>
            </div>
        </div>

        <table class="tabel-matriks">
            <thead>
                <tr>
                    <th rowspan="2" class="col-no">No</th>
                    <th rowspan="2" class="col-nama" style="text-align:center">
                        {{ $kategori === 'siswa' ? 'Nama Siswa' : 'Nama Pegawai' }}
                    </th>
                    <th colspan="{{ $daysInMonth }}">Tanggal ({{ $namaBulan }} {{ $tahun }})</th>
                    @if($kategori !== 'siswa')
                        <th rowspan="2" class="col-total">Jumlah Jam</th>
                    @else
                        <th colspan="4">Rekap</th>
                    @endif
                </tr>
                <tr>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <th class="col-tgl {{ ($daysMeta[$d]['is_sunday'] ?? false) ? 'minggu' : '' }}">{{ $d }}</th>
                    @endfor
                    @if($kategori === 'siswa')
                        <th class="col-rekap-sm">H</th>
                        <th class="col-rekap-sm">S</th>
                        <th class="col-rekap-sm">I</th>
                        <th class="col-rekap-sm">A</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($cetakRows as $index => $row)
                    <tr>
                        <td class="col-no">{{ $index + 1 }}</td>
                        <td class="col-nama">
                            <div class="nama-utama">{{ $row['nama_pegawai'] }}</div>
                            <div class="nip-kecil">
                                {{ $kategori === 'siswa' ? 'NISN: ' . $row['nip'] . ' (' . $row['peran'] . ')' : 'NIP: ' . $row['nip'] }}
                            </div>
                        </td>

                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $cell = $row['daily'][$d] ?? null;
                                $isMinggu = $daysMeta[$d]['is_sunday'] ?? false;
                                $lbl = $cell['label'] ?? '';
                                $hasWaktu = !empty($cell['jam_masuk']) || !empty($cell['jam_pulang']);
                                $isIzinSakitAlpa = in_array($lbl, ['I', 'S', 'A'], true);
                            @endphp
                            <td class="col-tgl {{ $isMinggu ? 'minggu' : '' }}"
                                @if($kategori !== 'siswa' && ($cell['has_record'] ?? false))
                                    title="Tanggal {{ $d }} {{ $namaBulan }} {{ $tahun }} | Masuk: {{ $cell['jam_masuk'] ?? '-' }} | Pulang: {{ $cell['jam_pulang'] ?? '-' }} | Durasi: {{ $cell['durasi_lengkap'] ?? '-' }}"
                                @endif>
                                @if($kategori !== 'siswa')
                                    @if($hasWaktu)
                                        <div class="detail-waktu">
                                            <span class="waktu-baris waktu-masuk">{{ $cell['jam_masuk'] ?? '--:--' }}</span>
                                            <span class="waktu-baris waktu-pulang">{{ $cell['jam_pulang'] ?? '--:--' }}</span>
                                        </div>
                                    @elseif(!$isMinggu && !$isIzinSakitAlpa)
                                        <div class="detail-waktu detail-kosong">
                                            <span class="waktu-baris">-</span>
                                            <span class="waktu-baris">-</span>
                                        </div>
                                    @endif

                                    @if($lbl !== '')
                                        <span class="jam-utama {{ $hasWaktu ? 'jam-dengan-waktu' : '' }} {{ $lbl === 'I' ? 'status-izin' : ($lbl === 'S' ? 'status-sakit' : ($lbl === 'A' ? 'status-alpa' : '')) }}">{{ $lbl }}</span>
                                    @endif
                                @else
                                    @if(!empty($cell['jam_masuk']))
                                        <div class="detail-waktu">
                                            <span class="waktu-baris waktu-masuk">{{ $cell['jam_masuk'] }}</span>
                                        </div>
                                    @elseif(!$isMinggu && !$isIzinSakitAlpa)
                                        <div class="detail-waktu detail-kosong">
                                            <span class="waktu-baris">-</span>
                                        </div>
                                    @endif

                                    @if($lbl !== '')
                                        <span class="jam-utama {{ !empty($cell['jam_masuk']) ? 'jam-dengan-waktu' : '' }} {{ $lbl === 'I' ? 'status-izin' : ($lbl === 'S' ? 'status-sakit' : ($lbl === 'A' ? 'status-alpa' : '')) }}">{{ $lbl }}</span>
                                    @endif
                                @endif
                            </td>
                        @endfor

                        @if($kategori !== 'siswa')
                            <td class="col-total">{{ $row['total_jam_singkat'] }}</td>
                        @else
                            <td class="col-rekap-sm">{{ $row['hadir'] }}</td>
                            <td class="col-rekap-sm">{{ $row['sakit'] }}</td>
                            <td class="col-rekap-sm">{{ $row['izin'] }}</td>
                            <td class="col-rekap-sm">{{ $row['alpa'] }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 2 + $daysInMonth + ($kategori !== 'siswa' ? 1 : 4) }}" style="text-align:center;padding:18px">
                            Belum ada data {{ $kategori === 'siswa' ? 'siswa' : 'pegawai' }} aktif untuk ditampilkan.
                        </td>
                    </tr>
                @endforelse

                @if($kategori !== 'siswa' && count($cetakRows) > 0)
                    <tr class="baris-total">
                        <td colspan="{{ 2 + $daysInMonth }}" style="text-align:right;padding-right:10px">
                            TOTAL AKUMULASI JAM KERJA ({{ strtoupper($namaBulan) }} {{ $tahun }}) :
                        </td>
                        <td class="col-total">{{ $totalDurasiSingkat }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="catatan-bawah">
            @if($kategori !== 'siswa')
                <div>
                    Keterangan: Angka pada setiap kolom tanggal menunjukkan <strong>jumlah jam kerja harian</strong> yang dihitung dari selisih waktu masuk dan waktu pulang (contoh: <strong>8j 30m</strong> = 8 jam 30 menit). Kode: <strong>I</strong> = Izin, <strong>S</strong> = Sakit, <strong>A</strong> = Alpa, kolom abu-abu = Hari Minggu.
                </div>
                <div class="ket-waktu">
                    Format Rincian Waktu per Tanggal: <strong>Baris 1 (Hijau)</strong> = Jam Masuk | <strong>Baris 2 (Biru)</strong> = Jam Pulang | <strong>Baris 3 (Tebal)</strong> = Jumlah Jam Kerja Harian.
                </div>
            @else
                <div>
                    Keterangan: <strong>H</strong> = Hadir, <strong>S</strong> = Sakit, <strong>I</strong> = Izin, <strong>A</strong> = Alpa, kolom abu-abu = Hari Minggu.
                </div>
                <div class="ket-waktu">
                    Format Rincian Waktu per Tanggal: <strong>Baris Atas</strong> = Jam Masuk | <strong>Baris Bawah</strong> = Kode Kehadiran.
                </div>
            @endif
        </div>

        <div class="ttd-blok">
            <div class="ttd-item">
                <p style="margin:0">Petugas Presensi,</p>
                <div class="ttd-space"></div>
                <p class="ttd-nama">{{ auth()->user()->nama_lengkap ?? 'Administrator' }}</p>
                <p class="ttd-nip">NIP. {{ auth()->user()->nip ?? '-' }}</p>
            </div>
            <div class="ttd-item">
                <p style="margin:0">{{ $kotaCetak }}, <span id="teksTanggalCetak">{{ $tanggalCetakFormatted }}</span></p>
                <p style="margin:2px 0 0">Mengetahui,<br>Kepala Sekolah</p>
                <div class="ttd-space"></div>
                <p class="ttd-nama">{{ $sekolah->kepala_sekolah ?? 'Kepala Sekolah' }}</p>
                <p class="ttd-nip">NIP. {{ $sekolah->nip_kepala_sekolah ?? '-' }}</p>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var BULAN_ID = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];

            var toggleWaktu = document.getElementById('toggleWaktu');
            var inputTanggalCetak = document.getElementById('inputTanggalCetak');
            var teksTanggalCetak = document.getElementById('teksTanggalCetak');
            var btnPrintDoc = document.getElementById('btnPrintDoc');
            var btnBackLaporan = document.getElementById('btnBackLaporan');

            function formatTanggalIndo(ymd) {
                if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
                    return null;
                }
                var parts = ymd.split('-');
                var bulanIdx = parseInt(parts[1], 10) - 1;
                if (bulanIdx < 0 || bulanIdx > 11) {
                    return null;
                }
                return parts[2] + ' ' + BULAN_ID[bulanIdx] + ' ' + parts[0];
            }

            function syncUrlState() {
                try {
                    var currentUrl = new URL(window.location.href);
                    if (toggleWaktu) {
                        if (toggleWaktu.checked) {
                            currentUrl.searchParams.set('tampilkan_waktu', '1');
                        } else {
                            currentUrl.searchParams.delete('tampilkan_waktu');
                        }
                    }
                    if (inputTanggalCetak && inputTanggalCetak.value) {
                        currentUrl.searchParams.set('tanggal_cetak', inputTanggalCetak.value);
                    }
                    window.history.replaceState({}, '', currentUrl.toString());

                    if (btnBackLaporan && btnBackLaporan.href) {
                        var backUrl = new URL(btnBackLaporan.href, window.location.origin);
                        if (inputTanggalCetak && inputTanggalCetak.value) {
                            backUrl.searchParams.set('tanggal_cetak', inputTanggalCetak.value);
                        }
                        if (toggleWaktu && toggleWaktu.checked) {
                            backUrl.searchParams.set('tampilkan_waktu', '1');
                        } else {
                            backUrl.searchParams.delete('tampilkan_waktu');
                        }
                        btnBackLaporan.href = backUrl.toString();
                    }
                } catch (e) {
                    // Abaikan bila URL API tidak didukung
                }
            }

            if (toggleWaktu) {
                document.body.classList.toggle('tampilkan-waktu', toggleWaktu.checked);
                toggleWaktu.addEventListener('change', function () {
                    document.body.classList.toggle('tampilkan-waktu', toggleWaktu.checked);
                    syncUrlState();
                });
            }

            if (inputTanggalCetak && teksTanggalCetak) {
                var updateTanggalCetak = function () {
                    var formatted = formatTanggalIndo(inputTanggalCetak.value);
                    if (formatted) {
                        teksTanggalCetak.textContent = formatted;
                        syncUrlState();
                    }
                };
                inputTanggalCetak.addEventListener('input', updateTanggalCetak);
                inputTanggalCetak.addEventListener('change', updateTanggalCetak);
            }

            if (btnPrintDoc) {
                btnPrintDoc.addEventListener('click', function () {
                    window.print();
                });
            }
        })();
    </script>
</body>
</html>
