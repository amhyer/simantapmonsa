<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Siswa - SIMANTAP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.4; color: #000; }
        .container { padding: 15px; }
        .kop { text-align: center; border-bottom: 3px double #1F3864; padding-bottom: 10px; margin-bottom: 15px; }
        .kop h1 { font-size: 15px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .kop h2 { font-size: 12px; font-weight: bold; margin-bottom: 3px; }
        .kop p { font-size: 9px; color: #333; }
        .filter-info { font-size: 9px; color: #666; margin-bottom: 12px; padding: 6px 10px; background: #f8f9fa; border-radius: 4px; }
        .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #1F3864; border-left: 3px solid #B8860B; padding-left: 8px; margin: 12px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; margin-bottom: 12px; }
        th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; }
        th { background: #1F3864; color: #fff; font-weight: bold; text-align: center; font-size: 8.5px; }
        td.ctr { text-align: center; }
        td.right { text-align: right; }
        tr:nth-child(even) td { background: #F8FAFC; }
        .ttd { margin-top: 20px; display: flex; justify-content: space-between; text-align: center; font-size: 9px; }
        .ttd .spacer { height: 45px; }
        .ttd-col { width: 30%; }
        .footer { margin-top: 15px; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="kop">
            <h1>{{ strtoupper($pengaturan->pengaturan['sekolah'] ?? 'SEKOLAH') }}</h1>
            <h2>LAPORAN REKAP NILAI SISWA</h2>
            <p>{{ $pengaturan->pengaturan['alamat'] ?? '' }}</p>
            <p>NPSN: {{ $pengaturan->pengaturan['npsn'] ?? '-' }} | Guru: {{ $guru->nama_lengkap }}</p>
        </div>

        @if($kelasFilter || $semesterId || $mapelFilter)
            <div class="filter-info">
                <b>Filter:</b>
                @if($kelasFilter) Kelas: {{ $kelasFilter }} @endif
                @if($semesterId) | Semester: {{ $semesterId }} @endif
                @if($mapelFilter) | Mapel: {{ $mapelFilter }} @endif
            </div>
        @endif

        <div class="section-title">A. REKAP NILAI AKHIR</div>
        <table>
            <thead>
                <tr>
                    <th style="width:20px">No</th>
                    <th>Nama Siswa</th>
                    <th style="width:50px">NIS</th>
                    <th style="width:45px">Kelas</th>
                    <th style="width:55px">Nilai Akhir</th>
                    <th style="width:40px">Predikat</th>
                    <th style="width:55px">Kehadiran</th>
                    <th style="width:55px">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dataLaporan as $i => $data)
                    <tr>
                        <td class="ctr">{{ $i + 1 }}</td>
                        <td><b>{{ $data['siswa']->nama_peserta_didik }}</b></td>
                        <td class="ctr">{{ $data['siswa']->nis }}</td>
                        <td class="ctr">{{ $data['siswa']->kelas }}</td>
                        <td class="ctr"><b>{{ ($data['nilai_akhir'] ?? 0) > 0 ? number_format($data['nilai_akhir'], 1) : '—' }}</b></td>
                        <td class="ctr"><b>{{ $data['predikat']['huruf'] ?? '—' }}</b></td>
                        <td class="ctr">{{ $data['kehadiran_persen'] }}%</td>
                        <td class="ctr">
                            @if(($data['nilai_akhir'] ?? 0) >= 70)
                                Tuntas
                            @elseif(($data['nilai_akhir'] ?? 0) > 0)
                                Belum Tuntas
                            @else
                                Belum Dinilai
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ctr" style="padding:12px;color:#666">Belum ada data</td></tr>
                @endforelse
            </tbody>
        </table>

        @if(collect($dataLaporan)->first()['nilai_per_mapel']->count() ?? false)
            @php
                $allMapels = collect($dataLaporan)->flatMap(fn($d) => $d['nilai_per_mapel']->pluck('nama'))->unique()->sort()->values();
            @endphp
            <div class="section-title">B. DETAIL NILAI PER MATA PELAJARAN</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:20px">No</th>
                        <th>Nama Siswa</th>
                        @foreach($allMapels as $m)
                            <th style="text-align:center">{{ $m }}</th>
                        @endforeach
                        <th style="width:50px;text-align:center">Rata-rata</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dataLaporan as $i => $data)
                        <tr>
                            <td class="ctr">{{ $i + 1 }}</td>
                            <td><b>{{ $data['siswa']->nama_peserta_didik }}</b></td>
                            @foreach($allMapels as $m)
                                @php
                                    $mapelData = $data['nilai_per_mapel']->firstWhere('nama', $m);
                                @endphp
                                <td class="ctr">
                                    @if($mapelData)
                                        <b>{{ $mapelData['rata_rata'] }}</b>
                                        <span style="font-size:7px">({{ $mapelData['predikat'] }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endforeach
                            <td class="ctr"><b>{{ number_format($data['nilai_akhir'], 1) }}</b></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title">C. REKAPITULASI KEHADIRAN</div>
        <table>
            <thead>
                <tr>
                    <th style="width:20px">No</th>
                    <th>Nama Siswa</th>
                    <th style="width:50px">Hadir</th>
                    <th style="width:45px">Sakit</th>
                    <th style="width:40px">Izin</th>
                    <th style="width:40px">Alpa</th>
                    <th style="width:50px">Total</th>
                    <th style="width:50px">% Hadir</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dataLaporan as $i => $data)
                    <tr>
                        <td class="ctr">{{ $i + 1 }}</td>
                        <td><b>{{ $data['siswa']->nama_peserta_didik }}</b></td>
                        <td class="ctr">{{ $data['total_hadir'] }}</td>
                        <td class="ctr">{{ $data['total_sakit'] }}</td>
                        <td class="ctr">{{ $data['total_izin'] }}</td>
                        <td class="ctr">{{ $data['total_alpa'] }}</td>
                        <td class="ctr">{{ $data['total_kehadiran'] }}</td>
                        <td class="ctr"><b>{{ $data['kehadiran_persen'] }}%</b></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="ttd">
            <div class="ttd-col">&nbsp;</div>
            <div class="ttd-col">&nbsp;</div>
            <div class="ttd-col">
                {{ $pengaturan->pengaturan['kota'] ?? 'Makassar' }}, {{ date('d F Y') }}<br>
                Guru Kelas
                <div class="spacer"></div>
                <b><u>{{ $guru->nama_lengkap }}</u></b><br>
                <small>NIP. {{ $guru->rekaman['nip'] ?? '-' }}</small>
            </div>
        </div>

        <div class="footer">
            Laporan ini digenerate otomasis oleh SIMANTAP pada {{ now()->format('d F Y H:i') }}
        </div>
    </div>
</body>
</html>
