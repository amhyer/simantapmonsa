<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catatan;
use App\Models\Kehadiran;
use App\Models\KenaikanKelas;
use App\Models\NilaiErapot;
use App\Models\P5Penilaian;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Models\User;
use Illuminate\Http\Request;

class CetakController extends Controller
{
    private function daftarKelas()
    {
        return Siswa::whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');
    }

    private function tahunDefault(): string
    {
        $tahun = (int) date('Y');

        return $tahun.'/'.($tahun + 1);
    }

    private function rekapKehadiran(int $siswaId): array
    {
        $counts = Kehadiran::where('siswa_id', $siswaId)
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->all();

        return [
            'S' => (int) ($counts['S'] ?? 0),
            'I' => (int) ($counts['I'] ?? 0),
            'A' => (int) ($counts['A'] ?? 0),
        ];
    }

    // ---------- Leger ----------

    public function leger(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $siswa = collect();
        $mapel = collect();
        $matriks = [];
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get(['id', 'nama_peserta_didik', 'nisn']);
            $nilai = NilaiErapot::where('kelas', $kelas)
                ->where('semester', $semester)
                ->where('tahun_ajaran', $tahunAjaran)
                ->whereNotNull('nilai_akhir')
                ->get(['siswa_id', 'mata_pelajaran', 'nilai_akhir']);
            $mapel = $nilai->pluck('mata_pelajaran')->unique()->sort()->values();
            foreach ($nilai as $n) {
                $matriks[$n->siswa_id][$n->mata_pelajaran] = $n->nilai_akhir;
            }
        }

        return view('admin.cetak.leger', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran', 'siswa', 'mapel', 'matriks'
        ));
    }

    // ---------- Pelengkap ----------

    public function pelengkap(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();

        $siswa = collect();
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get();

            // PERBAIKAN N+1: sebelumnya 2 query dijalankan per siswa
            // (rekap kehadiran + catatan terakhir). Kini 2 query agregat
            // untuk seluruh kelas sekaligus.
            $siswaIds = $siswa->pluck('id');

            $rekapSemua = Kehadiran::whereIn('siswa_id', $siswaIds)
                ->selectRaw('siswa_id, status, COUNT(*) as jumlah')
                ->groupBy('siswa_id', 'status')
                ->get()
                ->groupBy('siswa_id');

            $catatanSemua = Catatan::whereIn('siswa_id', $siswaIds)
                ->orderByDesc('tanggal')
                ->get(['siswa_id', 'catatan', 'tanggal'])
                ->groupBy('siswa_id')
                ->map(fn ($baris) => $baris->first());

            foreach ($siswa as $row) {
                $counts = ($rekapSemua[$row->id] ?? collect())
                    ->pluck('jumlah', 'status')
                    ->all();

                $row->rekap_hadir = [
                    'S' => (int) ($counts['S'] ?? 0),
                    'I' => (int) ($counts['I'] ?? 0),
                    'A' => (int) ($counts['A'] ?? 0),
                ];
                $row->catatan_terakhir = $catatanSemua[$row->id] ?? null;
            }
        }

        return view('admin.cetak.pelengkap', compact('kelasList', 'kelas', 'siswa'));
    }

    // ---------- Nilai rapor ----------

    public function nilai(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $siswa = collect();
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->withCount(['nilaiErapot' => fn ($q) => $q->where('semester', $semester)->where('tahun_ajaran', $tahunAjaran)])
                ->orderBy('nama_peserta_didik')
                ->get();
        }

        return view('admin.cetak.nilai', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran', 'siswa'
        ));
    }

    public function showNilai(Request $request, Siswa $siswa)
    {
        $validated = $request->validate([
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
            'kertas' => 'nullable|in:A4,F4',
            'margin_kiri' => 'nullable|numeric|min:5|max:40',
            'margin_kanan' => 'nullable|numeric|min:5|max:40',
            'margin_atas' => 'nullable|numeric|min:5|max:40',
            'margin_bawah' => 'nullable|numeric|min:5|max:40',
            'posisi_ttd_ks' => 'nullable|in:sejajar,bawah',
            'tampil_ttd' => 'nullable|in:0,1',
            'tampil_wali' => 'nullable|in:0,1',
        ]);
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $sekolah = SekolahSettings::first();
        $tanggalRapor = TanggalRapor::where('tahun_ajaran', $tahunAjaran)
            ->whereRaw('LOWER(semester) = ?', [strtolower($semester)])
            ->first();
        $raporConfig = self::ambilConfigRapor($sekolah);
        $tampilanRapor = self::ambilTampilanRapor($sekolah);
        $printOpts = $this->resolvePrintOptions($validated, $raporConfig);

        $mapelMetaMap = $this->loadMapelMetaMap();
        $payload = $this->buildRaporPayload($siswa, $semester, $tahunAjaran, $sekolah, $mapelMetaMap);

        return view('admin.cetak.nilai-show', array_merge($payload, [
            'sekolah' => $sekolah,
            'tanggalRapor' => $tanggalRapor,
            'semester' => $semester,
            'tahunAjaran' => $tahunAjaran,
            'raporConfig' => $raporConfig,
            'tampilanRapor' => $tampilanRapor,
            'printOpts' => $printOpts,
        ]));
    }

    public function showNilaiKelas(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
            'kertas' => 'nullable|in:A4,F4',
            'margin_kiri' => 'nullable|numeric|min:5|max:40',
            'margin_kanan' => 'nullable|numeric|min:5|max:40',
            'margin_atas' => 'nullable|numeric|min:5|max:40',
            'margin_bawah' => 'nullable|numeric|min:5|max:40',
            'posisi_ttd_ks' => 'nullable|in:sejajar,bawah',
            'tampil_ttd' => 'nullable|in:0,1',
            'tampil_wali' => 'nullable|in:0,1',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $sekolah = SekolahSettings::first();
        $tanggalRapor = TanggalRapor::where('tahun_ajaran', $tahunAjaran)
            ->whereRaw('LOWER(semester) = ?', [strtolower($semester)])
            ->first();
        $raporConfig = self::ambilConfigRapor($sekolah);
        $tampilanRapor = self::ambilTampilanRapor($sekolah);
        $printOpts = $this->resolvePrintOptions($validated, $raporConfig);
        $mapelMetaMap = $this->loadMapelMetaMap();

        $siswaList = $kelas
            ? Siswa::where('kelas', $kelas)->where('aktif', true)->orderBy('nama_peserta_didik')->get()
            : collect();

        $raporList = $siswaList->map(
            fn (Siswa $s) => $this->buildRaporPayload($s, $semester, $tahunAjaran, $sekolah, $mapelMetaMap)
        )->values();

        return view('admin.cetak.nilai-kelas-show', compact(
            'kelas', 'semester', 'tahunAjaran', 'sekolah', 'tanggalRapor',
            'raporConfig', 'tampilanRapor', 'printOpts', 'raporList'
        ));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $raporConfig
     * @return array<string, mixed>
     */
    private function resolvePrintOptions(array $validated, array $raporConfig): array
    {
        return [
            'kertas' => $validated['kertas'] ?? ($raporConfig['ukuran_kertas'] ?? 'A4'),
            'margin_kiri' => (float) ($validated['margin_kiri'] ?? 15),
            'margin_kanan' => (float) ($validated['margin_kanan'] ?? 15),
            'margin_atas' => (float) ($validated['margin_atas'] ?? (($raporConfig['margin_atas_cm'] ?? 1.5) * 10)),
            'margin_bawah' => (float) ($validated['margin_bawah'] ?? 12),
            'posisi_ttd_ks' => $validated['posisi_ttd_ks'] ?? 'sejajar',
            'tampil_ttd' => ($validated['tampil_ttd'] ?? '1') === '1',
            'tampil_wali' => ($validated['tampil_wali'] ?? '1') === '1',
        ];
    }

    /**
     * @return array<string, array{kelompok: string, urutan: int}>
     */
    private function loadMapelMetaMap(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('mata_pelajaran')) {
            return [];
        }

        $map = [];
        foreach (\App\Models\MataPelajaran::all(['nama', 'kelompok', 'urutan']) as $m) {
            $klp = strtoupper(trim((string) ($m->kelompok ?: 'A')));
            if (! in_array($klp, ['A', 'B'], true)) {
                $klp = in_array($klp, ['L', 'MUATAN LOKAL', 'PILIHAN'], true) ? 'B' : 'A';
            }
            $map[mb_strtolower(trim((string) $m->nama))] = [
                'kelompok' => $klp,
                'urutan' => (int) ($m->urutan ?? 999),
            ];
        }

        return $map;
    }

    /**
     * Susun payload lengkap satu lembar rapor siswa agar identik dengan e-Rapor SD
     * (dipakai bersama oleh cetak per siswa dan cetak 1 kelas sekaligus).
     *
     * @param  array<string, array{kelompok: string, urutan: int}>  $mapelMetaMap
     * @return array<string, mixed>
     */
    private function buildRaporPayload(
        Siswa $siswa,
        string $semester,
        string $tahunAjaran,
        ?SekolahSettings $sekolah,
        array $mapelMetaMap,
    ): array {
        $nilaiRaw = NilaiErapot::where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->get();

        $nilai = $nilaiRaw->sortBy(function ($n) use ($mapelMetaMap) {
            $key = mb_strtolower(trim((string) $n->mata_pelajaran));
            $meta = $mapelMetaMap[$key] ?? ['kelompok' => 'A', 'urutan' => 999];

            return [$meta['kelompok'], $meta['urutan'] ?: 999, $key];
        })->values();

        $nilaiKelompok = ['A' => collect(), 'B' => collect()];
        foreach ($nilai as $n) {
            $key = mb_strtolower(trim((string) $n->mata_pelajaran));
            $klp = $mapelMetaMap[$key]['kelompok'] ?? 'A';
            $nilaiKelompok[$klp === 'B' ? 'B' : 'A']->push($n);
        }

        $hadir = $this->rekapKehadiranSemester($siswa->id, $semester, $tahunAjaran);

        $catatan = $this->catatanSemester($siswa->id, $semester, $tahunAjaran);

        $kokurikuler = P5Penilaian::where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderByRaw("CASE WHEN tema = 'Kokurikuler Profil Lulusan' THEN 0 ELSE 1 END")
            ->get(['tema', 'dimensi', 'predikat', 'catatan']);

        $ekstrakurikuler = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('siswa_ekstrakurikuler') && \Illuminate\Support\Facades\Schema::hasTable('ekstrakurikuler')) {
            $ekstrakurikuler = \Illuminate\Support\Facades\DB::table('siswa_ekstrakurikuler')
                ->join('ekstrakurikuler', 'ekstrakurikuler.id', '=', 'siswa_ekstrakurikuler.ekstrakurikuler_id')
                ->where('siswa_ekstrakurikuler.siswa_id', $siswa->id)
                ->orderBy('ekstrakurikuler.nama')
                ->get(['ekstrakurikuler.nama', 'siswa_ekstrakurikuler.nilai', 'siswa_ekstrakurikuler.keterangan']);
        }

        $rata = $nilai->whereNotNull('nilai_akhir')->count()
            ? round($nilai->whereNotNull('nilai_akhir')->avg('nilai_akhir'), 2)
            : null;

        $ttdWaliMap = ($sekolah?->pengaturan ?? [])['ttd_wali_kelas'] ?? [];
        $rombel = $siswa->kelas ? Rombel::where('nama_rombel', $siswa->kelas)->first(['guru_id', 'tingkat']) : null;
        $waliGuruId = $siswa->guru_id ?? $rombel?->guru_id;
        $waliUser = $waliGuruId ? \App\Models\User::find($waliGuruId, ['nama_lengkap', 'nip']) : null;
        $waliNama = $waliUser?->nama_lengkap ?: ($siswa->nama_guru ?: null);
        $waliNip = $waliUser?->nip ?: null;
        $ttdWaliUri = $waliGuruId ? ($ttdWaliMap[(string) $waliGuruId] ?? null) : null;

        $kepsekNip = ($sekolah?->pengaturan ?? [])['nip_kepala_sekolah'] ?? null;

        $fase = $nilai->first()?->fase;
        if (! $fase) {
            $tingkatNum = (int) ($rombel?->tingkat ?: (preg_match('/(VI|6)/i', (string) $siswa->kelas) ? 6
                : (preg_match('/(V|5)/i', (string) $siswa->kelas) ? 5
                : (preg_match('/(IV|4)/i', (string) $siswa->kelas) ? 4
                : (preg_match('/(III|3)/i', (string) $siswa->kelas) ? 3
                : (preg_match('/(II|2)/i', (string) $siswa->kelas) ? 2 : 1))))));
            $fase = $tingkatNum <= 2 ? 'A' : ($tingkatNum <= 4 ? 'B' : 'C');
        }
        $isKelasAkhir = (int) ($rombel?->tingkat ?? 0) === 6 || (bool) preg_match('/^(kelas\s*)?(vi|6)\b/i', trim((string) $siswa->kelas));

        $naikKelas = $semester === 'Genap'
            ? KenaikanKelas::where('siswa_id', $siswa->id)
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('semester', $semester)
                ->first()
            : null;

        return compact(
            'siswa', 'nilai', 'nilaiKelompok', 'hadir', 'catatan',
            'kokurikuler', 'ekstrakurikuler', 'rata', 'ttdWaliUri',
            'waliNama', 'waliNip', 'kepsekNip', 'fase', 'isKelasAkhir', 'naikKelas'
        );
    }

    /**
     * Rekap ketidakhadiran (S/I/A) khusus semester & tahun ajaran terpilih;
     * fallback ke rekap keseluruhan jika data kehadiran hanya tercatat di luar rentang tanggal semester.
     *
     * @return array{S: int, I: int, A: int}
     */
    private function rekapKehadiranSemester(int $siswaId, string $semester, string $tahunAjaran): array
    {
        $yr = (int) substr($tahunAjaran, 0, 4);
        if ($yr >= 2000) {
            $isGenap = strtolower($semester) === 'genap';
            $semCode = $yr.($isGenap ? '2' : '1');
            $start = $isGenap ? ($yr + 1).'-01-01' : $yr.'-07-01';
            $end = $isGenap ? ($yr + 1).'-06-30' : $yr.'-12-31';

            $counts = Kehadiran::where('siswa_id', $siswaId)
                ->where(function ($q) use ($start, $end, $semCode) {
                    $q->whereBetween('tanggal', [$start, $end])
                        ->orWhere('keterangan', 'Rekap e-Rapor (.xsp) '.$semCode);
                })
                ->selectRaw('status, COUNT(*) as jumlah')
                ->groupBy('status')
                ->pluck('jumlah', 'status');

            if ($counts->isNotEmpty()) {
                return [
                    'S' => (int) ($counts['S'] ?? 0),
                    'I' => (int) ($counts['I'] ?? 0),
                    'A' => (int) ($counts['A'] ?? 0),
                ];
            }
        }

        return $this->rekapKehadiran($siswaId);
    }

    private function catatanSemester(int $siswaId, string $semester, string $tahunAjaran): ?Catatan
    {
        $yr = (int) substr($tahunAjaran, 0, 4);
        if ($yr >= 2000) {
            $isGenap = strtolower($semester) === 'genap';
            $start = $isGenap ? ($yr + 1).'-01-01' : $yr.'-07-01';
            $end = $isGenap ? ($yr + 1).'-06-30' : $yr.'-12-31';

            $scoped = Catatan::where('siswa_id', $siswaId)
                ->whereBetween('tanggal', [$start, $end])
                ->latest('tanggal')
                ->first(['catatan']);

            if ($scoped) {
                return $scoped;
            }
        }

        return Catatan::where('siswa_id', $siswaId)->latest('tanggal')->first(['catatan']);
    }

    public static function ambilConfigRapor(?SekolahSettings $sekolah): array
    {
        $saved = ($sekolah?->pengaturan ?? [])['rapor'] ?? [];

        return array_merge([
            'kementerian' => 'Kemendikdasmen',
            'ukuran_kertas' => 'A4',
            'margin_atas_cm' => 1.5,
            'kapitalisasi_nama' => 'asli',
            'format_desimal' => '0',
            'page_break_catatan' => false,
            'ttd_kepsek_data_uri' => null,
        ], is_array($saved) ? $saved : []);
    }

    public function kelengkapan(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $siswaCounts = Siswa::where('aktif', true)
            ->whereNotNull('kelas')
            ->selectRaw('kelas, COUNT(*) as total')
            ->groupBy('kelas')
            ->pluck('total', 'kelas');

        $nilaiSemua = NilaiErapot::where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->whereNotNull('nilai_akhir')
            ->get(['kelas', 'siswa_id', 'mata_pelajaran']);

        $rombelMap = Rombel::whereIn('nama_rombel', $kelasList->all())
            ->get()
            ->keyBy('nama_rombel');

        $kelasStats = [];
        foreach ($kelasList as $k) {
            $jmlSiswa = (int) ($siswaCounts[$k] ?? 0);
            $nilaiKelas = $nilaiSemua->where('kelas', $k);
            $jmlMapel = $nilaiKelas->pluck('mata_pelajaran')->unique()->count();
            $expected = $jmlSiswa * $jmlMapel;
            $filled = $nilaiKelas->unique(fn ($n) => $n->siswa_id.'|'.$n->mata_pelajaran)->count();
            $persen = $expected > 0 ? round(($filled / $expected) * 100, 1) : 0.0;
            $kelasStats[$k] = [
                'siswa' => $jmlSiswa,
                'mapel' => $jmlMapel,
                'filled' => $filled,
                'expected' => $expected,
                'persen' => $persen,
                'terkunci' => (bool) ($rombelMap[$k]->kunci_input_rapor ?? false),
                'rombel_id' => $rombelMap[$k]->id ?? null,
            ];
        }

        $siswa = collect();
        $mapel = collect();
        $matriks = [];
        $rombelAktif = $kelas ? ($rombelMap[$kelas] ?? null) : null;

        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get(['id', 'nama_peserta_didik', 'nis', 'nisn']);
            $nilaiKelas = $nilaiSemua->where('kelas', $kelas);
            $mapel = $nilaiKelas->pluck('mata_pelajaran')->unique()->sort()->values();
            foreach ($nilaiKelas as $n) {
                $matriks[$n->siswa_id][$n->mata_pelajaran] = true;
            }
        }

        return view('admin.cetak.kelengkapan', compact(
            'kelasList', 'kelasStats', 'kelas', 'semester', 'tahunAjaran',
            'siswa', 'mapel', 'matriks', 'rombelAktif'
        ));
    }

    public function pengaturanRapor()
    {
        $sekolah = SekolahSettings::first();
        $raporConfig = self::ambilConfigRapor($sekolah);
        $ttdWaliMap = ($sekolah?->pengaturan ?? [])['ttd_wali_kelas'] ?? [];
        $waliKelasList = User::forSchool()->where('peran', 'guru')
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nama_pengguna']);

        return view('admin.cetak.pengaturan-rapor', compact('sekolah', 'raporConfig', 'ttdWaliMap', 'waliKelasList'));
    }

    public function simpanPengaturanRapor(Request $request)
    {
        $validated = $request->validate([
            'kementerian' => 'required|in:Kemendikdasmen,Kemendikbudristek',
            'ukuran_kertas' => 'required|in:A4,F4',
            'margin_atas_cm' => 'required|numeric|min:0.5|max:5',
            'kapitalisasi_nama' => 'required|in:asli,upper,title',
            'format_desimal' => 'required|in:0,2,auto',
            'page_break_catatan' => 'nullable|boolean',
            'ttd_kepsek' => 'nullable|image|mimes:png,jpg,jpeg|max:512',
            'hapus_ttd_kepsek' => 'nullable|boolean',
            'ttd_wali' => 'nullable|array',
            'ttd_wali.*' => 'nullable|image|mimes:png,jpg,jpeg|max:512',
            'hapus_ttd_wali' => 'nullable|array',
        ]);

        $tenantId = \App\Modules\Tenant\Contexts\TenantContext::id() ?? auth()->user()?->tenant_id;
        $defaultNama = ($tenantId !== null ? \App\Modules\Tenant\Models\Tenant::whereKey($tenantId)->value('nama_sekolah') : null)
            ?: config('app.name', 'SIMANTAP');
        $sekolah = SekolahSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'guru_id' => auth()->id(),
                'nama_sekolah' => $defaultNama,
            ]
        );
        $pengaturan = is_array($sekolah->pengaturan) ? $sekolah->pengaturan : [];
        $current = self::ambilConfigRapor($sekolah);

        $ttdDataUri = $current['ttd_kepsek_data_uri'] ?? null;
        if ($request->boolean('hapus_ttd_kepsek')) {
            $ttdDataUri = null;
        }
        if ($request->hasFile('ttd_kepsek')) {
            $file = $request->file('ttd_kepsek');
            $mime = $file->getMimeType() ?: 'image/png';
            $ttdDataUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));
        }

        $ttdWaliMap = is_array($pengaturan['ttd_wali_kelas'] ?? null) ? $pengaturan['ttd_wali_kelas'] : [];
        foreach (array_keys((array) $request->input('hapus_ttd_wali', [])) as $guruId) {
            unset($ttdWaliMap[(string) $guruId]);
        }
        foreach ((array) $request->file('ttd_wali', []) as $guruId => $file) {
            if ($file && $file->isValid()) {
                $mime = $file->getMimeType() ?: 'image/png';
                $ttdWaliMap[(string) $guruId] = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));
            }
        }

        $pengaturan['rapor'] = [
            'kementerian' => $validated['kementerian'],
            'ukuran_kertas' => $validated['ukuran_kertas'],
            'margin_atas_cm' => (float) $validated['margin_atas_cm'],
            'kapitalisasi_nama' => $validated['kapitalisasi_nama'],
            'format_desimal' => $validated['format_desimal'],
            'page_break_catatan' => $request->boolean('page_break_catatan'),
            'ttd_kepsek_data_uri' => $ttdDataUri,
        ];
        $pengaturan['ttd_wali_kelas'] = $ttdWaliMap;

        $sekolah->update(['pengaturan' => $pengaturan]);

        return redirect()->route('admin.cetak.pengaturan-rapor')
            ->with('success', 'Pengaturan & kustomisasi cetak rapor berhasil disimpan.');
    }

    public function showPts(Request $request, Siswa $siswa)
    {
        $validated = $request->validate([
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $nilai = NilaiErapot::where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('mata_pelajaran')
            ->get();
        $sekolah = SekolahSettings::first();
        $raporConfig = self::ambilConfigRapor($sekolah);
        $tampilanRapor = self::ambilTampilanRapor($sekolah);
        $hadir = $this->rekapKehadiran($siswa->id);
        $ttdWaliMap = ($sekolah?->pengaturan ?? [])['ttd_wali_kelas'] ?? [];
        $waliGuruId = $siswa->guru_id
            ?? ($siswa->kelas ? Rombel::where('nama_rombel', $siswa->kelas)->value('guru_id') : null);
        $ttdWaliUri = $waliGuruId ? ($ttdWaliMap[(string) $waliGuruId] ?? null) : null;

        return view('admin.cetak.pts-show', compact(
            'siswa', 'nilai', 'sekolah', 'raporConfig', 'tampilanRapor', 'hadir', 'semester', 'tahunAjaran', 'ttdWaliUri'
        ));
    }

    public static function ambilTampilanRapor(?SekolahSettings $sekolah): array
    {
        $saved = ($sekolah?->pengaturan ?? [])['tampilan_rapor'] ?? [];
        $saved = is_array($saved) ? $saved : [];

        $defaultKopBaris = [
            1 => ['bold' => true, 'size' => 12, 'color' => '#000000', 'text' => 'PEMERINTAH KOTA / KABUPATEN'],
            2 => ['bold' => true, 'size' => 13, 'color' => '#000000', 'text' => 'DINAS PENDIDIKAN'],
            3 => ['bold' => true, 'size' => 16, 'color' => '#000000', 'text' => strtoupper((string) ($sekolah?->nama_sekolah ?? config('app.name', 'SEKOLAH DASAR')))],
            4 => ['bold' => false, 'size' => 11, 'color' => '#000000', 'text' => trim((string) ($sekolah?->alamat ?? ''))],
            5 => ['bold' => false, 'size' => 11, 'color' => '#000000', 'text' => 'NPSN: '.($sekolah?->npsn ?? '-').($sekolah?->telepon ? ' • Telp: '.$sekolah->telepon : '')],
            6 => ['bold' => false, 'size' => 11, 'color' => '#000000', 'text' => ''],
        ];

        $savedBaris = is_array($saved['kop_baris'] ?? null) ? $saved['kop_baris'] : [];
        $kopBaris = [];
        for ($i = 1; $i <= 6; $i++) {
            $kopBaris[$i] = array_merge($defaultKopBaris[$i], is_array($savedBaris[$i] ?? null) ? $savedBaris[$i] : []);
        }

        $defaultPtsKolom = [];
        for ($k = 1; $k <= 12; $k++) {
            $defaultPtsKolom[(string) $k] = 5;
        }
        $savedPtsKolom = is_array($saved['pts_kolom_kelas'] ?? null) ? $saved['pts_kolom_kelas'] : [];
        $ptsKolom = [];
        foreach ($defaultPtsKolom as $k => $v) {
            $ptsKolom[$k] = max(1, min(12, (int) ($savedPtsKolom[$k] ?? $v)));
        }

        return [
            'terapkan_semester' => ! empty($saved) ? (bool) ($saved['terapkan_semester'] ?? false) : false,
            'terapkan_pts' => (bool) ($saved['terapkan_pts'] ?? false),
            'font_family' => (string) ($saved['font_family'] ?? 'Arial'),
            'size_judul_rapor' => (float) ($saved['size_judul_rapor'] ?? 18),
            'size_judul_tabel' => (float) ($saved['size_judul_tabel'] ?? 13),
            'size_isi_tabel' => (float) ($saved['size_isi_tabel'] ?? 12),
            'warna_template' => (string) ($saved['warna_template'] ?? 'default'),
            'warna_bg_header' => (string) ($saved['warna_bg_header'] ?? '#f0f0f0'),
            'warna_bg_cell' => (string) ($saved['warna_bg_cell'] ?? '#ffffff'),
            'table_opacity' => max(0, min(100, (int) ($saved['table_opacity'] ?? 0))),
            'warna_text_header' => (string) ($saved['warna_text_header'] ?? '#000000'),
            'warna_text_cell' => (string) ($saved['warna_text_cell'] ?? '#000000'),
            'warna_border' => (string) ($saved['warna_border'] ?? '#000000'),
            'show_watermark' => (bool) ($saved['show_watermark'] ?? false),
            'watermark_data_uri' => $saved['watermark_data_uri'] ?? null,
            'watermark_opacity' => max(0, min(100, (int) ($saved['watermark_opacity'] ?? 75))),
            'watermark_size' => max(10, min(150, (float) ($saved['watermark_size'] ?? 65))),
            'kop_preset' => (string) ($saved['kop_preset'] ?? 'set_1'),
            'kop_baris' => $kopBaris,
            'pts_kolom_kelas' => $ptsKolom,
            'pts_formatif_masuk' => (bool) ($saved['pts_formatif_masuk'] ?? false),
            'pts_ttd_kepsek' => array_key_exists('pts_ttd_kepsek', $saved) ? (bool) $saved['pts_ttd_kepsek'] : true,
            'pts_tabel_ekskul' => array_key_exists('pts_tabel_ekskul', $saved) ? (bool) $saved['pts_tabel_ekskul'] : true,
        ];
    }

    public function modifikasiTampilan()
    {
        $sekolah = SekolahSettings::first();
        $raporConfig = self::ambilConfigRapor($sekolah);
        $tampilan = self::ambilTampilanRapor($sekolah);
        $pengaturan = is_array($sekolah?->pengaturan) ? $sekolah->pengaturan : [];

        return view('admin.cetak.modifikasi-tampilan', compact('sekolah', 'raporConfig', 'tampilan', 'pengaturan'));
    }

    public function simpanModifikasiTampilan(Request $request)
    {
        $hexRule = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $validated = $request->validate([
            'terapkan_semester' => 'nullable|boolean',
            'terapkan_pts' => 'nullable|boolean',
            'font_family' => 'required|in:Arial,Calibri,Helvetica,Tahoma,Times New Roman,Verdana,Bookman Old Style',
            'size_judul_rapor' => 'required|numeric|min:10|max:36',
            'size_judul_tabel' => 'required|numeric|min:8|max:24',
            'size_isi_tabel' => 'required|numeric|min:8|max:22',
            'warna_template' => 'nullable|in:default,oranye,biru,hijau,custom',
            'warna_bg_header' => $hexRule,
            'warna_bg_cell' => $hexRule,
            'table_opacity' => 'required|integer|min:0|max:100',
            'warna_text_header' => $hexRule,
            'warna_text_cell' => $hexRule,
            'warna_border' => $hexRule,
            'show_watermark' => 'required|in:0,1',
            'file_watermark' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:1024',
            'hapus_watermark' => 'nullable|boolean',
            'watermark_opacity' => 'required|integer|min:0|max:100',
            'watermark_size' => 'required|numeric|min:10|max:150',
            'kop_preset' => 'required|in:tanpa_kop,set_1,set_2,set_3,set_4',
            'kop_baris' => 'nullable|array',
            'pts_kolom_kelas' => 'nullable|array',
            'pts_kolom_kelas.*' => 'nullable|integer|min:1|max:12',
            'pts_formatif_masuk' => 'required|in:0,1',
            'pts_ttd_kepsek' => 'required|in:0,1',
            'pts_tabel_ekskul' => 'required|in:0,1',
        ]);

        $tenantId = \App\Modules\Tenant\Contexts\TenantContext::id() ?? auth()->user()?->tenant_id;
        $defaultNama = ($tenantId !== null ? \App\Modules\Tenant\Models\Tenant::whereKey($tenantId)->value('nama_sekolah') : null)
            ?: config('app.name', 'SIMANTAP');
        $sekolah = SekolahSettings::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'guru_id' => auth()->id(),
                'nama_sekolah' => $defaultNama,
            ]
        );
        $pengaturan = is_array($sekolah->pengaturan) ? $sekolah->pengaturan : [];
        $current = self::ambilTampilanRapor($sekolah);

        $watermarkUri = $current['watermark_data_uri'] ?? null;
        if ($request->boolean('hapus_watermark')) {
            $watermarkUri = null;
        }
        if ($request->hasFile('file_watermark')) {
            $file = $request->file('file_watermark');
            $mime = $file->getMimeType() ?: 'image/png';
            $watermarkUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));
        }

        $kopBaris = [];
        $inputBaris = (array) $request->input('kop_baris', []);
        for ($i = 1; $i <= 6; $i++) {
            $row = is_array($inputBaris[$i] ?? null) ? $inputBaris[$i] : [];
            $color = (string) ($row['color'] ?? '#000000');
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = '#000000';
            }
            $kopBaris[$i] = [
                'bold' => ! empty($row['bold']),
                'size' => max(8, min(32, (int) ($row['size'] ?? 12))),
                'color' => $color,
                'text' => mb_substr(trim((string) ($row['text'] ?? '')), 0, 200),
            ];
        }

        $ptsKolom = [];
        $inputPts = (array) $request->input('pts_kolom_kelas', []);
        for ($k = 1; $k <= 12; $k++) {
            $ptsKolom[(string) $k] = max(1, min(12, (int) ($inputPts[(string) $k] ?? 5)));
        }

        $pengaturan['tampilan_rapor'] = [
            'terapkan_semester' => $request->boolean('terapkan_semester'),
            'terapkan_pts' => $request->boolean('terapkan_pts'),
            'font_family' => $validated['font_family'],
            'size_judul_rapor' => (float) $validated['size_judul_rapor'],
            'size_judul_tabel' => (float) $validated['size_judul_tabel'],
            'size_isi_tabel' => (float) $validated['size_isi_tabel'],
            'warna_template' => $validated['warna_template'] ?? 'custom',
            'warna_bg_header' => $validated['warna_bg_header'],
            'warna_bg_cell' => $validated['warna_bg_cell'],
            'table_opacity' => (int) $validated['table_opacity'],
            'warna_text_header' => $validated['warna_text_header'],
            'warna_text_cell' => $validated['warna_text_cell'],
            'warna_border' => $validated['warna_border'],
            'show_watermark' => $validated['show_watermark'] === '1',
            'watermark_data_uri' => $watermarkUri,
            'watermark_opacity' => (int) $validated['watermark_opacity'],
            'watermark_size' => (float) $validated['watermark_size'],
            'kop_preset' => $validated['kop_preset'],
            'kop_baris' => $kopBaris,
            'pts_kolom_kelas' => $ptsKolom,
            'pts_formatif_masuk' => $validated['pts_formatif_masuk'] === '1',
            'pts_ttd_kepsek' => $validated['pts_ttd_kepsek'] === '1',
            'pts_tabel_ekskul' => $validated['pts_tabel_ekskul'] === '1',
        ];

        $sekolah->update(['pengaturan' => $pengaturan]);

        return redirect()->route('admin.cetak.modifikasi-tampilan')
            ->with('success', 'Modifikasi tampilan rapor berhasil disimpan.');
    }

    public function previewModifikasiTampilan(Request $request)
    {
        $sekolah = SekolahSettings::first();
        $raporConfig = self::ambilConfigRapor($sekolah);
        $tampilanRapor = self::ambilTampilanRapor($sekolah);
        $jenis = $request->query('jenis', 'semester') === 'pts' ? 'pts' : 'semester';

        $siswa = Siswa::where('aktif', true)->orderBy('kelas')->orderBy('nama_peserta_didik')->first();

        return view('admin.cetak.modifikasi-tampilan-preview', compact('sekolah', 'raporConfig', 'tampilanRapor', 'jenis', 'siswa'));
    }

    public function showBukuInduk(Siswa $siswa)
    {
        $sekolah = SekolahSettings::first();
        $raporConfig = self::ambilConfigRapor($sekolah);
        $riwayatNilai = NilaiErapot::where('siswa_id', $siswa->id)
            ->orderBy('tahun_ajaran')
            ->orderBy('semester')
            ->orderBy('mata_pelajaran')
            ->get()
            ->groupBy(fn ($n) => $n->tahun_ajaran.' — Semester '.$n->semester);

        return view('admin.cetak.buku-induk-show', compact('siswa', 'sekolah', 'raporConfig', 'riwayatNilai'));
    }

    public function showP5bk(Request $request, Siswa $siswa)
    {
        $validated = $request->validate([
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $sekolah = SekolahSettings::first();
        $raporConfig = self::ambilConfigRapor($sekolah);
        $penilaian = P5Penilaian::where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('tema')
            ->orderBy('dimensi')
            ->get();

        return view('admin.cetak.p5bk-show', compact('siswa', 'sekolah', 'raporConfig', 'penilaian', 'semester', 'tahunAjaran'));
    }
}
