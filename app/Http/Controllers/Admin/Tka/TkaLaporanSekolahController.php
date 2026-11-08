<?php

namespace App\Http\Controllers\Admin\Tka;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Tka\AdaptiveSimulation;
use App\Models\Tka\EraporSyncLog;
use App\Services\Tka\TkaCognitiveAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TkaLaporanSekolahController extends Controller
{
    public function kognitifIndex(Request $request, TkaCognitiveAnalyticsService $analytics)
    {
        $allSiswa = Siswa::query()
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

        return view('admin.tka.kognitif-index', [
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

        return view('admin.tka.kognitif-show', compact(
            'siswa',
            'profile',
            'rekomendasi',
            'riwayatSimulasi',
            'riwayatSync'
        ));
    }

    public function laporanKelas(Request $request, TkaCognitiveAnalyticsService $analytics)
    {
        $target = max(40.0, min(95.0, (float) $request->query('target', 70)));
        $jenjang = trim((string) $request->query('jenjang', ''));

        $heatmap = $analytics->buildSchoolClassHeatmap(
            $jenjang !== '' ? $jenjang : null,
            $target
        );

        return view('admin.tka.laporan-kelas', compact('heatmap'));
    }

    public function exportCsv(Request $request, TkaCognitiveAnalyticsService $analytics): StreamedResponse
    {
        $target = max(40.0, min(95.0, (float) $request->query('target', 70)));
        $jenjang = trim((string) $request->query('jenjang', ''));

        $heatmap = $analytics->buildSchoolClassHeatmap(
            $jenjang !== '' ? $jenjang : null,
            $target
        );

        $filename = 'laporan-heatmap-tka-sekolah-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($heatmap): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            $headers = array_merge(
                ['Kelas', 'Jenjang/Tingkat', 'Jumlah Siswa', 'Siswa Terukur', 'Rata-rata Kognitif', 'Selisih Target ('.$heatmap['target_skor'].')'],
                $heatmap['indikator_columns']
            );
            fputcsv($out, $headers);

            foreach ($heatmap['kelas_rows'] as $row) {
                $line = [
                    $row['kelas'],
                    $row['tingkat'],
                    $row['jumlah_siswa'],
                    $row['siswa_terukur'],
                    $row['rata_skor'] ?? '-',
                    $row['kesenjangan_umum'] ?? '-',
                ];

                foreach ($heatmap['indikator_columns'] as $ind) {
                    $cell = $row['cells'][$ind] ?? null;
                    if (! $cell || ! $cell['terukur']) {
                        $line[] = '-';
                    } else {
                        $sign = $cell['kesenjangan'] >= 0 ? '+' : '';
                        $line[] = "{$cell['rata_skor']} ({$sign}{$cell['kesenjangan']})";
                    }
                }

                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
