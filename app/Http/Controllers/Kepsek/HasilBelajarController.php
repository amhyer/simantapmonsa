<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\Nilai;
use App\Models\RingkasanGuru;

class HasilBelajarController extends Controller
{
    public function index()
    {
        // RingkasanGuru (HasSchoolOwnership) & Nilai (BelongsToTenant) sudah terscope ke sekolah aktif.
        $ringkasan = RingkasanGuru::with('guru')->orderBy('nama_guru')->get();

        $analisisPerMapel = $ringkasan->groupBy('mata_pelajaran')->map(function ($items, $mapel) {
            return [
                'mata_pelajaran' => $mapel,
                'jumlah_kelas' => $items->count(),
                'rata_rata' => round((float) $items->avg('rata_rata'), 2),
                'ketuntasan' => round((float) $items->avg('ketuntasan_persen'), 2),
                'kehadiran' => round((float) $items->avg('kehadiran_persen'), 2),
                'tertinggi' => $items->max('rata_rata'),
                'terendah' => $items->min('rata_rata'),
                'detail' => $items,
            ];
        })->values();

        $rataRataSekolah = $ringkasan->count() > 0 ? round((float) $ringkasan->avg('rata_rata'), 1) : 0;
        $ketuntasanRataRata = $ringkasan->count() > 0 ? round((float) $ringkasan->avg('ketuntasan_persen'), 1) : 0;

        $terbaik = $ringkasan->sortByDesc('rata_rata')->first();
        $kelasTertinggi = $terbaik?->kelas ?: ($terbaik?->mata_pelajaran ?? '—');
        $nilaiTertinggi = (float) ($terbaik?->rata_rata ?? 0);

        $kkm = getKKM();
        $jumlahKelasRendah = $ringkasan->filter(function ($r) use ($kkm): bool {
            $kkmBaris = (int) ($r->kkm ?: $kkm);

            return (float) $r->rata_rata < $kkmBaris;
        })->count();

        $nilaiTertinggiList = Nilai::with(['siswa', 'guru'])
            ->orderByDesc('nilai')
            ->limit(10)
            ->get();

        $nilaiTerendahList = Nilai::where('nilai', '>', 0)
            ->with(['siswa', 'guru'])
            ->orderBy('nilai')
            ->limit(10)
            ->get();

        // Data tabel & grafik nyata per kelas/mapel (tidak memakai fallback angka statis).
        $nilaiPerGuruMapel = Nilai::selectRaw('guru_id, mata_pelajaran, MAX(nilai) as tertinggi, MIN(CASE WHEN nilai > 0 THEN nilai END) as terendah')
            ->groupBy('guru_id', 'mata_pelajaran')
            ->get()
            ->keyBy(fn ($row) => $row->guru_id.'|'.strtolower(trim((string) ($row->mata_pelajaran ?? ''))));

        $nilaiPerGuru = Nilai::selectRaw('guru_id, MAX(nilai) as tertinggi, MIN(CASE WHEN nilai > 0 THEN nilai END) as terendah')
            ->groupBy('guru_id')
            ->get()
            ->keyBy('guru_id');

        $tabelPerbandingan = $ringkasan->map(function ($r) use ($nilaiPerGuruMapel, $nilaiPerGuru) {
            $mapelKey = $r->guru_id.'|'.strtolower(trim((string) ($r->mata_pelajaran ?? '')));
            $stat = $nilaiPerGuruMapel->get($mapelKey) ?? $nilaiPerGuru->get($r->guru_id);

            return (object) [
                'nama_kelas' => $r->kelas ?: ($r->mata_pelajaran ?: '—'),
                'nama_wali' => $r->nama_guru ?: ($r->guru?->nama_lengkap ?? '—'),
                'jumlah_siswa' => (int) ($r->jumlah_siswa ?? 0),
                'rata_rata' => (float) ($r->rata_rata ?? 0),
                'tertinggi' => (float) ($stat->tertinggi ?? $r->rata_rata ?? 0),
                'terendah' => (float) ($stat->terendah ?? $r->rata_rata ?? 0),
                'ketuntasan_persen' => (float) ($r->ketuntasan_persen ?? 0),
                'kehadiran_persen' => (float) ($r->kehadiran_persen ?? 0),
            ];
        })->values();

        $chartKelasLabels = $ringkasan->map(fn ($r) => $r->kelas ?: ($r->nama_guru ?: 'Kelas'))->values()->all();
        $chartKelasRataRata = $ringkasan->map(fn ($r) => round((float) ($r->rata_rata ?? 0), 1))->values()->all();
        $chartPredikatA = $ringkasan->map(fn ($r) => (int) ($r->predikat_a ?? 0))->values()->all();
        $chartPredikatB = $ringkasan->map(fn ($r) => (int) ($r->predikat_b ?? 0))->values()->all();
        $chartPredikatC = $ringkasan->map(fn ($r) => (int) ($r->predikat_c ?? 0))->values()->all();
        $chartPredikatD = $ringkasan->map(fn ($r) => (int) ($r->predikat_d ?? 0))->values()->all();
        $chartKetuntasanData = $ringkasan->map(fn ($r) => round((float) ($r->ketuntasan_persen ?? 0), 1))->values()->all();
        $chartTrenLabels = ['Tugas', 'UH', 'Praktik', 'PTS', 'PAS'];
        $chartTrenDatasets = $ringkasan->take(6)->map(fn ($r) => [
            $r->kelas ?: ($r->nama_guru ?: 'Kelas'),
            [
                round((float) ($r->rata_tugas ?? 0), 1),
                round((float) ($r->rata_ulangan_harian ?? 0), 1),
                round((float) ($r->rata_praktik ?? 0), 1),
                round((float) ($r->rata_pts ?? 0), 1),
                round((float) ($r->rata_pas ?? 0), 1),
            ],
        ])->values()->all();

        return view('kepsek.hasil-belajar.index', compact(
            'ringkasan', 'analisisPerMapel', 'rataRataSekolah', 'kelasTertinggi',
            'nilaiTertinggi', 'ketuntasanRataRata', 'jumlahKelasRendah', 'kkm',
            'nilaiTertinggiList', 'nilaiTerendahList', 'tabelPerbandingan',
            'chartKelasLabels', 'chartKelasRataRata', 'chartPredikatA',
            'chartPredikatB', 'chartPredikatC', 'chartPredikatD',
            'chartKetuntasanData', 'chartTrenLabels', 'chartTrenDatasets'
        ));
    }
}
