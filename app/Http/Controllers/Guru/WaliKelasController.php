<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Admin\CetakController;
use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Models\JadwalPelajaran;
use App\Models\KenaikanKelas;
use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\P5Penilaian;
use App\Models\PengaturanGuru;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\TemaKokurikuler;
use App\Models\TranskripNilai;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WaliKelasController extends Controller
{
    private function tahunDefault(): string
    {
        $tahun = (int) date('Y');

        return $tahun.'/'.($tahun + 1);
    }

    private function daftarKelasGuru(): Collection
    {
        $guruId = auth()->id();

        $kelasRombel = Rombel::where('guru_id', $guruId)->pluck('nama_rombel');
        $kelasSiswa = Siswa::where('guru_id', $guruId)->where('aktif', true)->whereNotNull('kelas')->distinct()->pluck('kelas');
        $kelasJadwal = JadwalPelajaran::where('guru_id', $guruId)->whereNotNull('kelas')->distinct()->pluck('kelas');
        $pengaturan = PengaturanGuru::where('guru_id', $guruId)->pluck('kelas');

        $gabung = $kelasRombel
            ->merge($kelasSiswa)
            ->merge($kelasJadwal)
            ->merge($pengaturan)
            ->filter(fn ($k) => ! empty($k))
            ->unique()
            ->sort()
            ->values();

        return $gabung;
    }

    private function siswaKelas(?string $kelas)
    {
        $kelasSah = $this->daftarKelasGuru();

        if (! $kelas || ! $kelasSah->contains($kelas)) {
            return Siswa::where('guru_id', auth()->id())
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get();
        }

        $rows = Siswa::where('kelas', $kelas)
            ->where('aktif', true)
            ->orderBy('nama_peserta_didik')
            ->get();

        if ($rows->isEmpty()) {
            $rows = Siswa::where('guru_id', auth()->id())
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get();
        }

        return $rows;
    }

    private function pastikanSiswaPerwalian(Siswa $siswa): void
    {
        $guruId = (int) auth()->id();
        $milikLangsung = (int) $siswa->guru_id === $guruId;
        $dalamKelasAmpu = $siswa->kelas !== null && $this->daftarKelasGuru()->contains($siswa->kelas);

        abort_unless($milikLangsung || $dalamKelasAmpu, 403, 'Siswa ini bukan anggota kelas perwalian Anda.');
    }

    // ==================== 1. TUJUAN PEMBELAJARAN (TP) ====================

    public function tujuanPembelajaran(Request $request)
    {
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::firstOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kelas' => '1.A',
                'mata_pelajaran' => 'Matematika',
                'kkm' => getKKM($guru->id),
                'semester' => 'Ganjil',
                'tahun_pelajaran' => $this->tahunDefault(),
            ]
        );

        $tpList = collect(($pengaturan->pengaturan ?? [])['tujuan_pembelajaran'] ?? []);
        $mapelFilter = $request->query('mapel');
        if ($mapelFilter) {
            $tpList = $tpList->where('mata_pelajaran', $mapelFilter)->values();
        }

        $mapelOptions = MataPelajaran::where('aktif', true)->orderBy('urutan')->orderBy('nama')->pluck('nama');
        if ($mapelOptions->isEmpty()) {
            $mapelOptions = collect(['Pendidikan Agama', 'Pendidikan Pancasila', 'Bahasa Indonesia', 'Matematika', 'IPAS', 'Seni dan Budaya', 'PJOK', 'Bahasa Inggris']);
        }

        return view('guru.tp.index', compact('pengaturan', 'tpList', 'mapelOptions', 'mapelFilter'));
    }

    public function simpanTujuanPembelajaran(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:30',
            'mata_pelajaran' => 'required|string|max:120',
            'fase' => 'required|in:Fase A,Fase B,Fase C',
            'semester' => 'required|in:Ganjil,Genap',
            'deskripsi' => 'required|string|max:300',
        ]);

        $guru = auth()->user();
        $pengaturan = PengaturanGuru::firstOrCreate(
            ['guru_id' => $guru->id],
            [
                'nama_guru' => $guru->nama_lengkap,
                'kkm' => getKKM($guru->id),
                'semester' => $validated['semester'],
                'tahun_pelajaran' => $this->tahunDefault(),
            ]
        );

        $cfg = is_array($pengaturan->pengaturan) ? $pengaturan->pengaturan : [];
        $items = is_array($cfg['tujuan_pembelajaran'] ?? null) ? $cfg['tujuan_pembelajaran'] : [];
        $items[] = [
            'id' => (string) Str::uuid(),
            'kode' => trim($validated['kode']),
            'mata_pelajaran' => trim($validated['mata_pelajaran']),
            'fase' => $validated['fase'],
            'semester' => $validated['semester'],
            'deskripsi' => trim($validated['deskripsi']),
            'created_at' => now()->toDateTimeString(),
        ];
        $cfg['tujuan_pembelajaran'] = array_values($items);
        $pengaturan->update(['pengaturan' => $cfg]);

        return redirect()->route('guru.tp.index')
            ->with('success', 'Butir Tujuan Pembelajaran (TP) berhasil disimpan.');
    }

    public function hapusTujuanPembelajaran(string $id)
    {
        $pengaturan = PengaturanGuru::where('guru_id', auth()->id())->first();
        if ($pengaturan && is_array($pengaturan->pengaturan)) {
            $cfg = $pengaturan->pengaturan;
            $items = is_array($cfg['tujuan_pembelajaran'] ?? null) ? $cfg['tujuan_pembelajaran'] : [];
            $cfg['tujuan_pembelajaran'] = array_values(array_filter($items, fn ($row) => ($row['id'] ?? '') !== $id));
            $pengaturan->update(['pengaturan' => $cfg]);
        }

        return redirect()->route('guru.tp.index')
            ->with('success', 'Butir Tujuan Pembelajaran berhasil dihapus.');
    }

    // ==================== 2. INPUT PROJEK P5 (GURU & WALAS) ====================

    public function p5Index(Request $request)
    {
        $kelasList = $this->daftarKelasGuru();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
            'tema' => 'nullable|string|max:150',
            'dimensi' => 'nullable|string|max:150',
        ]);

        $temaList = TemaKokurikuler::orderBy('nama')->pluck('nama')->unique()->values();
        if ($temaList->isEmpty()) {
            $temaList = collect(['Gaya Hidup Berkelanjutan', 'Kearifan Lokal', 'Bhinneka Tunggal Ika', 'Bangunlah Jiwa dan Raganya', 'Rekayasa dan Teknologi', 'Kewirausahaan']);
        }

        $dimensiList = collect([
            'Beriman, Bertakwa kepada Tuhan YME, dan Berakhlak Mulia',
            'Berkebinekaan Global',
            'Bergotong Royong',
            'Mandiri',
            'Bernalar Kritis',
            'Kreatif',
        ]);

        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();
        $tema = $validated['tema'] ?? $temaList->first();
        $dimensi = $validated['dimensi'] ?? $dimensiList->first();

        $siswa = $this->siswaKelas($kelas);
        $penilaian = $siswa->isEmpty()
            ? collect()
            : P5Penilaian::whereIn('siswa_id', $siswa->pluck('id'))
                ->where('tema', $tema)
                ->where('dimensi', $dimensi)
                ->where('semester', $semester)
                ->where('tahun_ajaran', $tahunAjaran)
                ->get()
                ->keyBy('siswa_id');

        return view('guru.p5.index', compact(
            'kelasList', 'temaList', 'dimensiList',
            'kelas', 'semester', 'tahunAjaran', 'tema', 'dimensi',
            'siswa', 'penilaian'
        ));
    }

    public function p5Simpan(Request $request)
    {
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string|max:9',
            'tema' => 'required|string|max:150',
            'dimensi' => 'required|string|max:150',
            'nilai' => 'required|array',
            'nilai.*.predikat' => 'required|in:BB,MB,BSH,SB',
            'nilai.*.catatan' => 'nullable|string|max:500',
        ]);

        if (Rombel::apakahTerkunciUntukKelas($validated['kelas'] ?? null)) {
            return back()->with('error', 'Input nilai rapor sedang dikunci oleh Admin Sekolah.');
        }

        $siswa = $this->siswaKelas($validated['kelas'] ?? null);
        $allowedSet = array_flip($siswa->pluck('id')->all());

        DB::transaction(function () use ($validated, $allowedSet) {
            foreach ($validated['nilai'] as $siswaId => $row) {
                $id = (int) $siswaId;
                if (! isset($allowedSet[$id])) {
                    continue;
                }
                P5Penilaian::updateOrCreate(
                    [
                        'siswa_id' => $id,
                        'tema' => $validated['tema'],
                        'dimensi' => $validated['dimensi'],
                        'semester' => $validated['semester'],
                        'tahun_ajaran' => $validated['tahun_ajaran'],
                    ],
                    [
                        'predikat' => $row['predikat'],
                        'catatan' => $row['catatan'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('guru.p5.index', [
            'kelas' => $validated['kelas'] ?? null,
            'semester' => $validated['semester'],
            'tahun_ajaran' => $validated['tahun_ajaran'],
            'tema' => $validated['tema'],
            'dimensi' => $validated['dimensi'],
        ])->with('success', 'Capaian & Catatan Proses Projek P5 berhasil disimpan.');
    }

    // ==================== 3. CEK PENILAIAN & DESKRIPSI CAPAIAN GURU ====================

    public function cekPenilaian(Request $request)
    {
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kelasList = $this->daftarKelasGuru();
        $kelas = $request->query('kelas', $pengaturan?->kelas ?? $kelasList->first());
        $semester = $request->query('semester', $pengaturan?->semester ?? 'Ganjil');
        $tahunAjaran = $request->query('tahun_ajaran', $pengaturan?->tahun_pelajaran ?? $this->tahunDefault());

        $siswa = $this->siswaKelas($kelas);
        $nilaiRows = $siswa->isEmpty()
            ? collect()
            : NilaiErapot::whereIn('siswa_id', $siswa->pluck('id'))
                ->where('semester', $semester)
                ->where('tahun_ajaran', $tahunAjaran)
                ->orderBy('mata_pelajaran')
                ->get();

        $mapelFilter = $request->query('mapel');
        $mapelList = $nilaiRows->pluck('mata_pelajaran')->filter()->unique()->values();
        if ($mapelList->isEmpty() && ! empty($pengaturan?->mata_pelajaran)) {
            $mapelList = collect([$pengaturan->mata_pelajaran]);
        }

        if ($mapelFilter) {
            $nilaiFiltered = $nilaiRows->where('mata_pelajaran', $mapelFilter)->keyBy('siswa_id');
        } else {
            $nilaiFiltered = $nilaiRows->keyBy('siswa_id');
        }

        return view('guru.cek-penilaian.index', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran',
            'mapelList', 'mapelFilter', 'siswa', 'nilaiFiltered', 'nilaiRows'
        ));
    }

    // ==================== 4. MENU WALI KELAS: INPUT NILAI EKSKUL ====================

    public function ekskul(Request $request)
    {
        $kelasList = $this->daftarKelasGuru();
        $kelas = $request->query('kelas', $kelasList->first());
        $siswa = $this->siswaKelas($kelas);
        $ekskulList = Ekstrakurikuler::where('aktif', true)->orderBy('nama')->pluck('nama');
        if ($ekskulList->isEmpty()) {
            $ekskulList = collect(['Pramuka', 'UKS / Dokter Kecil', 'Seni Tari & Musik', 'Olahraga / Futsal', 'Tahfidz / Keagamaan']);
        }

        return view('guru.walas.ekskul', compact('kelasList', 'kelas', 'siswa', 'ekskulList'));
    }

    public function simpanEkskul(Request $request)
    {
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'ekskul' => 'required|array',
            'ekskul.*.nama' => 'nullable|string|max:120',
            'ekskul.*.predikat' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'ekskul.*.keterangan' => 'nullable|string|max:300',
        ]);

        if (Rombel::apakahTerkunciUntukKelas($validated['kelas'] ?? null)) {
            return back()->with('error', 'Input nilai rapor sedang dikunci oleh Admin Sekolah.');
        }

        $siswaList = $this->siswaKelas($validated['kelas'] ?? null)->keyBy('id');

        DB::transaction(function () use ($validated, $siswaList) {
            foreach ($validated['ekskul'] as $siswaId => $row) {
                $siswa = $siswaList->get((int) $siswaId);
                if (! $siswa) {
                    continue;
                }
                $rekaman = is_array($siswa->rekaman) ? $siswa->rekaman : [];
                if (empty($row['nama'])) {
                    unset($rekaman['ekskul']);
                } else {
                    $rekaman['ekskul'] = [
                        'nama' => trim((string) $row['nama']),
                        'predikat' => $row['predikat'] ?? 'Baik',
                        'keterangan' => trim((string) ($row['keterangan'] ?? '')),
                        'updated_at' => now()->toDateTimeString(),
                    ];
                }
                $siswa->update(['rekaman' => $rekaman]);
            }
        });

        return redirect()->route('guru.walas.ekskul', ['kelas' => $validated['kelas'] ?? null])
            ->with('success', 'Nilai ekstrakurikuler siswa berhasil disimpan.');
    }

    // ==================== 5. MENU WALI KELAS: INPUT KENAIKAN KELAS ====================

    public function naikKelas(Request $request)
    {
        $kelasList = $this->daftarKelasGuru();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);

        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Genap';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $siswa = $this->siswaKelas($kelas);
        $keputusan = $siswa->isEmpty()
            ? collect()
            : KenaikanKelas::whereIn('siswa_id', $siswa->pluck('id'))
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('semester', $semester)
                ->get()
                ->keyBy('siswa_id');

        return view('guru.walas.naik-kelas', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran', 'siswa', 'keputusan'
        ));
    }

    public function simpanNaikKelas(Request $request)
    {
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string|max:9',
            'keputusan' => 'required|array',
            'keputusan.*.naik_kelas' => 'required|in:0,1',
            'keputusan.*.catatan' => 'nullable|string|max:255',
        ]);

        if (Rombel::apakahTerkunciUntukKelas($validated['kelas'] ?? null)) {
            return back()->with('error', 'Input nilai rapor sedang dikunci oleh Admin Sekolah.');
        }

        $siswa = $this->siswaKelas($validated['kelas'] ?? null);
        $allowedSet = array_flip($siswa->pluck('id')->all());

        DB::transaction(function () use ($validated, $allowedSet) {
            foreach ($validated['keputusan'] as $siswaId => $row) {
                $id = (int) $siswaId;
                if (! isset($allowedSet[$id])) {
                    continue;
                }
                KenaikanKelas::updateOrCreate(
                    [
                        'siswa_id' => $id,
                        'tahun_ajaran' => $validated['tahun_ajaran'],
                        'semester' => $validated['semester'],
                    ],
                    [
                        'naik_kelas' => $row['naik_kelas'] === '1',
                        'catatan' => $row['catatan'] ?? null,
                        'updated_by' => auth()->id(),
                    ]
                );
            }
        });

        return redirect()->route('guru.walas.naik-kelas', [
            'kelas' => $validated['kelas'] ?? null,
            'semester' => $validated['semester'],
            'tahun_ajaran' => $validated['tahun_ajaran'],
        ])->with('success', 'Keputusan kenaikan kelas / kelulusan berhasil disimpan.');
    }

    // ==================== 6. MENU WALI KELAS: STATUS PENILAIAN & STATISTIK KELAS ====================

    public function statusPenilaianKelas(Request $request)
    {
        $kelasList = $this->daftarKelasGuru();
        $kelas = $request->query('kelas', $kelasList->first());
        $semester = $request->query('semester', 'Ganjil');
        $tahunAjaran = $request->query('tahun_ajaran', $this->tahunDefault());

        $siswa = $this->siswaKelas($kelas);
        $siswaIds = $siswa->pluck('id');
        $jmlSiswa = $siswa->count();

        $nilai = $siswaIds->isEmpty()
            ? collect()
            : NilaiErapot::whereIn('siswa_id', $siswaIds)
                ->where('semester', $semester)
                ->where('tahun_ajaran', $tahunAjaran)
                ->whereNotNull('nilai_akhir')
                ->get();

        $mapelResmi = MataPelajaran::where('aktif', true)->orderBy('urutan')->orderBy('nama')->pluck('nama');
        $mapelDariNilai = $nilai->pluck('mata_pelajaran')->filter()->unique();
        $semuaMapel = $mapelResmi->merge($mapelDariNilai)->unique()->values();

        $rekapMapel = $semuaMapel->map(function ($namaMapel) use ($nilai, $jmlSiswa) {
            $baris = $nilai->where('mata_pelajaran', $namaMapel);
            $terisi = $baris->pluck('siswa_id')->unique()->count();

            return [
                'mata_pelajaran' => $namaMapel,
                'terisi' => $terisi,
                'total_siswa' => $jmlSiswa,
                'persen' => $jmlSiswa > 0 ? round($terisi / $jmlSiswa * 100, 1) : 0,
                'rata_rata' => $baris->count() ? round($baris->avg('nilai_akhir'), 2) : 0,
                'tertinggi' => $baris->count() ? round((float) $baris->max('nilai_akhir'), 2) : 0,
                'terendah' => $baris->count() ? round((float) $baris->min('nilai_akhir'), 2) : 0,
            ];
        });

        $sebaranPredikat = collect(['A', 'B', 'C', 'D'])->mapWithKeys(function ($p) use ($nilai) {
            return [$p => $nilai->where('predikat', $p)->count()];
        });

        $p5Rows = $siswaIds->isEmpty()
            ? collect()
            : P5Penilaian::whereIn('siswa_id', $siswaIds)
                ->where('semester', $semester)
                ->where('tahun_ajaran', $tahunAjaran)
                ->get();
        $sebaranP5 = collect(['SB', 'BSH', 'MB', 'BB'])->mapWithKeys(function ($p) use ($p5Rows) {
            return [$p => $p5Rows->where('predikat', $p)->count()];
        });

        return view('guru.walas.status-penilaian', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran',
            'siswa', 'rekapMapel', 'sebaranPredikat', 'sebaranP5'
        ));
    }

    // ==================== 7. MENU WALI KELAS: LEGER & CETAK RAPOR P5 ====================

    public function legerKelas(Request $request)
    {
        $kelasList = $this->daftarKelasGuru();
        $kelas = $request->query('kelas', $kelasList->first());
        $semester = $request->query('semester', 'Ganjil');
        $tahunAjaran = $request->query('tahun_ajaran', $this->tahunDefault());

        $siswa = $this->siswaKelas($kelas);
        $nilai = $siswa->isEmpty()
            ? collect()
            : NilaiErapot::whereIn('siswa_id', $siswa->pluck('id'))
                ->where('semester', $semester)
                ->where('tahun_ajaran', $tahunAjaran)
                ->whereNotNull('nilai_akhir')
                ->get(['siswa_id', 'mata_pelajaran', 'nilai_akhir']);

        $mapel = $nilai->pluck('mata_pelajaran')->unique()->sort()->values();
        $matriks = [];
        foreach ($nilai as $n) {
            $matriks[$n->siswa_id][$n->mata_pelajaran] = $n->nilai_akhir;
        }

        return view('guru.walas.leger', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran', 'siswa', 'mapel', 'matriks'
        ));
    }

    public function raporP5(Request $request, Siswa $siswa)
    {
        $this->pastikanSiswaPerwalian($siswa);

        $semester = $request->query('semester', 'Ganjil');
        $tahunAjaran = $request->query('tahun_ajaran', $this->tahunDefault());
        $sekolah = SekolahSettings::first();
        $raporConfig = CetakController::ambilConfigRapor($sekolah);
        $penilaian = P5Penilaian::where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('tema')
            ->orderBy('dimensi')
            ->get();

        return view('admin.cetak.p5bk-show', compact('siswa', 'sekolah', 'raporConfig', 'penilaian', 'semester', 'tahunAjaran'));
    }

    // ==================== 8. MENU WALI KELAS: TRANSKRIP IJAZAH ====================

    public function transkripIndex(Request $request)
    {
        $kelasList = $this->daftarKelasGuru();
        $kelas = $request->query('kelas', $kelasList->first());
        $siswa = $this->siswaKelas($kelas);
        $mapel = MataPelajaran::where('aktif', true)
            ->where('masuk_transkrip', true)
            ->orderBy('kelompok')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
        if ($mapel->isEmpty()) {
            $mapel = MataPelajaran::where('aktif', true)->orderBy('urutan')->orderBy('nama')->get();
        }

        $transkripMap = $siswa->isEmpty()
            ? collect()
            : TranskripNilai::whereIn('siswa_id', $siswa->pluck('id'))
                ->get()
                ->groupBy('siswa_id');

        return view('guru.walas.transkrip', compact('kelasList', 'kelas', 'siswa', 'mapel', 'transkripMap'));
    }

    public function transkripSimpan(Request $request, Siswa $siswa)
    {
        $this->pastikanSiswaPerwalian($siswa);

        $validated = $request->validate([
            'nomor_ijazah' => 'nullable|string|max:100|unique:siswa,nomor_ijazah,'.$siswa->id,
            'nilai' => 'nullable|array',
            'nilai.*' => 'nullable|numeric|min:0|max:100',
        ]);

        if (array_key_exists('nomor_ijazah', $validated)) {
            $siswa->update(['nomor_ijazah' => ($validated['nomor_ijazah'] ?? null) ?: null]);
        }

        $nilaiInput = $validated['nilai'] ?? [];
        DB::transaction(function () use ($nilaiInput, $siswa) {
            foreach ($nilaiInput as $mapelId => $mentah) {
                if ($mentah === null || $mentah === '') {
                    continue;
                }
                $mapel = MataPelajaran::find((int) $mapelId);
                if (! $mapel) {
                    continue;
                }
                $angka = round((float) $mentah, 2);
                TranskripNilai::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'mata_pelajaran_id' => $mapel->id],
                    [
                        'nama_mapel' => $mapel->nama,
                        'nilai_akhir' => $angka,
                        'predikat' => TranskripNilai::tentukanPredikat($angka),
                    ]
                );
            }
        });

        return back()->with('success', "Data transkrip ijazah {$siswa->nama_peserta_didik} berhasil disimpan.");
    }

    public function transkripCetak(Siswa $siswa)
    {
        $this->pastikanSiswaPerwalian($siswa);

        $nilai = TranskripNilai::where('siswa_id', $siswa->id)
            ->with('mapel')
            ->get()
            ->sortBy(fn ($n) => sprintf('%s-%05d-%s', $n->mapel->kelompok ?? 'ZZ', (int) ($n->mapel->urutan ?? 99999), $n->nama_mapel))
            ->values();
        $sekolah = SekolahSettings::first();
        $setting = ($sekolah && is_array($sekolah->pengaturan) && is_array($sekolah->pengaturan['transkrip'] ?? null))
            ? $sekolah->pengaturan['transkrip']
            : [];
        $rata = $nilai->count() ? round($nilai->avg('nilai_akhir'), 2) : null;

        return view('admin.transkrip.cetak-show', compact('siswa', 'nilai', 'sekolah', 'setting', 'rata'));
    }
}
