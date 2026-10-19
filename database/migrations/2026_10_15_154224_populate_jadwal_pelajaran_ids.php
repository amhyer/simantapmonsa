<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Populate jadwal_pelajaran_id in nilai_erapor based on matching rules:
     * 1. Match semester text to semesters.id
     * 2. Normalize mata_pelajaran and kelas (lowercase + trim)
     * 3. Match guru_id + mapel + kelas + semester_id
     * 4. Handle ambiguities (multiple days) → pick first, log count
     * 5. Idempotent (only process NULL values)
     */
    public function up(): void
    {
        // Get all nilai_erapor with NULL jadwal_pelajaran_id
        $nilaiRecords = DB::table('nilai_erapor')
            ->whereNull('jadwal_pelajaran_id')
            ->get();

        $matched = 0;
        $unmatched = 0;
        $ambiguous = 0;

        foreach ($nilaiRecords as $nilai) {
            // Step 1: Find semester_id
            $semester = DB::table('semesters')
                ->whereRaw('LOWER(nama_semester) = ?', [strtolower($nilai->semester)])
                ->where('tahun_ajaran', $nilai->tahun_ajaran)
                ->first();

            if (!$semester) {
                $unmatched++;
                continue; // Skip jika semester tidak ditemukan
            }

            // Step 2: Normalize text for matching
            $mapelNormalized = strtolower(trim($nilai->mata_pelajaran));
            $kelasNormalized = strtolower(trim($nilai->kelas));

            // Step 3: Find matching jadwal_pelajaran
            $jadwalCandidates = DB::table('jadwal_pelajaran')
                ->where('guru_id', $nilai->guru_id)
                ->whereRaw('LOWER(mata_pelajaran) = ?', [$mapelNormalized])
                ->whereRaw('LOWER(kelas) = ?', [$kelasNormalized])
                ->where('semester_id', $semester->id)
                ->orderBy('id')
                ->get();

            if ($jadwalCandidates->count() === 0) {
                $unmatched++;
                continue; // No match
            }

            if ($jadwalCandidates->count() > 1) {
                $ambiguous++; // Log ambiguity
            }

            // Step 4: Pick first match
            $jadwalId = $jadwalCandidates->first()->id;

            // Step 5: Update nilai_erapor
            DB::table('nilai_erapor')
                ->where('id', $nilai->id)
                ->update(['jadwal_pelajaran_id' => $jadwalId]);

            $matched++;
        }

        // Log results
        $logMessage = "Populate selesai: {$matched} matched, {$unmatched} unmatched, {$ambiguous} ambiguous";
        logger()->info($logMessage);
    }

    /**
     * Reverse the migrations.
     *
     * Nullify all jadwal_pelajaran_id (safe rollback)
     */
    public function down(): void
    {
        DB::table('nilai_erapor')
            ->whereNotNull('jadwal_pelajaran_id')
            ->update(['jadwal_pelajaran_id' => null]);

        logger()->info('Rollback: all jadwal_pelajaran_id nullified');
    }
};
