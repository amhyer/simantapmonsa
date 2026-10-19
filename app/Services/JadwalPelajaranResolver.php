<?php

namespace App\Services;

use App\Models\NilaiErapot;
use App\Models\JadwalPelajaran;
use App\Models\Semester;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk mencocokkan nilai_erapor dengan jadwal_pelajaran (Dapodik).
 * 
 * Matching rules:
 * 1. Semester text → semesters.id (case-insensitive)
 * 2. Normalize mata_pelajaran & kelas (lowercase + trim)
 * 3. Match: guru_id + mapel + kelas + semester_id
 * 4. Ambiguity: multiple results → pick first, log count
 */
class JadwalPelajaranResolver
{
    /**
     * Resolve jadwal_pelajaran_id - supports multiple call signatures.
     *
     * Signature 1: resolve(NilaiErapot $nilai)
     * Signature 2: resolve(int $guruId, string $mapel, string $kelas, string $semester, string $tahunAjaran)
     *
     * @param NilaiErapot|int ...$args
     * @return int|null jadwal_pelajaran_id atau null jika tidak ditemukan
     */
    public function resolve(...$args): ?int
    {
        // Signature 1: resolve(NilaiErapot $nilai)
        if (count($args) === 1 && $args[0] instanceof NilaiErapot) {
            return $this->resolveFromModel($args[0]);
        }
        
        // Signature 2: resolve(int $guruId, string $mapel, string $kelas, string $semester, string $tahunAjaran)
        if (count($args) === 5) {
            return $this->resolveFromParams($args[0], $args[1], $args[2], $args[3], $args[4]);
        }
        
        throw new \InvalidArgumentException('Invalid arguments for resolve()');
    }

    /**
     * Resolve dari NilaiErapot model.
     */
    private function resolveFromModel(NilaiErapot $nilai): ?int
    {
        // Step 1: Find semester_id
        $semester = Semester::whereRaw('LOWER(nama_semester) = ?', [strtolower($nilai->semester)])
            ->where('tahun_ajaran', $nilai->tahun_ajaran)
            ->first();

        if (!$semester) {
            Log::debug("Semester tidak ditemukan untuk nilai #{$nilai->id}");
            return null;
        }

        // Step 2: Normalize text
        $mapelNormalized = strtolower(trim($nilai->mata_pelajaran));
        $kelasNormalized = strtolower(trim($nilai->kelas));

        // Step 3: Find matching jadwal
        $candidates = JadwalPelajaran::where('guru_id', $nilai->guru_id)
            ->whereRaw('LOWER(mata_pelajaran) = ?', [$mapelNormalized])
            ->whereRaw('LOWER(kelas) = ?', [$kelasNormalized])
            ->where('semester_id', $semester->id)
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            Log::debug("Tidak ada jadwal untuk nilai #{$nilai->id}");
            return null;
        }

        if ($candidates->count() > 1) {
            Log::info("Ambiguity: {$candidates->count()} jadwal untuk nilai #{$nilai->id}, picking first");
        }

        return $candidates->first()->id;
    }

    /**
     * Resolve dari parameter individual.
     */
    private function resolveFromParams(int $guruId, string $mapel, string $kelas, string $semester, string $tahunAjaran): ?int
    {
        // Step 1: Find semester_id
        $semesterModel = Semester::whereRaw('LOWER(nama_semester) = ?', [strtolower($semester)])
            ->where('tahun_ajaran', $tahunAjaran)
            ->first();

        if (!$semesterModel) {
            Log::debug("Semester tidak ditemukan: {$semester} {$tahunAjaran}");
            return null;
        }

        // Step 2: Normalize text
        $mapelNormalized = strtolower(trim($mapel));
        $kelasNormalized = strtolower(trim($kelas));

        // Step 3: Find matching jadwal
        $candidates = JadwalPelajaran::where('guru_id', $guruId)
            ->whereRaw('LOWER(mata_pelajaran) = ?', [$mapelNormalized])
            ->whereRaw('LOWER(kelas) = ?', [$kelasNormalized])
            ->where('semester_id', $semesterModel->id)
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() > 1) {
            Log::info("Ambiguity: {$candidates->count()} jadwal, picking first");
        }

        return $candidates->first()->id;
    }

    /**
     * Pilih kandidat jadwal yang paling sesuai (untuk unit test tanpa DB).
     *
     * @param array $kandidat Array of candidates with keys: id, mata_pelajaran, kelas, semester_id
     * @param string $mapel Nama mata pelajaran
     * @param string $kelas Nama kelas
     * @param int|null $semesterId ID semester atau null
     * @return int|null ID kandidat terpilih atau null
     */
    public function pilihKandidat(array $kandidat, string $mapel, string $kelas, ?int $semesterId): ?int
    {
        // Normalize input
        $mapelNorm = strtolower(trim($mapel));
        $kelasNorm = strtolower(trim($kelas));
        // Normalize whitespace (replace multiple spaces with single space)
        $mapelNorm = preg_replace('/\s+/', ' ', $mapelNorm);
        $kelasNorm = preg_replace('/\s+/', ' ', $kelasNorm);

        // Filter by mapel and kelas (case-insensitive)
        $filtered = array_filter($kandidat, function ($k) use ($mapelNorm, $kelasNorm) {
            $kMapel = strtolower(trim($k['mata_pelajaran']));
            $kMapel = preg_replace('/\s+/', ' ', $kMapel);
            $kKelas = strtolower(trim($k['kelas']));
            $kKelas = preg_replace('/\s+/', ' ', $kKelas);
            return $kMapel === $mapelNorm && $kKelas === $kelasNorm;
        });

        if (empty($filtered)) {
            return null;
        }

        // If semester provided, prefer exact match
        if ($semesterId !== null) {
            foreach ($filtered as $k) {
                if (($k['semester_id'] ?? null) === $semesterId) {
                    return $k['id'];
                }
            }
        }

        // Fallback: candidate with null semester_id
        foreach ($filtered as $k) {
            if (($k['semester_id'] ?? null) === null) {
                return $k['id'];
            }
        }

        // Last resort: first candidate
        return reset($filtered)['id'] ?? null;
    }

    /**
     * Get alignment statistics.
     *
     * @return array{total: int, matched: int, unmatched: int, persen: float, categories: array}
     */
    public function alignmentStats(): array
    {
        $total = NilaiErapot::count();
        $matched = NilaiErapot::whereNotNull('jadwal_pelajaran_id')->count();
        $unmatched = $total - $matched;
        $persen = $total > 0 ? round($matched / $total * 100, 2) : 0;

        return [
            'total' => $total,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'persen' => $persen,
            'categories' => [], // Can be expanded to show unmatched categories
        ];
    }
}