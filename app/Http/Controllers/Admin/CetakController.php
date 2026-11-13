<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catatan;
use App\Models\Kehadiran;
use App\Models\NilaiErapot;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\TanggalRapor;
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

        return $tahun . '/' . ($tahun + 1);
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
        ]);
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $nilai = NilaiErapot::where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('mata_pelajaran')
            ->get();
        $sekolah = SekolahSettings::first();
        $tanggalRapor = TanggalRapor::where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->first();
        $hadir = $this->rekapKehadiran($siswa->id);
        $catatan = Catatan::where('siswa_id', $siswa->id)->latest('tanggal')->first(['catatan']);
        $rata = $nilai->whereNotNull('nilai_akhir')->count()
            ? round($nilai->whereNotNull('nilai_akhir')->avg('nilai_akhir'), 2)
            : null;
        $raporConfig = self::ambilConfigRapor($sekolah);
        $ttdWaliMap = ($sekolah?->pengaturan ?? [])['ttd_wali_kelas'] ?? [];
        $waliGuruId = $siswa->guru_id
            ?? ($siswa->kelas ? \App\Models\Rombel::where('nama_rombel', $siswa->kelas)->value('guru_id') : null);
        $ttdWaliUri = $waliGuruId ? ($ttdWaliMap[(string) $waliGuruId] ?? null) : null;
        $naikKelas = $semester === 'Genap'
            ? \App\Models\KenaikanKelas::where('siswa_id', $siswa->id)
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('semester', $semester)
                ->first()
            : null;

        return view('admin.cetak.nilai-show', compact(
            'siswa', 'nilai', 'sekolah', 'tanggalRapor', 'hadir', 'catatan',
            'rata', 'semester', 'tahunAjaran', 'raporConfig', 'ttdWaliUri', 'naikKelas'
        ));
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

        $rombelMap = \App\Models\Rombel::whereIn('nama_rombel', $kelasList->all())
            ->get()
            ->keyBy('nama_rombel');

        $kelasStats = [];
        foreach ($kelasList as $k) {
            $jmlSiswa = (int) ($siswaCounts[$k] ?? 0);
            $nilaiKelas = $nilaiSemua->where('kelas', $k);
            $jmlMapel = $nilaiKelas->pluck('mata_pelajaran')->unique()->count();
            $expected = $jmlSiswa * $jmlMapel;
            $filled = $nilaiKelas->unique(fn ($n) => $n->siswa_id . '|' . $n->mata_pelajaran)->count();
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
        $waliKelasList = \App\Models\User::where('peran', 'guru')
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

        $sekolah = SekolahSettings::firstOrCreate(
            [],
            ['nama_sekolah' => config('app.name', 'SIMANTAP')]
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
            $ttdDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
        }

        $ttdWaliMap = is_array($pengaturan['ttd_wali_kelas'] ?? null) ? $pengaturan['ttd_wali_kelas'] : [];
        foreach (array_keys((array) $request->input('hapus_ttd_wali', [])) as $guruId) {
            unset($ttdWaliMap[(string) $guruId]);
        }
        foreach ((array) $request->file('ttd_wali', []) as $guruId => $file) {
            if ($file && $file->isValid()) {
                $mime = $file->getMimeType() ?: 'image/png';
                $ttdWaliMap[(string) $guruId] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
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
        $hadir = $this->rekapKehadiran($siswa->id);
        $ttdWaliMap = ($sekolah?->pengaturan ?? [])['ttd_wali_kelas'] ?? [];
        $waliGuruId = $siswa->guru_id
            ?? ($siswa->kelas ? \App\Models\Rombel::where('nama_rombel', $siswa->kelas)->value('guru_id') : null);
        $ttdWaliUri = $waliGuruId ? ($ttdWaliMap[(string) $waliGuruId] ?? null) : null;

        return view('admin.cetak.pts-show', compact(
            'siswa', 'nilai', 'sekolah', 'raporConfig', 'hadir', 'semester', 'tahunAjaran', 'ttdWaliUri'
        ));
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
            ->groupBy(fn ($n) => $n->tahun_ajaran . ' — Semester ' . $n->semester);

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
        $penilaian = \App\Models\P5Penilaian::where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('tema')
            ->orderBy('dimensi')
            ->get();

        return view('admin.cetak.p5bk-show', compact('siswa', 'sekolah', 'raporConfig', 'penilaian', 'semester', 'tahunAjaran'));
    }
}
