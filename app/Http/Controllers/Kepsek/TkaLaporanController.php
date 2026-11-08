<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Services\Tka\TkaCognitiveAnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TkaLaporanController extends Controller
{
    public function index(Request $request, TkaCognitiveAnalyticsService $analytics)
    {
        $target = max(40.0, min(95.0, (float) $request->query('target', 70)));
        $jenjang = trim((string) $request->query('jenjang', ''));

        $heatmap = $analytics->buildSchoolClassHeatmap(
            $jenjang !== '' ? $jenjang : null,
            $target
        );

        return view('kepsek.tka.laporan-kelas', compact('heatmap'));
    }

    public function exportCsv(Request $request, TkaCognitiveAnalyticsService $analytics): StreamedResponse
    {
        $target = max(40.0, min(95.0, (float) $request->query('target', 70)));
        $jenjang = trim((string) $request->query('jenjang', ''));

        $heatmap = $analytics->buildSchoolClassHeatmap(
            $jenjang !== '' ? $jenjang : null,
            $target
        );

        $filename = 'laporan-eksekutif-heatmap-tka-'.now()->format('Ymd-His').'.csv';

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
