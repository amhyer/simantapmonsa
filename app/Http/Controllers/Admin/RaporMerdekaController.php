<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KenaikanKelas;
use App\Models\MataPelajaran;
use App\Models\P5Penilaian;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\SiswaBelajar;
use App\Models\TemaKokurikuler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RaporMerdekaController extends Controller
{
    public const DEFAULT_TEKNIK = [
        'Tes Tertulis',
        'Tes Lisan',
        'Portofolio',
        'Penugasan',
        'Praktik',
        'Proyek',
        'Produk',
    ];

    private function daftarKelas()
    {
        return Siswa::where('aktif', true)
            ->whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');
    }

    private function tahunDefault(): string
    {
        $tahun = (int) date('Y');

        return $tahun.'/'.($tahun + 1);
    }

    private function settings(): SekolahSettings
    {
        return SekolahSettings::firstOrCreate(
            [],
            ['nama_sekolah' => config('app.name', 'SIMANTAP')]
        );
    }

    // ---------- 1. Pengaturan Naik Kelas ----------

    public function naikKelas(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);

        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Genap';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $siswa = collect();
        $keputusan = [];
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get(['id', 'nama_peserta_didik', 'nis', 'nisn', 'kelas']);

            $keputusan = KenaikanKelas::whereIn('siswa_id', $siswa->pluck('id'))
                ->where('tahun_ajaran', $tahunAjaran)
                ->where('semester', $semester)
                ->get()
                ->keyBy('siswa_id');
        }

        return view('admin.rapor.naik-kelas', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran', 'siswa', 'keputusan'
        ));
    }

    public function simpanNaikKelas(Request $request)
    {
        $validated = $request->validate([
            'kelas' => 'required|string|max:50',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string|max:9',
            'keputusan' => 'required|array',
            'keputusan.*.naik_kelas' => 'required|in:0,1',
            'keputusan.*.catatan' => 'nullable|string|max:255',
        ]);

        $siswaIds = Siswa::where('kelas', $validated['kelas'])
            ->where('aktif', true)
            ->pluck('id')
            ->all();
        $allowedSet = array_flip($siswaIds);

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

        return redirect()->route('admin.rapor.naik-kelas', [
            'kelas' => $validated['kelas'],
            'semester' => $validated['semester'],
            'tahun_ajaran' => $validated['tahun_ajaran'],
        ])->with('success', 'Keputusan kenaikan kelas berhasil disimpan.');
    }

    // ---------- 2. Teknik Penilaian & Siswa Belajar ----------

    public function teknikPenilaian()
    {
        $sekolah = $this->settings();
        $teknik = ($sekolah->pengaturan ?? [])['teknik_penilaian'] ?? self::DEFAULT_TEKNIK;

        return view('admin.rapor.teknik-penilaian', compact('teknik'));
    }

    public function simpanTeknikPenilaian(Request $request)
    {
        $validated = $request->validate([
            'teknik' => 'required|array|min:1',
            'teknik.*' => 'nullable|string|max:80',
        ]);

        $cleaned = collect($validated['teknik'])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sekolah = $this->settings();
        $pengaturan = is_array($sekolah->pengaturan) ? $sekolah->pengaturan : [];
        $pengaturan['teknik_penilaian'] = $cleaned !== [] ? $cleaned : self::DEFAULT_TEKNIK;
        $sekolah->update(['pengaturan' => $pengaturan]);

        return redirect()->route('admin.rapor.teknik-penilaian')
            ->with('success', 'Daftar teknik penilaian berhasil diperbarui.');
    }

    public function siswaBelajar(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();

        $mapelList = MataPelajaran::orderBy('nama')->pluck('nama')->unique()->values();
        if ($mapelList->isEmpty()) {
            $mapelList = collect(['Pendidikan Agama', 'Bahasa Indonesia', 'Matematika', 'IPAS', 'Bahasa Inggris']);
        }

        $siswa = collect();
        $statusMap = [];
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get(['id', 'nama_peserta_didik', 'nisn']);

            $rows = SiswaBelajar::whereIn('siswa_id', $siswa->pluck('id'))->get();
            foreach ($rows as $r) {
                $statusMap[$r->siswa_id][$r->mata_pelajaran] = $r->aktif;
            }
        }

        return view('admin.rapor.siswa-belajar', compact(
            'kelasList', 'kelas', 'mapelList', 'siswa', 'statusMap'
        ));
    }

    public function simpanSiswaBelajar(Request $request)
    {
        $validated = $request->validate([
            'kelas' => 'required|string|max:50',
            'mapel_list' => 'required|array',
            'mapel_list.*' => 'string|max:120',
            'belajar' => 'nullable|array',
        ]);

        $siswaIds = Siswa::where('kelas', $validated['kelas'])
            ->where('aktif', true)
            ->pluck('id')
            ->all();

        $belajar = $validated['belajar'] ?? [];

        DB::transaction(function () use ($siswaIds, $validated, $belajar) {
            foreach ($siswaIds as $siswaId) {
                foreach ($validated['mapel_list'] as $mapel) {
                    $aktif = ! empty($belajar[$siswaId][$mapel]);
                    SiswaBelajar::updateOrCreate(
                        ['siswa_id' => $siswaId, 'mata_pelajaran' => $mapel],
                        ['aktif' => $aktif]
                    );
                }
            }
        });

        return redirect()->route('admin.rapor.siswa-belajar', ['kelas' => $validated['kelas']])
            ->with('success', 'Pemetaan siswa belajar berhasil disimpan.');
    }

    // ---------- 3. Penilaian P5BK ----------

    public function p5Index(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
            'tema' => 'nullable|string|max:150',
            'dimensi' => 'nullable|string|max:150',
        ]);

        $temaList = TemaKokurikuler::orderBy('nama')->pluck('nama')->unique()->values();
        if ($temaList->isEmpty()) {
            $temaList = collect(['Gaya Hidup Berkelanjutan', 'Kearifan Lokal', 'Bhinneka Tunggal Ika', 'Bangunlah Jiwa dan Raganya']);
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

        $siswa = collect();
        $penilaian = [];
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get(['id', 'nama_peserta_didik', 'nisn']);

            $penilaian = P5Penilaian::whereIn('siswa_id', $siswa->pluck('id'))
                ->where('tema', $tema)
                ->where('dimensi', $dimensi)
                ->where('semester', $semester)
                ->where('tahun_ajaran', $tahunAjaran)
                ->get()
                ->keyBy('siswa_id');
        }

        return view('admin.rapor.p5-index', compact(
            'kelasList', 'temaList', 'dimensiList',
            'kelas', 'semester', 'tahunAjaran', 'tema', 'dimensi',
            'siswa', 'penilaian'
        ));
    }

    public function p5Simpan(Request $request)
    {
        $validated = $request->validate([
            'kelas' => 'required|string|max:50',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string|max:9',
            'tema' => 'required|string|max:150',
            'dimensi' => 'required|string|max:150',
            'nilai' => 'required|array',
            'nilai.*.predikat' => 'required|in:BB,MB,BSH,SB',
            'nilai.*.catatan' => 'nullable|string|max:500',
        ]);

        $siswaIds = Siswa::where('kelas', $validated['kelas'])
            ->where('aktif', true)
            ->pluck('id')
            ->all();
        $allowedSet = array_flip($siswaIds);

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

        return redirect()->route('admin.rapor.p5.index', [
            'kelas' => $validated['kelas'],
            'semester' => $validated['semester'],
            'tahun_ajaran' => $validated['tahun_ajaran'],
            'tema' => $validated['tema'],
            'dimensi' => $validated['dimensi'],
        ])->with('success', 'Penilaian P5BK berhasil disimpan.');
    }

    // ---------- 4. Forum Diskusi & Pengumuman Beranda ----------

    public function simpanForum(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:120',
            'pesan' => 'required|string|max:600',
        ]);

        $sekolah = $this->settings();
        $pengaturan = is_array($sekolah->pengaturan) ? $sekolah->pengaturan : [];
        $posts = is_array($pengaturan['forum_pengumuman'] ?? null) ? $pengaturan['forum_pengumuman'] : [];

        array_unshift($posts, [
            'id' => (string) Str::uuid(),
            'judul' => $validated['judul'],
            'pesan' => $validated['pesan'],
            'penulis' => auth()->user()->nama_lengkap ?? 'Operator Sekolah',
            'created_at' => now()->format('d M Y, H:i'),
        ]);

        $pengaturan['forum_pengumuman'] = array_slice($posts, 0, 30);
        $sekolah->update(['pengaturan' => $pengaturan]);

        return back()->with('success', 'Pengumuman / pesan forum berhasil dipublikasikan.');
    }

    public function hapusForum(string $postId)
    {
        $sekolah = $this->settings();
        $pengaturan = is_array($sekolah->pengaturan) ? $sekolah->pengaturan : [];
        $posts = is_array($pengaturan['forum_pengumuman'] ?? null) ? $pengaturan['forum_pengumuman'] : [];

        $pengaturan['forum_pengumuman'] = array_values(array_filter(
            $posts,
            fn ($item) => ($item['id'] ?? '') !== $postId
        ));
        $sekolah->update(['pengaturan' => $pengaturan]);

        return back()->with('success', 'Pesan forum berhasil dihapus.');
    }
}
