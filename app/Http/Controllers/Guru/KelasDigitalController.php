<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Elearning\KelasDigital;
use App\Models\Elearning\KelasDigitalMateriItem;
use App\Models\Elearning\KelasDigitalModul;
use App\Models\Elearning\KelasDigitalPengumpulan;
use App\Models\Elearning\KelasDigitalPertemuan;
use App\Models\Elearning\KelasDigitalTugas;
use App\Models\Kuis;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Rombel;
use App\Models\Semester;
use App\Services\Elearning\ElearningFileService;
use App\Services\Elearning\KelasDigitalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KelasDigitalController extends Controller
{
    public function __construct(
        private readonly KelasDigitalAccessService $access,
        private readonly ElearningFileService $files,
    ) {}

    public function index()
    {
        $guru = auth()->user();

        $kelasList = KelasDigital::query()
            ->where('guru_id', $guru->id)
            ->with('rombel')
            ->withCount(['modul', 'tugas'])
            ->orderByDesc('aktif')
            ->orderBy('kelas')
            ->orderBy('mata_pelajaran')
            ->get();

        return view('guru.kelas-digital.index', compact('kelasList'));
    }

    public function create()
    {
        $guru = auth()->user();
        $rombelList = $this->access->rombelOptionsForGuru($guru);
        $mapelOptions = MataPelajaran::where('aktif', true)->orderBy('urutan')->orderBy('nama')->pluck('nama');

        return view('guru.kelas-digital.create', compact('rombelList', 'mapelOptions'));
    }

    public function store(Request $request)
    {
        $guru = auth()->user();

        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:180',
            'mata_pelajaran' => 'required|string|max:120',
            'rombel_id' => 'nullable|integer|exists:rombel,id',
            'kelas' => 'nullable|string|max:60|required_without:rombel_id',
            'deskripsi' => 'nullable|string|max:2000',
            'aktif' => 'nullable|boolean',
        ]);

        $rombel = null;
        if (! empty($validated['rombel_id'])) {
            $rombel = Rombel::query()
                ->when($guru->tenant_id !== null, fn ($q) => $q->where('tenant_id', $guru->tenant_id))
                ->findOrFail((int) $validated['rombel_id']);
        }

        $namaKelasRombel = trim((string) ($validated['kelas'] ?? ''));
        if ($namaKelasRombel === '' && $rombel) {
            $namaKelasRombel = $rombel->nama_rombel;
        }

        $semesterId = Semester::orderByDesc('semester_id')->value('id');

        $kelasDigital = KelasDigital::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $guru->tenant_id,
            'guru_id' => $guru->id,
            'rombel_id' => $rombel?->id,
            'semester_id' => $semesterId,
            'nama_kelas' => trim($validated['nama_kelas']),
            'mata_pelajaran' => trim($validated['mata_pelajaran']),
            'kelas' => $namaKelasRombel,
            'deskripsi' => $validated['deskripsi'] ?? null,
            'aktif' => $request->boolean('aktif', true),
        ]);

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Kelas Digital berhasil dibuat. Silakan susun modul dan pertemuan.');
    }

    public function show(KelasDigital $kelasDigital)
    {
        $guru = auth()->user();
        $this->access->authorizeGuruKelas($guru, $kelasDigital);

        $kelasDigital->load([
            'rombel',
            'modul.pertemuan.items',
            'modul.pertemuan.materi',
            'modul.pertemuan.kuis',
            'modul.pertemuan.tugas' => fn ($q) => $q->withCount('pengumpulan'),
        ]);

        $pertemuanIds = $kelasDigital->modul
            ->flatMap(fn (KelasDigitalModul $m) => $m->pertemuan->pluck('id'))
            ->values()
            ->all();

        $peserta = $this->access->pesertaKelas($kelasDigital);
        $progressPerSiswa = $this->access->progressPerSiswa($peserta, $pertemuanIds);

        $materiOptions = Materi::query()
            ->where('guru_id', $guru->id)
            ->orderByDesc('tanggal')
            ->limit(100)
            ->get(['id', 'judul', 'mata_pelajaran', 'kelas']);

        $kuisOptions = Kuis::query()
            ->where('guru_id', $guru->id)
            ->orderByDesc('tanggal')
            ->limit(100)
            ->get(['id', 'judul', 'mata_pelajaran', 'kelas', 'aktif']);

        return view('guru.kelas-digital.show', compact(
            'kelasDigital',
            'peserta',
            'progressPerSiswa',
            'materiOptions',
            'kuisOptions'
        ));
    }

    public function edit(KelasDigital $kelasDigital)
    {
        $guru = auth()->user();
        $this->access->authorizeGuruKelas($guru, $kelasDigital);

        $rombelList = $this->access->rombelOptionsForGuru($guru);
        $mapelOptions = MataPelajaran::where('aktif', true)->orderBy('urutan')->orderBy('nama')->pluck('nama');

        return view('guru.kelas-digital.edit', compact('kelasDigital', 'rombelList', 'mapelOptions'));
    }

    public function update(Request $request, KelasDigital $kelasDigital)
    {
        $guru = auth()->user();
        $this->access->authorizeGuruKelas($guru, $kelasDigital);

        $validated = $request->validate([
            'nama_kelas' => 'required|string|max:180',
            'mata_pelajaran' => 'required|string|max:120',
            'rombel_id' => 'nullable|integer|exists:rombel,id',
            'kelas' => 'nullable|string|max:60|required_without:rombel_id',
            'deskripsi' => 'nullable|string|max:2000',
            'aktif' => 'nullable|boolean',
        ]);

        $rombel = null;
        if (! empty($validated['rombel_id'])) {
            $rombel = Rombel::query()
                ->when($guru->tenant_id !== null, fn ($q) => $q->where('tenant_id', $guru->tenant_id))
                ->findOrFail((int) $validated['rombel_id']);
        }

        $namaKelasRombel = trim((string) ($validated['kelas'] ?? ''));
        if ($namaKelasRombel === '' && $rombel) {
            $namaKelasRombel = $rombel->nama_rombel;
        }

        $kelasDigital->update([
            'rombel_id' => $rombel?->id,
            'nama_kelas' => trim($validated['nama_kelas']),
            'mata_pelajaran' => trim($validated['mata_pelajaran']),
            'kelas' => $namaKelasRombel,
            'deskripsi' => $validated['deskripsi'] ?? null,
            'aktif' => $request->boolean('aktif', false),
        ]);

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Pengaturan Kelas Digital berhasil diperbarui.');
    }

    public function destroy(KelasDigital $kelasDigital)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);

        $kelasDigital->delete();

        return redirect()
            ->route('guru.kelas-digital.index')
            ->with('success', 'Kelas Digital berhasil dihapus.');
    }

    // ==================== MODUL / BAB ====================

    public function storeModul(Request $request, KelasDigital $kelasDigital)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);

        $validated = $request->validate([
            'judul' => 'required|string|max:200',
            'deskripsi' => 'nullable|string|max:2000',
            'urutan' => 'nullable|integer|min:1|max:999',
            'tujuan_pembelajaran' => 'nullable|string|max:3000',
            'pm' => 'nullable|array',
            'pm.*' => 'in:berkesadaran,bermakna,menggembirakan',
            'dimensi' => 'nullable|array',
            'dimensi.*' => 'integer|min:0|max:7',
            'pengalaman_memahami' => 'nullable|string|max:1500',
            'pengalaman_mengaplikasi' => 'nullable|string|max:1500',
            'pengalaman_merefleksi' => 'nullable|string|max:1500',
            'dipublikasikan' => 'nullable|boolean',
        ]);

        $pmFlags = [];
        foreach (($validated['pm'] ?? []) as $key) {
            $pmFlags[$key] = true;
        }

        $pengalaman = array_filter([
            'memahami' => trim((string) ($validated['pengalaman_memahami'] ?? '')),
            'mengaplikasi' => trim((string) ($validated['pengalaman_mengaplikasi'] ?? '')),
            'merefleksi' => trim((string) ($validated['pengalaman_merefleksi'] ?? '')),
        ], fn (string $v) => $v !== '');

        $nextUrutan = ((int) $kelasDigital->modul()->max('urutan')) + 1;

        KelasDigitalModul::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $kelasDigital->tenant_id,
            'kelas_digital_id' => $kelasDigital->id,
            'judul' => trim($validated['judul']),
            'deskripsi' => $validated['deskripsi'] ?? null,
            'urutan' => $validated['urutan'] ?? $nextUrutan,
            'tujuan_pembelajaran' => $validated['tujuan_pembelajaran'] ?? null,
            'pm' => $pmFlags,
            'dimensi' => array_values(array_map('intval', $validated['dimensi'] ?? [])),
            'pengalaman' => $pengalaman,
            'dipublikasikan' => $request->boolean('dipublikasikan', true),
        ]);

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Modul/Bab berhasil ditambahkan.');
    }

    public function destroyModul(KelasDigital $kelasDigital, KelasDigitalModul $modul)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        abort_unless((int) $modul->kelas_digital_id === (int) $kelasDigital->id, 404);

        $modul->delete();

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Modul/Bab berhasil dihapus.');
    }

    // ==================== PERTEMUAN ====================

    public function storePertemuan(Request $request, KelasDigital $kelasDigital, KelasDigitalModul $modul)
    {
        $guru = auth()->user();
        $this->access->authorizeGuruKelas($guru, $kelasDigital);
        abort_unless((int) $modul->kelas_digital_id === (int) $kelasDigital->id, 404);

        $validated = $request->validate([
            'judul' => 'required|string|max:200',
            'deskripsi' => 'nullable|string|max:2000',
            'tanggal' => 'nullable|date',
            'urutan' => 'nullable|integer|min:1|max:999',
            'materi_id' => 'nullable|integer|exists:materi,id',
            'kuis_id' => 'nullable|integer|exists:kuis,id',
            'dipublikasikan' => 'nullable|boolean',
        ]);

        $materiId = null;
        if (! empty($validated['materi_id'])) {
            $materi = Materi::where('guru_id', $guru->id)->findOrFail((int) $validated['materi_id']);
            $materiId = $materi->id;
        }

        $kuisId = null;
        if (! empty($validated['kuis_id'])) {
            $kuis = Kuis::where('guru_id', $guru->id)->findOrFail((int) $validated['kuis_id']);
            $kuisId = $kuis->id;
        }

        $nextUrutan = ((int) $modul->pertemuan()->max('urutan')) + 1;

        KelasDigitalPertemuan::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $kelasDigital->tenant_id,
            'modul_id' => $modul->id,
            'materi_id' => $materiId,
            'kuis_id' => $kuisId,
            'judul' => trim($validated['judul']),
            'deskripsi' => $validated['deskripsi'] ?? null,
            'tanggal' => $validated['tanggal'] ?? now()->toDateString(),
            'urutan' => $validated['urutan'] ?? $nextUrutan,
            'dipublikasikan' => $request->boolean('dipublikasikan', true),
        ]);

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Pertemuan berhasil ditambahkan.');
    }

    public function destroyPertemuan(KelasDigital $kelasDigital, KelasDigitalPertemuan $pertemuan)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        $pertemuan->loadMissing('modul');
        abort_unless((int) $pertemuan->modul?->kelas_digital_id === (int) $kelasDigital->id, 404);

        $pertemuan->delete();

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Pertemuan berhasil dihapus.');
    }

    // ==================== BUTIR MATERI (TEKS / VIDEO / FILE / LINK) ====================

    public function storeItem(Request $request, KelasDigital $kelasDigital, KelasDigitalPertemuan $pertemuan)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        $pertemuan->loadMissing('modul');
        abort_unless((int) $pertemuan->modul?->kelas_digital_id === (int) $kelasDigital->id, 404);

        $validated = $request->validate([
            'tipe' => 'required|in:teks,video,file,link',
            'judul' => 'required|string|max:200',
            'konten' => 'nullable|string|max:20000|required_if:tipe,teks',
            'tautan' => 'nullable|url|max:1000|required_if:tipe,video|required_if:tipe,link',
            'file' => array_merge(ElearningFileService::validationRuleGuru(), ['required_if:tipe,file']),
        ]);

        $fileMeta = null;
        if ($request->hasFile('file')) {
            $fileMeta = $this->files->store($request->file('file'), 'materi', $kelasDigital->tenant_id, 'file');
        }

        $nextUrutan = ((int) $pertemuan->items()->max('urutan')) + 1;

        KelasDigitalMateriItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $kelasDigital->tenant_id,
            'pertemuan_id' => $pertemuan->id,
            'tipe' => $validated['tipe'],
            'judul' => trim($validated['judul']),
            'konten' => $validated['konten'] ?? null,
            'tautan' => $validated['tautan'] ?? null,
            'file_path' => $fileMeta['path'] ?? null,
            'file_nama_asli' => $fileMeta['original_name'] ?? null,
            'file_mime' => $fileMeta['mime'] ?? null,
            'file_ukuran' => $fileMeta['size'] ?? null,
            'urutan' => $nextUrutan,
        ]);

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Konten materi berhasil ditambahkan ke pertemuan.');
    }

    public function destroyItem(KelasDigital $kelasDigital, KelasDigitalMateriItem $item)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        $item->loadMissing('pertemuan.modul');
        abort_unless((int) $item->pertemuan?->modul?->kelas_digital_id === (int) $kelasDigital->id, 404);

        $this->files->deleteQuietly($item->file_path);
        $item->delete();

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Konten materi berhasil dihapus.');
    }

    public function downloadItem(KelasDigital $kelasDigital, KelasDigitalMateriItem $item)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        $item->loadMissing('pertemuan.modul');
        abort_unless((int) $item->pertemuan?->modul?->kelas_digital_id === (int) $kelasDigital->id, 404);

        return $this->files->download($item->file_path, $item->file_nama_asli);
    }

    // ==================== TUGAS & PENILAIAN ====================

    public function storeTugas(Request $request, KelasDigital $kelasDigital, KelasDigitalPertemuan $pertemuan)
    {
        $guru = auth()->user();
        $this->access->authorizeGuruKelas($guru, $kelasDigital);
        $pertemuan->loadMissing('modul');
        abort_unless((int) $pertemuan->modul?->kelas_digital_id === (int) $kelasDigital->id, 404);

        $validated = $request->validate([
            'judul' => 'required|string|max:200',
            'instruksi' => 'required|string|max:10000',
            'tipe_pengumpulan' => 'required|in:teks,file,keduanya',
            'batas_waktu' => 'nullable|date',
            'nilai_maksimal' => 'nullable|integer|min:1|max:100',
            'sinkron_ke_nilai' => 'nullable|boolean',
            'lampiran' => ElearningFileService::validationRuleGuru(),
        ]);

        $fileMeta = null;
        if ($request->hasFile('lampiran')) {
            $fileMeta = $this->files->store($request->file('lampiran'), 'tugas', $kelasDigital->tenant_id, 'lampiran');
        }

        KelasDigitalTugas::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $kelasDigital->tenant_id,
            'pertemuan_id' => $pertemuan->id,
            'kelas_digital_id' => $kelasDigital->id,
            'guru_id' => $guru->id,
            'judul' => trim($validated['judul']),
            'instruksi' => trim($validated['instruksi']),
            'tipe_pengumpulan' => $validated['tipe_pengumpulan'],
            'batas_waktu' => $validated['batas_waktu'] ?? null,
            'nilai_maksimal' => (int) ($validated['nilai_maksimal'] ?? 100),
            'lampiran_path' => $fileMeta['path'] ?? null,
            'lampiran_nama' => $fileMeta['original_name'] ?? null,
            'lampiran_mime' => $fileMeta['mime'] ?? null,
            'lampiran_ukuran' => $fileMeta['size'] ?? null,
            'sinkron_ke_nilai' => $request->boolean('sinkron_ke_nilai', true),
        ]);

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Tugas berhasil ditambahkan ke pertemuan.');
    }

    public function destroyTugas(KelasDigital $kelasDigital, KelasDigitalTugas $tugas)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        abort_unless((int) $tugas->kelas_digital_id === (int) $kelasDigital->id, 404);

        $this->files->deleteQuietly($tugas->lampiran_path);
        $tugas->delete();

        return redirect()
            ->route('guru.kelas-digital.show', $kelasDigital)
            ->with('success', 'Tugas berhasil dihapus.');
    }

    public function downloadLampiranTugas(KelasDigital $kelasDigital, KelasDigitalTugas $tugas)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        abort_unless((int) $tugas->kelas_digital_id === (int) $kelasDigital->id, 404);

        return $this->files->download($tugas->lampiran_path, $tugas->lampiran_nama);
    }

    public function daftarPengumpulan(KelasDigital $kelasDigital, KelasDigitalTugas $tugas)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        abort_unless((int) $tugas->kelas_digital_id === (int) $kelasDigital->id, 404);

        $tugas->loadMissing('pertemuan.modul');
        $peserta = $this->access->pesertaKelas($kelasDigital);

        $pengumpulanList = KelasDigitalPengumpulan::query()
            ->where('tugas_id', $tugas->id)
            ->with(['siswa', 'nilaiRecord'])
            ->get();

        $pengumpulanMap = $pengumpulanList->keyBy('siswa_id');

        // Sertakan juga pengumpulan siswa yang mungkin tidak masuk filter kelas saat ini agar tidak hilang.
        $pesertaIds = array_flip($peserta->pluck('id')->all());
        $pengumpulanTambahan = $pengumpulanList->filter(fn ($p) => ! isset($pesertaIds[$p->siswa_id]));

        return view('guru.kelas-digital.pengumpulan', compact(
            'kelasDigital',
            'tugas',
            'peserta',
            'pengumpulanMap',
            'pengumpulanTambahan'
        ));
    }

    public function nilaiPengumpulan(
        Request $request,
        KelasDigital $kelasDigital,
        KelasDigitalTugas $tugas,
        KelasDigitalPengumpulan $pengumpulan,
    ) {
        $guru = auth()->user();
        $this->access->authorizeGuruKelas($guru, $kelasDigital);
        abort_unless((int) $tugas->kelas_digital_id === (int) $kelasDigital->id, 404);
        abort_unless((int) $pengumpulan->tugas_id === (int) $tugas->id, 404);

        $maks = max(1, (int) $tugas->nilai_maksimal);

        $validated = $request->validate([
            'nilai' => 'required|numeric|min:0|max:'.$maks,
            'catatan_guru' => 'nullable|string|max:2000',
            'status' => 'nullable|in:dinilai,revisi',
        ]);

        $pengumpulan->loadMissing('siswa');
        $siswa = $pengumpulan->siswa;
        abort_unless($siswa !== null, 404);

        $nilaiAngka = round((float) $validated['nilai'], 2);
        $nilaiSkala100 = $maks === 100 ? $nilaiAngka : round(($nilaiAngka / $maks) * 100, 2);
        $statusBaru = $validated['status'] ?? 'dinilai';

        DB::transaction(function () use ($pengumpulan, $tugas, $kelasDigital, $guru, $siswa, $nilaiAngka, $nilaiSkala100, $validated, $statusBaru): void {
            $nilaiRecord = null;
            if ($statusBaru === 'dinilai' && $tugas->sinkron_ke_nilai) {
                $nilaiRecord = $this->access->syncNilaiKeDaftarNilai(
                    $pengumpulan,
                    $tugas,
                    $kelasDigital,
                    $guru,
                    $siswa,
                    $nilaiSkala100
                );
            }

            $pengumpulan->update([
                'nilai' => $nilaiAngka,
                'catatan_guru' => $validated['catatan_guru'] ?? null,
                'status' => $statusBaru,
                'dinilai_oleh' => $guru->id,
                'dinilai_pada' => now(),
                'nilai_id' => $nilaiRecord?->id ?? $pengumpulan->nilai_id,
            ]);
        });

        $pesan = $tugas->sinkron_ke_nilai && $statusBaru === 'dinilai'
            ? "Nilai {$siswa->nama_peserta_didik} tersimpan dan disinkronkan ke Daftar Nilai."
            : "Penilaian {$siswa->nama_peserta_didik} berhasil disimpan.";

        return redirect()
            ->route('guru.kelas-digital.tugas.pengumpulan', [$kelasDigital, $tugas])
            ->with('success', $pesan);
    }

    public function downloadPengumpulan(KelasDigital $kelasDigital, KelasDigitalPengumpulan $pengumpulan)
    {
        $this->access->authorizeGuruKelas(auth()->user(), $kelasDigital);
        $pengumpulan->loadMissing('tugas');
        abort_unless((int) $pengumpulan->tugas?->kelas_digital_id === (int) $kelasDigital->id, 404);

        return $this->files->download($pengumpulan->file_path, $pengumpulan->file_nama_asli);
    }
}
