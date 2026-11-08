<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kehadiran;
use App\Models\PresensiPegawai;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresensiLaporanController extends Controller
{
    private const PERAN_MAP = [
        'guru' => ['guru'],
        'tendik' => ['tendik', 'kepsek'],
        'pegawai' => ['guru', 'tendik', 'kepsek'],
    ];

    public function index(Request $request)
    {
        $params = $this->resolveParams($request);
        $data = $this->buildReportData($params);
        $sekolah = SekolahSettings::first();
        $kelasList = Siswa::where('aktif', true)->whereNotNull('kelas')->distinct()->pluck('kelas')->sort()->values();
        $pegawaiFilterList = User::forSchool()->whereIn('peran', ['guru', 'tendik', 'kepsek'])
            ->where('aktif', true)
            ->orderBy('peran')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'peran']);

        return view('admin.presensi.laporan', array_merge($params, $data, [
            'sekolah' => $sekolah,
            'kelasList' => $kelasList,
            'pegawaiFilterList' => $pegawaiFilterList,
        ]));
    }

    public function cetak(Request $request)
    {
        $params = $this->resolveParams($request);
        $sekolah = SekolahSettings::first();
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        $kelasList = Siswa::where('aktif', true)->whereNotNull('kelas')->distinct()->pluck('kelas')->sort()->values();
        $pegawaiFilterList = User::forSchool()->whereIn('peran', ['guru', 'tendik', 'kepsek'])
            ->where('aktif', true)
            ->orderBy('peran')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'peran']);

        if ($params['kategori'] === 'siswa') {
            $data = $this->buildSiswaCetakData($params);
            return view('admin.presensi.cetak', array_merge($params, $data, [
                'sekolah' => $sekolah,
                'selectedUserId' => null,
                'kelasList' => $kelasList,
                'pegawaiFilterList' => $pegawaiFilterList,
            ]));
        }

        $data = $this->buildPegawaiCetakData($params, $userId);

        return view('admin.presensi.cetak', array_merge($params, $data, [
            'sekolah' => $sekolah,
            'selectedUserId' => $userId,
            'kelasList' => $kelasList,
            'pegawaiFilterList' => $pegawaiFilterList,
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        $params = $this->resolveParams($request);
        $data = $this->buildReportData($params);
        $kategori = $params['kategori'];
        $periode = $params['periode'];

        $filename = sprintf(
            'Laporan_Presensi_%s_%s_%s.csv',
            strtoupper($kategori),
            ucfirst($periode),
            $periode === 'harian' ? $params['tanggal'] : ($periode === 'bulanan' ? $params['tahun'] . '-' . str_pad((string) $params['bulan'], 2, '0', STR_PAD_LEFT) : $params['tahun'])
        );

        return response()->streamDownload(function () use ($params, $data) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($params['kategori'] === 'siswa') {
                $this->exportSiswaCsv($handle, $params, $data);
            } else {
                $this->exportPegawaiCsv($handle, $params, $data);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function resolveParams(Request $request): array
    {
        $kategori = $request->input('kategori', 'guru');
        if (!in_array($kategori, ['guru', 'tendik', 'pegawai', 'siswa'], true)) {
            $kategori = 'guru';
        }

        $periode = $request->input('periode', 'harian');
        if (!in_array($periode, ['harian', 'bulanan', 'tahunan'], true)) {
            $periode = 'harian';
        }

        $tanggal = $request->input('tanggal') ?: now()->toDateString();
        try {
            $tanggalRef = Carbon::parse($tanggal);
        } catch (\Throwable) {
            $tanggalRef = now();
            $tanggal = $tanggalRef->toDateString();
        }

        $bulan = (int) ($request->input('bulan') ?: $tanggalRef->month);
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) $tanggalRef->month;
        }

        $tahun = (int) ($request->input('tahun') ?: $tanggalRef->year);
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int) $tanggalRef->year;
        }

        $search = trim((string) $request->input('search', ''));
        $kelas = trim((string) $request->input('kelas', ''));
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        $tanggalCetakRaw = $request->input('tanggal_cetak');
        try {
            $tanggalCetak = $tanggalCetakRaw
                ? Carbon::parse($tanggalCetakRaw)->toDateString()
                : now()->toDateString();
        } catch (\Throwable) {
            $tanggalCetak = now()->toDateString();
        }

        $tampilkanWaktu = $request->boolean('tampilkan_waktu');

        return compact(
            'kategori',
            'periode',
            'tanggal',
            'bulan',
            'tahun',
            'search',
            'kelas',
            'userId',
            'tanggalCetak',
            'tampilkanWaktu'
        );
    }

    private function buildReportData(array $params): array
    {
        if ($params['kategori'] === 'siswa') {
            return $this->buildSiswaData($params);
        }

        return $this->buildPegawaiData($params);
    }

    private function buildPegawaiData(array $params): array
    {
        $peranList = self::PERAN_MAP[$params['kategori']] ?? self::PERAN_MAP['guru'];
        $periode = $params['periode'];
        $refDate = Carbon::parse($params['tanggal']);
        $startOfYear = Carbon::create($params['tahun'], 1, 1)->startOfYear();
        $endOfYear = Carbon::create($params['tahun'], 12, 31)->endOfYear();

        $startOfWeek = $refDate->copy()->startOfWeek();
        $endOfWeek = $refDate->copy()->endOfWeek();

        $startOfMonth = Carbon::create($params['tahun'], $params['bulan'], 1)->startOfMonth();
        $endOfMonth = Carbon::create($params['tahun'], $params['bulan'], 1)->endOfMonth();

        // Pegawai Query
        $pegawaiQuery = User::forSchool()->whereIn('peran', $peranList)
            ->where('aktif', true)
            ->orderBy('peran')
            ->orderBy('nama_lengkap');

        if (!empty($params['userId'])) {
            $pegawaiQuery->where('id', $params['userId']);
        }

        if (!empty($params['search'])) {
            $term = '%' . $params['search'] . '%';
            $pegawaiQuery->where(function ($q) use ($term) {
                $q->where('nama_lengkap', 'like', $term)
                    ->orWhere('nip', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $pegawaiList = $pegawaiQuery->get();
        $pegawaiIds = $pegawaiList->pluck('id')->all();

        // Ambil semua presensi pegawai dalam rentang 1 tahun bersangkutan
        $recordsTahun = PresensiPegawai::whereIn('user_id', $pegawaiIds)
            ->whereDate('tanggal', '>=', $startOfYear->toDateString())
            ->whereDate('tanggal', '<=', $endOfYear->toDateString())
            ->get();

        $recordsByUser = [];
        foreach ($recordsTahun as $rec) {
            $tglStr = Carbon::parse($rec->tanggal)->toDateString();
            $recordsByUser[$rec->user_id][$tglStr] = $rec;
        }

        $rows = [];
        $totalPersonel = $pegawaiList->count();
        $totalHadir = 0;
        $totalIzin = 0;
        $totalSakit = 0;
        $totalAlpa = 0;
        $totalTerlambat = 0;
        $sumMenitPeriode = 0;

        foreach ($pegawaiList as $user) {
            $userRecords = $recordsByUser[$user->id] ?? [];

            // Hitung menit tahun ini
            $menitTahun = 0;
            $hariMasukTahun = 0;
            $hadirTahun = 0;
            $sakitTahun = 0;
            $izinTahun = 0;
            $alpaTahun = 0;
            $terlambatTahun = 0;

            foreach ($userRecords as $tgl => $rec) {
                if (in_array($rec->status, ['hadir', 'terlambat'], true)) {
                    $hadirTahun++;
                    if ($rec->status === 'terlambat') {
                        $terlambatTahun++;
                    }
                } elseif ($rec->status === 'izin') {
                    $izinTahun++;
                } elseif ($rec->status === 'sakit') {
                    $sakitTahun++;
                } elseif ($rec->status === 'alpa') {
                    $alpaTahun++;
                }

                if ($rec->waktu_masuk && $rec->waktu_pulang) {
                    $m = max(0, Carbon::parse($rec->waktu_masuk)->diffInMinutes(Carbon::parse($rec->waktu_pulang), false));
                    $menitTahun += $m;
                    $hariMasukTahun++;
                }
            }

            // Hitung menit minggu ini
            $menitMinggu = 0;
            for ($d = 0; $d < 7; $d++) {
                $curr = $startOfWeek->copy()->addDays($d);
                $tglStr = $curr->toDateString();
                if (isset($userRecords[$tglStr])) {
                    $r = $userRecords[$tglStr];
                    if ($r->waktu_masuk && $r->waktu_pulang) {
                        $menitMinggu += max(0, Carbon::parse($r->waktu_masuk)->diffInMinutes(Carbon::parse($r->waktu_pulang), false));
                    }
                }
            }

            // Hitung menit bulan ini
            $menitBulan = 0;
            $hadirBulan = 0;
            $terlambatBulan = 0;
            $izinBulan = 0;
            $sakitBulan = 0;
            $alpaBulan = 0;
            $hariMasukBulan = 0;

            $daysInMonth = $startOfMonth->daysInMonth;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $currM = Carbon::create($params['tahun'], $params['bulan'], $d);
                $tglStr = $currM->toDateString();
                if (isset($userRecords[$tglStr])) {
                    $r = $userRecords[$tglStr];
                    if (in_array($r->status, ['hadir', 'terlambat'], true)) {
                        $hadirBulan++;
                        if ($r->status === 'terlambat') {
                            $terlambatBulan++;
                        }
                    } elseif ($r->status === 'izin') {
                        $izinBulan++;
                    } elseif ($r->status === 'sakit') {
                        $sakitBulan++;
                    } elseif ($r->status === 'alpa') {
                        $alpaBulan++;
                    }

                    if ($r->waktu_masuk && $r->waktu_pulang) {
                        $m = max(0, Carbon::parse($r->waktu_masuk)->diffInMinutes(Carbon::parse($r->waktu_pulang), false));
                        $menitBulan += $m;
                        $hariMasukBulan++;
                    }
                }
            }

            // Data hari terpilih (Harian)
            $todayRec = $userRecords[$params['tanggal']] ?? null;
            $menitHari = 0;
            $statusHari = $todayRec?->status ?? 'belum_absen';
            $jamMasuk = $todayRec?->waktu_masuk ? Carbon::parse($todayRec->waktu_masuk)->format('H:i') : '-';
            $jamPulang = $todayRec?->waktu_pulang ? Carbon::parse($todayRec->waktu_pulang)->format('H:i') : '-';

            if ($todayRec?->waktu_masuk && $todayRec?->waktu_pulang) {
                $menitHari = max(0, Carbon::parse($todayRec->waktu_masuk)->diffInMinutes(Carbon::parse($todayRec->waktu_pulang), false));
            }

            // Akumulasi KPI
            if ($periode === 'harian') {
                if (in_array($statusHari, ['hadir', 'terlambat'], true)) {
                    $totalHadir++;
                    if ($statusHari === 'terlambat') $totalTerlambat++;
                } elseif ($statusHari === 'izin') {
                    $totalIzin++;
                } elseif ($statusHari === 'sakit') {
                    $totalSakit++;
                } elseif ($statusHari === 'alpa') {
                    $totalAlpa++;
                }
                $sumMenitPeriode += $menitHari;
            } elseif ($periode === 'bulanan') {
                $totalHadir += $hadirBulan;
                $totalTerlambat += $terlambatBulan;
                $totalIzin += $izinBulan;
                $totalSakit += $sakitBulan;
                $totalAlpa += $alpaBulan;
                $sumMenitPeriode += $menitBulan;
            } else {
                $totalHadir += $hadirTahun;
                $totalTerlambat += $terlambatTahun;
                $totalIzin += $izinTahun;
                $totalSakit += $sakitTahun;
                $totalAlpa += $alpaTahun;
                $sumMenitPeriode += $menitTahun;
            }

            $rows[] = [
                'user' => $user,
                'peran_label' => ucfirst($user->peran === 'tendik' ? 'Tenaga Kependidikan' : ($user->peran === 'kepsek' ? 'Kepala Sekolah' : 'Guru')),
                'record' => $todayRec,
                'status' => $statusHari,
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang,
                'menit_hari' => $menitHari,
                'durasi_hari' => self::formatDuration($menitHari),
                'durasi_hari_jam' => round($menitHari / 60, 2),
                'menit_minggu' => $menitMinggu,
                'durasi_minggu' => self::formatDuration($menitMinggu),
                'durasi_minggu_jam' => round($menitMinggu / 60, 2),
                'menit_bulan' => $menitBulan,
                'durasi_bulan' => self::formatDuration($menitBulan),
                'durasi_bulan_jam' => round($menitBulan / 60, 2),
                'rata_rata_hari_bulan' => self::formatDuration($hariMasukBulan > 0 ? (int) round($menitBulan / $hariMasukBulan) : 0),
                'hadir_bulan' => $hadirBulan,
                'terlambat_bulan' => $terlambatBulan,
                'izin_bulan' => $izinBulan,
                'sakit_bulan' => $sakitBulan,
                'alpa_bulan' => $alpaBulan,
                'menit_tahun' => $menitTahun,
                'durasi_tahun' => self::formatDuration($menitTahun),
                'durasi_tahun_jam' => round($menitTahun / 60, 2),
                'rata_rata_minggu_tahun' => self::formatDuration((int) round($menitTahun / 52)),
                'rata_rata_hari_tahun' => self::formatDuration($hariMasukTahun > 0 ? (int) round($menitTahun / $hariMasukTahun) : 0),
                'hadir_tahun' => $hadirTahun,
                'terlambat_tahun' => $terlambatTahun,
                'izin_tahun' => $izinTahun,
                'sakit_tahun' => $sakitTahun,
                'alpa_tahun' => $alpaTahun,
                'metode' => $todayRec?->metode ? strtoupper($todayRec->metode) : '-',
            ];
        }

        $rataRataMenit = $totalPersonel > 0 ? (int) round($sumMenitPeriode / $totalPersonel) : 0;
        $persenHadir = $totalPersonel > 0 && $periode === 'harian'
            ? round(($totalHadir / $totalPersonel) * 100, 1)
            : 0;

        $kpi = [
            'total_personel' => $totalPersonel,
            'total_hadir' => $totalHadir,
            'total_terlambat' => $totalTerlambat,
            'total_izin' => $totalIzin,
            'total_sakit' => $totalSakit,
            'total_alpa' => $totalAlpa,
            'persen_hadir' => $persenHadir,
            'total_durasi_label' => self::formatDuration($sumMenitPeriode),
            'rata_rata_durasi_label' => self::formatDuration($rataRataMenit),
            'rata_rata_durasi_jam' => round($rataRataMenit / 60, 2),
        ];

        return compact('rows', 'kpi');
    }

    private function buildPegawaiCetakData(array $params, ?int $userId = null): array
    {
        $peranList = self::PERAN_MAP[$params['kategori']] ?? self::PERAN_MAP['guru'];
        $startOfMonth = Carbon::create($params['tahun'], $params['bulan'], 1)->startOfMonth();
        $endOfMonth = Carbon::create($params['tahun'], $params['bulan'], 1)->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        $namaBulan = $startOfMonth->locale('id')->translatedFormat('F');

        $daysMeta = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dt = Carbon::create($params['tahun'], $params['bulan'], $d);
            $daysMeta[$d] = [
                'day' => $d,
                'date' => $dt->toDateString(),
                'is_sunday' => $dt->isSunday(),
                'is_weekend' => $dt->isWeekend(),
            ];
        }

        $pegawaiQuery = User::forSchool()->whereIn('peran', $peranList)
            ->where('aktif', true)
            ->orderBy('nama_lengkap');

        if ($userId) {
            $pegawaiQuery->where('id', $userId);
        }

        if (!empty($params['search'])) {
            $term = '%' . $params['search'] . '%';
            $pegawaiQuery->where(function ($q) use ($term) {
                $q->where('nama_lengkap', 'like', $term)
                    ->orWhere('nip', 'like', $term);
            });
        }

        $pegawaiList = $pegawaiQuery->get();
        $pegawaiIds = $pegawaiList->pluck('id')->all();

        $records = PresensiPegawai::whereIn('user_id', $pegawaiIds)
            ->whereDate('tanggal', '>=', $startOfMonth->toDateString())
            ->whereDate('tanggal', '<=', $endOfMonth->toDateString())
            ->get();

        $recordsByUserAndDay = [];
        foreach ($records as $r) {
            $dayNum = (int) Carbon::parse($r->tanggal)->day;
            $recordsByUserAndDay[$r->user_id][$dayNum] = $r;
        }

        $rows = [];
        $totalMenitKeseluruhan = 0;

        foreach ($pegawaiList as $user) {
            $userRecs = $recordsByUserAndDay[$user->id] ?? [];
            $daily = [];
            $totalMenitPegawai = 0;
            $hariHadir = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $rec = $userRecs[$d] ?? null;
                $jamMasuk = $rec?->waktu_masuk ? Carbon::parse($rec->waktu_masuk)->format('H:i') : null;
                $jamPulang = $rec?->waktu_pulang ? Carbon::parse($rec->waktu_pulang)->format('H:i') : null;
                $menit = 0;
                $label = '';
                $tipe = 'kosong';

                if ($rec) {
                    if (in_array($rec->status, ['hadir', 'terlambat'], true)) {
                        $hariHadir++;
                    }

                    if ($rec->waktu_masuk && $rec->waktu_pulang) {
                        $menit = max(0, Carbon::parse($rec->waktu_masuk)->diffInMinutes(Carbon::parse($rec->waktu_pulang), false));
                        $totalMenitPegawai += $menit;
                        $totalMenitKeseluruhan += $menit;
                        $label = self::formatShortHours($menit);
                        $tipe = 'jam';
                    } elseif ($rec->waktu_masuk && !$rec->waktu_pulang) {
                        $label = '0j';
                        $tipe = 'belum_pulang';
                    } else {
                        $label = match ($rec->status) {
                            'izin' => 'I',
                            'sakit' => 'S',
                            'alpa' => 'A',
                            'hadir', 'terlambat' => 'H',
                            default => '',
                        };
                        $tipe = 'status';
                    }
                }

                $daily[$d] = [
                    'has_record' => $rec !== null,
                    'status' => $rec?->status,
                    'tipe' => $tipe,
                    'jam_masuk' => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                    'menit' => $menit,
                    'label' => $label,
                    'durasi_lengkap' => $menit > 0 ? self::formatDurationDetailed($menit) : '-',
                ];
            }

            $rows[] = [
                'nama_pegawai' => $user->nama_lengkap,
                'nip' => $user->nip ?: '-',
                'peran' => ucfirst($user->peran === 'tendik' ? 'Tendik' : ($user->peran === 'kepsek' ? 'Kepsek' : 'Guru')),
                'daily' => $daily,
                'hari_hadir' => $hariHadir,
                'total_menit' => $totalMenitPegawai,
                'total_jam_singkat' => self::formatDuration($totalMenitPegawai),
                'total_jam_lengkap' => self::formatDurationDetailed($totalMenitPegawai),
                'total_jam_desimal' => round($totalMenitPegawai / 60, 2),
            ];
        }

        return [
            'cetakRows' => $rows,
            'daysInMonth' => $daysInMonth,
            'daysMeta' => $daysMeta,
            'namaBulan' => $namaBulan,
            'totalMenitKeseluruhan' => $totalMenitKeseluruhan,
            'totalDurasiSingkat' => self::formatDuration($totalMenitKeseluruhan),
            'totalDurasiKeseluruhan' => self::formatDurationDetailed($totalMenitKeseluruhan),
            'totalJamDesimalKeseluruhan' => round($totalMenitKeseluruhan / 60, 2),
            'pegawaiList' => $pegawaiList,
        ];
    }

    private function buildSiswaCetakData(array $params): array
    {
        $startOfMonth = Carbon::create($params['tahun'], $params['bulan'], 1)->startOfMonth();
        $endOfMonth = Carbon::create($params['tahun'], $params['bulan'], 1)->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        $namaBulan = $startOfMonth->locale('id')->translatedFormat('F');

        $daysMeta = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dt = Carbon::create($params['tahun'], $params['bulan'], $d);
            $daysMeta[$d] = [
                'day' => $d,
                'date' => $dt->toDateString(),
                'is_sunday' => $dt->isSunday(),
                'is_weekend' => $dt->isWeekend(),
            ];
        }

        $siswaQuery = Siswa::where('aktif', true)->orderBy('kelas')->orderBy('nama_peserta_didik');

        if (!empty($params['kelas'])) {
            $siswaQuery->where('kelas', $params['kelas']);
        }

        if (!empty($params['search'])) {
            $term = '%' . $params['search'] . '%';
            $siswaQuery->where(function ($q) use ($term) {
                $q->where('nama_peserta_didik', 'like', $term)
                    ->orWhere('nisn', 'like', $term)
                    ->orWhere('nis', 'like', $term);
            });
        }

        $siswaList = $siswaQuery->get();
        $siswaIds = $siswaList->pluck('id')->all();

        $kehadiran = Kehadiran::whereIn('siswa_id', $siswaIds)
            ->whereDate('tanggal', '>=', $startOfMonth->toDateString())
            ->whereDate('tanggal', '<=', $endOfMonth->toDateString())
            ->get();

        $recordsBySiswaAndDay = [];
        foreach ($kehadiran as $kh) {
            $dayNum = (int) Carbon::parse($kh->tanggal)->day;
            $recordsBySiswaAndDay[$kh->siswa_id][$dayNum] = $kh;
        }

        $rows = [];
        foreach ($siswaList as $siswa) {
            $userRecs = $recordsBySiswaAndDay[$siswa->id] ?? [];
            $daily = [];
            $h = 0;
            $s = 0;
            $i = 0;
            $a = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $rec = $userRecs[$d] ?? null;
                $kode = '';
                if ($rec) {
                    $st = strtoupper((string) $rec->status);
                    if ($st === 'H' || $st === 'HADIR') {
                        $kode = 'H';
                        $h++;
                    } elseif ($st === 'S' || $st === 'SAKIT') {
                        $kode = 'S';
                        $s++;
                    } elseif ($st === 'I' || $st === 'IZIN') {
                        $kode = 'I';
                        $i++;
                    } elseif ($st === 'A' || $st === 'ALPA') {
                        $kode = 'A';
                        $a++;
                    }
                }
                $daily[$d] = [
                    'has_record' => $rec !== null,
                    'jam_masuk' => $rec?->waktu_masuk ? Carbon::parse($rec->waktu_masuk)->format('H:i') : null,
                    'label' => $kode,
                ];
            }

            $rows[] = [
                'nama_pegawai' => $siswa->nama_peserta_didik,
                'nip' => $siswa->nisn ?: ($siswa->nis ?: '-'),
                'peran' => $siswa->kelas ?: '-',
                'daily' => $daily,
                'hadir' => $h,
                'sakit' => $s,
                'izin' => $i,
                'alpa' => $a,
            ];
        }

        return [
            'cetakRows' => $rows,
            'daysInMonth' => $daysInMonth,
            'daysMeta' => $daysMeta,
            'namaBulan' => $namaBulan,
            'totalMenitKeseluruhan' => 0,
            'totalDurasiSingkat' => '-',
            'totalDurasiKeseluruhan' => '-',
            'totalJamDesimalKeseluruhan' => 0,
            'pegawaiList' => $siswaList,
        ];
    }

    private function buildSiswaData(array $params): array
    {
        $periode = $params['periode'];
        $startOfYear = Carbon::create($params['tahun'], 1, 1)->startOfYear();
        $endOfYear = Carbon::create($params['tahun'], 12, 31)->endOfYear();
        $startOfMonth = Carbon::create($params['tahun'], $params['bulan'], 1)->startOfMonth();
        $endOfMonth = Carbon::create($params['tahun'], $params['bulan'], 1)->endOfMonth();

        $siswaQuery = Siswa::where('aktif', true)
            ->orderBy('kelas')
            ->orderBy('nama_peserta_didik');

        if (!empty($params['kelas'])) {
            $siswaQuery->where('kelas', $params['kelas']);
        }

        if (!empty($params['search'])) {
            $term = '%' . $params['search'] . '%';
            $siswaQuery->where(function ($q) use ($term) {
                $q->where('nama_peserta_didik', 'like', $term)
                    ->orWhere('nisn', 'like', $term)
                    ->orWhere('nis', 'like', $term);
            });
        }

        $siswaList = $siswaQuery->get();
        $siswaIds = $siswaList->pluck('id')->all();

        // Ambil data kehadiran siswa dalam 1 tahun
        $kehadiranTahun = Kehadiran::whereIn('siswa_id', $siswaIds)
            ->whereDate('tanggal', '>=', $startOfYear->toDateString())
            ->whereDate('tanggal', '<=', $endOfYear->toDateString())
            ->get();

        $recordsBySiswa = [];
        foreach ($kehadiranTahun as $kh) {
            $tglStr = Carbon::parse($kh->tanggal)->toDateString();
            $recordsBySiswa[$kh->siswa_id][$tglStr] = $kh;
        }

        $rows = [];
        $totalSiswa = $siswaList->count();
        $totalHadir = 0;
        $totalSakit = 0;
        $totalIzin = 0;
        $totalAlpa = 0;

        foreach ($siswaList as $siswa) {
            $userRecords = $recordsBySiswa[$siswa->id] ?? [];

            // Tahunan
            $hadirTahun = 0;
            $sakitTahun = 0;
            $izinTahun = 0;
            $alpaTahun = 0;

            foreach ($userRecords as $tgl => $kh) {
                $st = strtoupper((string) $kh->status);
                if ($st === 'H' || $st === 'HADIR') $hadirTahun++;
                elseif ($st === 'S' || $st === 'SAKIT') $sakitTahun++;
                elseif ($st === 'I' || $st === 'IZIN') $izinTahun++;
                elseif ($st === 'A' || $st === 'ALPA') $alpaTahun++;
            }

            // Bulanan
            $hadirBulan = 0;
            $sakitBulan = 0;
            $izinBulan = 0;
            $alpaBulan = 0;
            $daysInMonth = $startOfMonth->daysInMonth;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $currM = Carbon::create($params['tahun'], $params['bulan'], $d);
                $tglStr = $currM->toDateString();
                if (isset($userRecords[$tglStr])) {
                    $kh = $userRecords[$tglStr];
                    $st = strtoupper((string) $kh->status);
                    if ($st === 'H' || $st === 'HADIR') $hadirBulan++;
                    elseif ($st === 'S' || $st === 'SAKIT') $sakitBulan++;
                    elseif ($st === 'I' || $st === 'IZIN') $izinBulan++;
                    elseif ($st === 'A' || $st === 'ALPA') $alpaBulan++;
                }
            }

            // Harian
            $todayKh = $userRecords[$params['tanggal']] ?? null;
            $statusRaw = $todayKh?->status ? strtoupper((string) $todayKh->status) : null;
            $statusHari = match ($statusRaw) {
                'H', 'HADIR' => 'Hadir',
                'S', 'SAKIT' => 'Sakit',
                'I', 'IZIN' => 'Izin',
                'A', 'ALPA' => 'Alpa',
                default => 'Belum Absen',
            };
            $jamMasuk = $todayKh?->waktu_masuk ? Carbon::parse($todayKh->waktu_masuk)->format('H:i') : '-';

            if ($periode === 'harian') {
                if ($statusHari === 'Hadir') $totalHadir++;
                elseif ($statusHari === 'Sakit') $totalSakit++;
                elseif ($statusHari === 'Izin') $totalIzin++;
                elseif ($statusHari === 'Alpa') $totalAlpa++;
            } elseif ($periode === 'bulanan') {
                $totalHadir += $hadirBulan;
                $totalSakit += $sakitBulan;
                $totalIzin += $izinBulan;
                $totalAlpa += $alpaBulan;
            } else {
                $totalHadir += $hadirTahun;
                $totalSakit += $sakitTahun;
                $totalIzin += $izinTahun;
                $totalAlpa += $alpaTahun;
            }

            $totalHariBulan = $hadirBulan + $sakitBulan + $izinBulan + $alpaBulan;
            $persenBulan = $totalHariBulan > 0 ? round(($hadirBulan / $totalHariBulan) * 100, 1) : 0;

            $totalHariTahun = $hadirTahun + $sakitTahun + $izinTahun + $alpaTahun;
            $persenTahun = $totalHariTahun > 0 ? round(($hadirTahun / $totalHariTahun) * 100, 1) : 0;

            $rows[] = [
                'siswa' => $siswa,
                'kelas' => $siswa->kelas ?? '-',
                'status' => $statusHari,
                'jam_masuk' => $jamMasuk,
                'metode' => $todayKh?->metode ? strtoupper($todayKh->metode) : '-',
                'keterangan' => $todayKh?->keterangan ?? '-',
                'hadir_bulan' => $hadirBulan,
                'sakit_bulan' => $sakitBulan,
                'izin_bulan' => $izinBulan,
                'alpa_bulan' => $alpaBulan,
                'persen_bulan' => $persenBulan,
                'hadir_tahun' => $hadirTahun,
                'sakit_tahun' => $sakitTahun,
                'izin_tahun' => $izinTahun,
                'alpa_tahun' => $alpaTahun,
                'persen_tahun' => $persenTahun,
            ];
        }

        $persenHadir = $totalSiswa > 0 && $periode === 'harian'
            ? round(($totalHadir / $totalSiswa) * 100, 1)
            : 0;

        $kpi = [
            'total_personel' => $totalSiswa,
            'total_hadir' => $totalHadir,
            'total_terlambat' => 0,
            'total_izin' => $totalIzin,
            'total_sakit' => $totalSakit,
            'total_alpa' => $totalAlpa,
            'persen_hadir' => $persenHadir,
        ];

        return compact('rows', 'kpi');
    }

    private function exportPegawaiCsv($handle, array $params, array $data): void
    {
        $periode = $params['periode'];

        if ($periode === 'harian') {
            fputcsv($handle, [
                'No', 'Nama Pegawai', 'NIP', 'Peran', 'Tanggal', 'Status',
                'Jam Masuk', 'Jam Pulang', 'Jam Kerja Hari Ini', 'Akumulasi Jam Minggu Ini',
                'Akumulasi Jam Tahun Ini', 'Metode Presensi'
            ]);

            foreach ($data['rows'] as $i => $row) {
                fputcsv($handle, [
                    $i + 1,
                    $row['user']->nama_lengkap,
                    $row['user']->nip ?: '-',
                    $row['peran_label'],
                    $params['tanggal'],
                    ucfirst(str_replace('_', ' ', $row['status'])),
                    $row['jam_masuk'],
                    $row['jam_pulang'],
                    $row['durasi_hari'],
                    $row['durasi_minggu'],
                    $row['durasi_tahun'],
                    $row['metode'],
                ]);
            }
        } elseif ($periode === 'bulanan') {
            fputcsv($handle, [
                'No', 'Nama Pegawai', 'NIP', 'Peran', 'Bulan/Tahun',
                'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa',
                'Total Jam Bulan Ini', 'Rata-rata Jam/Hari',
                'Jam Minggu Ini', 'Akumulasi Jam Tahun Ini'
            ]);

            $bulanTahun = Carbon::create($params['tahun'], $params['bulan'], 1)->locale('id')->translatedFormat('F Y');
            foreach ($data['rows'] as $i => $row) {
                fputcsv($handle, [
                    $i + 1,
                    $row['user']->nama_lengkap,
                    $row['user']->nip ?: '-',
                    $row['peran_label'],
                    $bulanTahun,
                    $row['hadir_bulan'],
                    $row['terlambat_bulan'],
                    $row['izin_bulan'],
                    $row['sakit_bulan'],
                    $row['alpa_bulan'],
                    $row['durasi_bulan'],
                    $row['rata_rata_hari_bulan'],
                    $row['durasi_minggu'],
                    $row['durasi_tahun'],
                ]);
            }
        } else {
            fputcsv($handle, [
                'No', 'Nama Pegawai', 'NIP', 'Peran', 'Tahun',
                'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa',
                'Total Jam Tahun Ini', 'Rata-rata Jam/Minggu', 'Rata-rata Jam/Hari'
            ]);

            foreach ($data['rows'] as $i => $row) {
                fputcsv($handle, [
                    $i + 1,
                    $row['user']->nama_lengkap,
                    $row['user']->nip ?: '-',
                    $row['peran_label'],
                    $params['tahun'],
                    $row['hadir_tahun'],
                    $row['terlambat_tahun'],
                    $row['izin_tahun'],
                    $row['sakit_tahun'],
                    $row['alpa_tahun'],
                    $row['durasi_tahun'],
                    $row['rata_rata_minggu_tahun'],
                    $row['rata_rata_hari_tahun'],
                ]);
            }
        }
    }

    private function exportSiswaCsv($handle, array $params, array $data): void
    {
        $periode = $params['periode'];

        if ($periode === 'harian') {
            fputcsv($handle, [
                'No', 'NISN', 'NIS', 'Nama Siswa', 'Kelas', 'Tanggal', 'Status Kehadiran',
                'Jam Masuk', 'Metode', 'Keterangan'
            ]);

            foreach ($data['rows'] as $i => $row) {
                fputcsv($handle, [
                    $i + 1,
                    $row['siswa']->nisn ?: '-',
                    $row['siswa']->nis ?: '-',
                    $row['siswa']->nama_peserta_didik,
                    $row['kelas'],
                    $params['tanggal'],
                    $row['status'],
                    $row['jam_masuk'],
                    $row['metode'],
                    $row['keterangan'],
                ]);
            }
        } elseif ($periode === 'bulanan') {
            fputcsv($handle, [
                'No', 'NISN', 'NIS', 'Nama Siswa', 'Kelas', 'Bulan/Tahun',
                'Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Alpa (A)', '% Kehadiran'
            ]);

            $bulanTahun = Carbon::create($params['tahun'], $params['bulan'], 1)->locale('id')->translatedFormat('F Y');
            foreach ($data['rows'] as $i => $row) {
                fputcsv($handle, [
                    $i + 1,
                    $row['siswa']->nisn ?: '-',
                    $row['siswa']->nis ?: '-',
                    $row['siswa']->nama_peserta_didik,
                    $row['kelas'],
                    $bulanTahun,
                    $row['hadir_bulan'],
                    $row['sakit_bulan'],
                    $row['izin_bulan'],
                    $row['alpa_bulan'],
                    $row['persen_bulan'] . '%',
                ]);
            }
        } else {
            fputcsv($handle, [
                'No', 'NISN', 'NIS', 'Nama Siswa', 'Kelas', 'Tahun',
                'Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Alpa (A)', '% Kehadiran Tahunan'
            ]);

            foreach ($data['rows'] as $i => $row) {
                fputcsv($handle, [
                    $i + 1,
                    $row['siswa']->nisn ?: '-',
                    $row['siswa']->nis ?: '-',
                    $row['siswa']->nama_peserta_didik,
                    $row['kelas'],
                    $params['tahun'],
                    $row['hadir_tahun'],
                    $row['sakit_tahun'],
                    $row['izin_tahun'],
                    $row['alpa_tahun'],
                    $row['persen_tahun'] . '%',
                ]);
            }
        }
    }

    public static function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0j 0m';
        }

        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;

        return "{$hours}j {$remMinutes}m";
    }

    public static function formatShortHours(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0j';
        }

        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;

        return $remMinutes > 0 ? "{$hours}j {$remMinutes}m" : "{$hours}j";
    }

    public static function formatDurationDetailed(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 Jam 0 Menit';
        }

        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;

        return "{$hours} Jam {$remMinutes} Menit";
    }
}
