<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\PengaturanGuru;
use App\Models\Siswa;
use App\Models\Tka\AdaptiveSimulation;
use App\Models\Tka\EraporSyncLog;
use App\Models\Tka\LearningRecommendation;
use App\Services\Tka\TkaCognitiveAnalyticsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TkaInovasiController extends Controller
{
    public function kognitifIndex(Request $request, TkaCognitiveAnalyticsService $analytics)
    {
        $guru = auth()->user();

        $allSiswa = Siswa::query()
            ->where('guru_id', $guru->id)
            ->where('aktif', true)
            ->orderBy('kelas')
            ->orderBy('nama_peserta_didik')
            ->get();

        $daftarKelas = $allSiswa->pluck('kelas')->filter()->unique()->sort()->values();
        $kelasFilter = trim((string) $request->query('kelas', ''));
        $zonaFilter = trim((string) $request->query('zona', ''));

        $siswaTarget = $kelasFilter !== ''
            ? $allSiswa->where('kelas', $kelasFilter)->values()
            : $allSiswa;

        $cohort = $analytics->buildCohortProfiles($siswaTarget);
        $profiles = $cohort['profiles'];

        if (in_array($zonaFilter, ['di_atas_rata_rata', 'rata_rata', 'di_bawah_rata_rata'], true)) {
            $profiles = array_values(array_filter(
                $profiles,
                fn (array $p) => $p['jumlah_pengujian'] > 0 && $p['zona_kemampuan'] === $zonaFilter
            ));
        }

        return view('guru.tka.kognitif-index', [
            'daftarKelas' => $daftarKelas,
            'kelasFilter' => $kelasFilter,
            'zonaFilter' => $zonaFilter,
            'rataKelompok' => $cohort['rata_rata_kelompok'],
            'zonaCounts' => $cohort['zona_counts'],
            'profiles' => $profiles,
            'indikatorStandar' => TkaCognitiveAnalyticsService::INDIKATOR_STANDAR,
            'totalSiswa' => $siswaTarget->count(),
        ]);
    }

    public function kognitifShow(string $siswaUuid, TkaCognitiveAnalyticsService $analytics)
    {
        abort_unless(Str::isUuid($siswaUuid), 404);

        $siswa = Siswa::query()
            ->where('uuid', $siswaUuid)
            ->where('guru_id', auth()->id())
            ->firstOrFail();

        $profile = $analytics->buildStudentCognitiveProfile($siswa);
        $rekomendasi = $profile['rekomendasi'];

        $riwayatSimulasi = AdaptiveSimulation::query()
            ->where('siswa_id', $siswa->id)
            ->where('status', 'selesai')
            ->latest('submitted_at')
            ->latest('id')
            ->limit(15)
            ->get();

        $riwayatSync = EraporSyncLog::query()
            ->where('siswa_id', $siswa->id)
            ->latest('synced_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('guru.tka.kognitif-show', compact(
            'siswa',
            'profile',
            'rekomendasi',
            'riwayatSimulasi',
            'riwayatSync'
        ));
    }

    public function generateRekomendasi(Request $request, TkaCognitiveAnalyticsService $analytics)
    {
        $validated = $request->validate([
            'siswa_uuid' => 'nullable|uuid',
            'kelas' => 'nullable|string|max:50',
        ]);

        $guruId = (int) auth()->id();

        if (! empty($validated['siswa_uuid'])) {
            $siswa = Siswa::query()
                ->where('uuid', $validated['siswa_uuid'])
                ->where('guru_id', $guruId)
                ->firstOrFail();

            $analytics->generateRecommendation($siswa, null, $guruId);

            return back()->with('success', "Paket rekomendasi pembelajaran personalisasi untuk {$siswa->nama_peserta_didik} berhasil diperbarui.");
        }

        $siswaList = Siswa::query()
            ->where('guru_id', $guruId)
            ->where('aktif', true)
            ->when(! empty($validated['kelas']), fn ($q) => $q->where('kelas', $validated['kelas']))
            ->get();

        if ($siswaList->isEmpty()) {
            return back()->with('error', 'Belum ada data siswa aktif untuk disusun paket rekomendasinya.');
        }

        $cohort = $analytics->buildCohortProfiles($siswaList);
        $mean = $cohort['rata_rata_kelompok'];
        $jumlah = 0;

        foreach ($siswaList as $siswa) {
            $analytics->generateRecommendation($siswa, null, $guruId, $mean);
            $jumlah++;
        }

        return back()->with('success', "Berhasil menyusun paket rekomendasi personalisasi otomatis untuk {$jumlah} siswa.");
    }

    public function updateRekomendasi(Request $request, LearningRecommendation $recommendation)
    {
        $siswa = $recommendation->siswa;
        abort_unless($siswa && (int) $siswa->guru_id === (int) auth()->id(), 403, 'Anda tidak berhak mengubah rekomendasi siswa ini.');

        $validated = $request->validate([
            'status_tindak_lanjut' => 'required|in:direkomendasikan,berjalan,tuntas',
            'catatan_guru' => 'nullable|string|max:1000',
        ]);

        $recommendation->update([
            'status_tindak_lanjut' => $validated['status_tindak_lanjut'],
            'catatan_guru' => $validated['catatan_guru'] ?? null,
        ]);

        return back()->with('success', 'Status tindak lanjut pembelajaran berhasil disimpan.');
    }

    public function integrasiErapor(Request $request, TkaCognitiveAnalyticsService $analytics)
    {
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::query()->where('guru_id', $guru->id)->first();

        $allSiswa = Siswa::query()
            ->where('guru_id', $guru->id)
            ->where('aktif', true)
            ->orderBy('kelas')
            ->orderBy('nama_peserta_didik')
            ->get();

        $daftarKelas = $allSiswa->pluck('kelas')->filter()->unique()->sort()->values();
        $kelasFilter = trim((string) $request->query('kelas', $pengaturan->kelas ?? ($daftarKelas->first() ?? '')));

        $siswaKelas = $kelasFilter !== ''
            ? $allSiswa->where('kelas', $kelasFilter)->values()
            : $allSiswa;

        $cohort = $analytics->buildCohortProfiles($siswaKelas);

        $mapelList = MataPelajaran::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->pluck('nama')
            ->all();

        if ($mapelList === []) {
            $mapelList = [
                'Matematika',
                'Bahasa Indonesia',
                'Ilmu Pengetahuan Alam dan Sosial (IPAS)',
                'Pendidikan Pancasila',
                'Bahasa Inggris',
            ];
        }

        $defaultMapel = $pengaturan->mata_pelajaran ?? $mapelList[0];
        $defaultSemester = in_array($pengaturan->semester ?? '', ['Ganjil', 'Genap'], true)
            ? $pengaturan->semester
            : 'Ganjil';
        $defaultTahunAjaran = $pengaturan->tahun_pelajaran ?? (date('Y').'/'.(date('Y') + 1));

        $syncLogs = EraporSyncLog::with('siswa')
            ->where('guru_id', $guru->id)
            ->latest('synced_at')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('guru.tka.integrasi-erapor', [
            'daftarKelas' => $daftarKelas,
            'kelasFilter' => $kelasFilter,
            'profiles' => $cohort['profiles'],
            'rataKelompok' => $cohort['rata_rata_kelompok'],
            'mapelList' => $mapelList,
            'defaultMapel' => $defaultMapel,
            'defaultSemester' => $defaultSemester,
            'defaultTahunAjaran' => $defaultTahunAjaran,
            'syncLogs' => $syncLogs,
        ]);
    }

    public function syncErapor(Request $request, TkaCognitiveAnalyticsService $analytics)
    {
        $validated = $request->validate([
            'mata_pelajaran' => 'required|string|max:120',
            'komponen_target' => 'required|in:formatif,sumatif,sumatif_akhir',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string|max:20',
            'siswa_uuid' => 'nullable|uuid',
            'kelas' => 'nullable|string|max:50',
        ]);

        $guru = auth()->user();

        if (! empty($validated['siswa_uuid'])) {
            $siswa = Siswa::query()
                ->where('uuid', $validated['siswa_uuid'])
                ->where('guru_id', $guru->id)
                ->firstOrFail();

            try {
                $log = $analytics->syncStudentToErapor($siswa, $guru, $validated);
            } catch (RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with(
                'success',
                "Skor kognitif TKA {$siswa->nama_peserta_didik} ({$log->skor_tka_konversi}) berhasil masuk ke Penilaian & e-Rapor {$log->mata_pelajaran} (Nilai Akhir: {$log->nilai_akhir_erapor})."
            );
        }

        $siswaList = Siswa::query()
            ->where('guru_id', $guru->id)
            ->where('aktif', true)
            ->when(! empty($validated['kelas']), fn ($q) => $q->where('kelas', $validated['kelas']))
            ->get();

        $berhasil = 0;
        $dilewati = 0;

        foreach ($siswaList as $siswa) {
            try {
                $analytics->syncStudentToErapor($siswa, $guru, $validated);
                $berhasil++;
            } catch (RuntimeException $exception) {
                // QueryException juga turunan RuntimeException. Tanpa pemisahan ini,
                // kegagalan simpan (mis. SQLSTATE PostgreSQL) tersamar sebagai
                // "belum ada data TKA" dan penyebabnya hilang dari log.
                if ($exception instanceof QueryException) {
                    Log::warning('Sinkronisasi TKA ke e-Rapor gagal (SQL).', [
                        'siswa_id' => $siswa->id,
                        'mata_pelajaran' => $validated['mata_pelajaran'] ?? null,
                        'message' => mb_substr($exception->getMessage(), 0, 600),
                    ]);
                }

                $dilewati++;
            }
        }

        if ($berhasil === 0) {
            return back()->with('error', 'Belum ada siswa dengan data asesmen/simulasi TKA yang dapat disinkronkan ke e-Rapor.');
        }

        $pesanTambahan = $dilewati > 0 ? " ({$dilewati} siswa dilewati karena belum memiliki data uji TKA)." : '.';

        return back()->with(
            'success',
            "Integrasi berhasil: {$berhasil} siswa telah disinkronkan ke Daftar Nilai & e-Rapor {$validated['mata_pelajaran']} ({$validated['komponen_target']}){$pesanTambahan}"
        );
    }

    public function laporanKelas(Request $request, TkaCognitiveAnalyticsService $analytics)
    {
        $target = max(40.0, min(95.0, (float) $request->query('target', 70)));
        $jenjang = trim((string) $request->query('jenjang', ''));

        $heatmap = $analytics->buildSchoolClassHeatmap(
            $jenjang !== '' ? $jenjang : null,
            $target,
            (int) auth()->id()
        );

        return view('guru.tka.laporan-kelas', compact('heatmap'));
    }
}
