@php
    $cfg = $raporConfig ?? [];
    $opts = $printOpts ?? [
        'posisi_ttd_ks' => 'sejajar',
        'tampil_ttd' => true,
        'tampil_wali' => true,
    ];
    $namaSiswa = match ($cfg['kapitalisasi_nama'] ?? 'asli') {
        'upper' => mb_strtoupper((string) $siswa->nama_peserta_didik),
        'title' => \Illuminate\Support\Str::title(mb_strtolower((string) $siswa->nama_peserta_didik)),
        default => $siswa->nama_peserta_didik,
    };
    $semesterAngka = strtolower((string) $semester) === 'genap' ? '2 (Genap)' : '1 (Ganjil)';

    $bulanId = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $tglObj = ($tanggalRapor && $tanggalRapor->tanggal) ? $tanggalRapor->tanggal : now();
    $tglIndo = $tglObj->day.' '.($bulanId[$tglObj->month] ?? $tglObj->format('F')).' '.$tglObj->year;

    $formatNilai = function ($nilaiAkhir) use ($cfg): string {
        if ($nilaiAkhir === null) {
            return '-';
        }
        $val = (float) $nilaiAkhir;

        return match ($cfg['format_desimal'] ?? '0') {
            '2' => number_format($val, 2, ',', '.'),
            'auto' => rtrim(rtrim(number_format($val, 2, ',', '.'), '0'), ','),
            default => number_format($val, 0, ',', '.'),
        };
    };

    $kelompokA = $nilaiKelompok['A'] ?? collect();
    $kelompokB = $nilaiKelompok['B'] ?? collect();
    $adaKelompokB = $kelompokB->isNotEmpty();

    $narasiKokurikuler = ($kokurikuler ?? collect())
        ->pluck('catatan')
        ->filter(fn ($v) => trim((string) $v) !== '')
        ->unique()
        ->values();

    $ekskulRows = $ekstrakurikuler ?? collect();
    $catatanNaik = trim((string) ($naikKelas->catatan ?? ''));
    if ($catatanNaik === 'Dipulihkan dari backup e-Rapor (.xsp)') {
        $catatanNaik = '';
    }
@endphp

<div class="rapor-sheet">
    @include('admin.cetak.partials.rapor-style-kop', ['jenis' => 'semester'])

    <table class="identitas-grid">
        <tr>
            <td style="width:56%">
                <table class="id-sub">
                    <tr><td class="lbl">Nama Murid</td><td class="sep">:</td><td><strong>{{ $namaSiswa }}</strong></td></tr>
                    <tr><td class="lbl">NIS / NISN</td><td class="sep">:</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
                    <tr><td class="lbl">Sekolah</td><td class="sep">:</td><td>{{ $sekolah->nama_sekolah ?? '-' }}</td></tr>
                    <tr><td class="lbl">Alamat</td><td class="sep">:</td><td>{{ $sekolah->alamat ?? '-' }}</td></tr>
                </table>
            </td>
            <td style="width:44%">
                <table class="id-sub">
                    <tr><td class="lbl-r">Kelas</td><td class="sep">:</td><td>{{ $siswa->kelas ?? '-' }}</td></tr>
                    <tr><td class="lbl-r">Fase</td><td class="sep">:</td><td>{{ $fase ?? 'C' }}</td></tr>
                    <tr><td class="lbl-r">Semester</td><td class="sep">:</td><td>{{ $semesterAngka }}</td></tr>
                    <tr><td class="lbl-r">Tahun Ajaran</td><td class="sep">:</td><td>{{ $tahunAjaran }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="judul">
        <h3>LAPORAN HASIL BELAJAR</h3>
    </div>

    <table class="nilai">
        <thead>
            <tr>
                <th style="width:34px">No</th>
                <th style="width:28%">Mata Pelajaran</th>
                <th style="width:66px">Nilai Akhir</th>
                <th>Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
            @if($nilai->isEmpty())
                <tr>
                    <td colspan="4" style="text-align:center">Belum ada nilai rapor untuk periode ini.</td>
                </tr>
            @else
                @if($adaKelompokB)
                    <tr class="klp-row"><td colspan="4"><strong>Kelompok A</strong></td></tr>
                @endif
                @foreach($kelompokA as $i => $n)
                    <tr>
                        <td style="text-align:center">{{ $i + 1 }}</td>
                        <td>{{ $n->mata_pelajaran }}</td>
                        <td style="text-align:center">{{ $formatNilai($n->nilai_akhir) }}</td>
                        <td class="desc-cell">{!! nl2br(e($n->deskripsi_capaian ?? '-')) !!}</td>
                    </tr>
                @endforeach

                @if($adaKelompokB)
                    <tr class="klp-row"><td colspan="4"><strong>Kelompok B</strong></td></tr>
                    @foreach($kelompokB as $i => $n)
                        <tr>
                            <td style="text-align:center">{{ $i + 1 }}</td>
                            <td>{{ $n->mata_pelajaran }}</td>
                            <td style="text-align:center">{{ $formatNilai($n->nilai_akhir) }}</td>
                            <td class="desc-cell">{!! nl2br(e($n->deskripsi_capaian ?? '-')) !!}</td>
                        </tr>
                    @endforeach
                @endif
            @endif
        </tbody>
    </table>

    @if($narasiKokurikuler->isNotEmpty())
        <table class="box-table" style="margin-top:12px">
            <thead>
                <tr><th>Kokurikuler</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="desc-cell">
                        @foreach($narasiKokurikuler as $idx => $narasi)
                            @if($idx > 0)<hr style="border:none;border-top:1px dashed #999;margin:6px 0">@endif
                            {!! nl2br(e($narasi)) !!}
                        @endforeach
                    </td>
                </tr>
            </tbody>
        </table>
    @endif

    <table class="box-table" style="margin-top:12px">
        <thead>
            <tr>
                <th style="width:34px">No</th>
                <th style="width:30%">Ekstrakurikuler</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ekskulRows as $idx => $eks)
                <tr>
                    <td style="text-align:center">{{ $idx + 1 }}</td>
                    <td>{{ $eks->nama }}</td>
                    <td>{{ $eks->keterangan ?: ($eks->nilai ? 'Predikat: '.$eks->nilai : '-') }}</td>
                </tr>
            @empty
                <tr><td style="text-align:center">1</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td style="text-align:center">2</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="two-col-block" style="margin-top:12px;{{ !empty($cfg['page_break_catatan']) ? 'page-break-before: always;' : '' }}">
        <tr>
            <td style="width:36%;vertical-align:top;padding-right:10px">
                <table class="box-table">
                    <thead>
                        <tr><th colspan="3">Ketidakhadiran</th></tr>
                    </thead>
                    <tbody>
                        <tr><td style="width:58%">Sakit</td><td style="width:10px;text-align:center">:</td><td>{{ $hadir['S'] }} hari</td></tr>
                        <tr><td>Izin</td><td style="text-align:center">:</td><td>{{ $hadir['I'] }} hari</td></tr>
                        <tr><td>Tanpa Keterangan</td><td style="text-align:center">:</td><td>{{ $hadir['A'] }} hari</td></tr>
                    </tbody>
                </table>
            </td>
            <td style="width:64%;vertical-align:top">
                <table class="box-table" style="height:100%">
                    <thead>
                        <tr><th>Catatan Wali Kelas</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="min-height:66px;vertical-align:top">{{ $catatan->catatan ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @if(!empty($naikKelas))
        <div class="keputusan-box">
            @if(!empty($isKelasAkhir))
                Keterangan Kelulusan : <strong>{{ $naikKelas->naik_kelas ? 'Lulus (Naik ke kelas berikutnya)' : 'Belum Lulus' }}</strong>{{ $catatanNaik !== '' ? ' — '.$catatanNaik : '' }}
            @else
                Keputusan Kenaikan Kelas : <strong>{{ $naikKelas->naik_kelas ? 'Naik ke kelas berikutnya' : 'Tinggal di kelas saat ini' }}</strong>{{ $catatanNaik !== '' ? ' — '.$catatanNaik : '' }}
            @endif
        </div>
    @endif

    <table class="box-table" style="margin-top:12px">
        <thead>
            <tr><th>Tanggapan Orang Tua/Wali Murid</th></tr>
        </thead>
        <tbody>
            <tr><td style="height:46px">&nbsp;</td></tr>
        </tbody>
    </table>

    @if(($opts['posisi_ttd_ks'] ?? 'sejajar') === 'bawah')
        <table class="ttd-grid">
            <tr>
                <td style="width:50%">
                    <p>Mengetahui,<br>Orang Tua Murid</p>
                    <div class="ttd-space"></div>
                    <p><strong>......................................</strong></p>
                </td>
                <td style="width:50%">
                    <p>{{ $tanggalRapor->tempat ?? 'Makassar' }}, {{ $tglIndo }}<br>Wali Kelas</p>
                    <div class="ttd-space">
                        @if(!empty($opts['tampil_ttd']) && !empty($ttdWaliUri))
                            <img src="{{ $ttdWaliUri }}" alt="TTD Wali Kelas" class="ttd-img">
                        @endif
                    </div>
                    <p><strong><u>{{ !empty($opts['tampil_wali']) ? ($waliNama ?? '........................') : '........................' }}</u></strong></p>
                    @if(!empty($opts['tampil_wali']) && !empty($waliNip))
                        <p class="nip">NIP. {{ $waliNip }}</p>
                    @endif
                </td>
            </tr>
        </table>
        <table class="ttd-grid" style="margin-top:10px">
            <tr>
                <td style="width:100%">
                    <p>Mengetahui,<br>Kepala Sekolah</p>
                    <div class="ttd-space">
                        @if(!empty($opts['tampil_ttd']) && !empty($cfg['ttd_kepsek_data_uri']))
                            <img src="{{ $cfg['ttd_kepsek_data_uri'] }}" alt="TTD Kepala Sekolah" class="ttd-img">
                        @endif
                    </div>
                    <p><strong><u>{{ $sekolah->kepala_sekolah ?? '........................' }}</u></strong></p>
                    @if(!empty($kepsekNip))
                        <p class="nip">NIP. {{ $kepsekNip }}</p>
                    @endif
                </td>
            </tr>
        </table>
    @else
        <table class="ttd-grid">
            <tr>
                <td style="width:33%">
                    <p>&nbsp;<br>Orang Tua Murid</p>
                    <div class="ttd-space"></div>
                    <p><strong>......................................</strong></p>
                </td>
                <td style="width:34%">
                    <p>Mengetahui,<br>Kepala Sekolah</p>
                    <div class="ttd-space">
                        @if(!empty($opts['tampil_ttd']) && !empty($cfg['ttd_kepsek_data_uri']))
                            <img src="{{ $cfg['ttd_kepsek_data_uri'] }}" alt="TTD Kepala Sekolah" class="ttd-img">
                        @endif
                    </div>
                    <p><strong><u>{{ $sekolah->kepala_sekolah ?? '........................' }}</u></strong></p>
                    @if(!empty($kepsekNip))
                        <p class="nip">NIP. {{ $kepsekNip }}</p>
                    @endif
                </td>
                <td style="width:33%">
                    <p>{{ $tanggalRapor->tempat ?? 'Makassar' }}, {{ $tglIndo }}<br>Wali Kelas</p>
                    <div class="ttd-space">
                        @if(!empty($opts['tampil_ttd']) && !empty($ttdWaliUri))
                            <img src="{{ $ttdWaliUri }}" alt="TTD Wali Kelas" class="ttd-img">
                        @endif
                    </div>
                    <p><strong><u>{{ !empty($opts['tampil_wali']) ? ($waliNama ?? '........................') : '........................' }}</u></strong></p>
                    @if(!empty($opts['tampil_wali']) && !empty($waliNip))
                        <p class="nip">NIP. {{ $waliNip }}</p>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <div class="rapor-footer">
        <span>{{ $siswa->kelas ?? '-' }} | {{ mb_strtoupper((string) $siswa->nama_peserta_didik) }} | {{ $siswa->nis ?? '-' }}</span>
        <span>Semester {{ $semester }} {{ $tahunAjaran }}</span>
    </div>
</div>
