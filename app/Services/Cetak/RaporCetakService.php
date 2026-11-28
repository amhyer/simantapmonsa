<?php

namespace App\Services\Cetak;

use App\Models\Catatan;
use App\Models\Kehadiran;
use App\Models\KenaikanKelas;
use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\P5Penilaian;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyusun payload lengkap satu lembar rapor siswa (format e-Rapor SD)
 * agar `CetakController` tetap ramping dan logika pengelompokan mapel,
 * kehadiran per semester, kokurikuler, ekskul, serta penandatangan dapat
 * dipakai ulang oleh cetak per siswa maupun cetak 1 kelas sekaligus.
 */
class RaporCetakService
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $raporConfig
     * @return array<string, mixed>
     */
    public function resolvePrintOptions(array $validated, array $raporConfig): array
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
    public function loadMapelMetaMap(): array
    {
        if (! Schema::hasTable('mata_pelajaran')) {
            return [];
        }

        $map = [];
        foreach (MataPelajaran::all(['nama', 'kelompok', 'urutan']) as $m) {
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
     * @param  array<string, array{kelompok: string, urutan: int}>  $mapelMetaMap
     * @return array<string, mixed>
     */
    public function buildRaporPayload(
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
        if (Schema::hasTable('siswa_ekstrakurikuler') && Schema::hasTable('ekstrakurikuler')) {
            $ekstrakurikuler = DB::table('siswa_ekstrakurikuler')
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
        $waliUser = $waliGuruId ? User::find($waliGuruId, ['nama_lengkap', 'nip']) : null;
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
     * @return array{S: int, I: int, A: int}
     */
    public function rekapKehadiran(int $siswaId): array
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

    /**
     * Rekap ketidakhadiran (S/I/A) khusus semester & tahun ajaran terpilih;
     * fallback ke rekap keseluruhan jika data kehadiran hanya tercatat di luar rentang tanggal semester.
     *
     * @return array{S: int, I: int, A: int}
     */
    public function rekapKehadiranSemester(int $siswaId, string $semester, string $tahunAjaran): array
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

    public function catatanSemester(int $siswaId, string $semester, string $tahunAjaran): ?Catatan
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
}
