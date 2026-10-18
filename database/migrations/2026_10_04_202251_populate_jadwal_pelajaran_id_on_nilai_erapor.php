<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Populate nilai_erapor.jadwal_pelajaran_id dengan ATURAN KOREKSI
 * (docs/10-ALIGNMENT-DESIGN.md §3) — bukan perbandingan langsung
 * semester_id vs teks semester seperti draf awal rencana.
 *
 * - Idempoten: hanya memproses baris yang masih NULL (aman dijalankan ulang).
 * - Hanya memakai query builder (bukan Eloquent) agar tahan terhadap
 *   perubahan model di masa depan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('nilai_erapor', 'jadwal_pelajaran_id')) {
            Log::warning('Populate jadwal_pelajaran_id dilewati: kolom belum ada.');
            return;
        }

        $norm = fn ($v) => mb_strtolower(trim((string) $v));

        // 1. Peta semester teks -> semesters.id ("ganjil|2025/2026" => [ids]).
        $semMap = [];
        foreach (DB::table('semesters')->get(['id', 'nama_semester', 'tahun_ajaran']) as $s) {
            $semMap[$norm($s->nama_semester) . '|' . trim((string) $s->tahun_ajaran)][] = $s->id;
        }

        // 2. Indeks jadwal "guru|mapel|kelas|semester_id" (id terkecil menang;
        //    jadwal bisa ganda antar-hari -> hitung ambiguitas untuk tindak lanjut).
        $index = [];
        $ambigu = 0;
        foreach (DB::table('jadwal_pelajaran')->orderBy('id')->get(['id', 'guru_id', 'mata_pelajaran', 'kelas', 'semester_id']) as $j) {
            $kunci = $j->guru_id . '|' . $norm($j->mata_pelajaran) . '|' . $norm($j->kelas) . '|' . $j->semester_id;
            if (!isset($index[$kunci])) {
                $index[$kunci] = $j->id;
            } else {
                $ambigu++;
            }
        }

        // 3. Cocokkan per chunk.
        $matched = 0;
        $unmatched = [
            'semester_tak_cocok' => 0,
            'guru_tak_punya_jadwal' => 0,
            'mapel_tak_cocok' => 0,
            'kelas_tak_cocok' => 0,
        ];
        $guruBerjadwal = [];
        foreach (DB::table('jadwal_pelajaran')->selectRaw('distinct guru_id')->pluck('guru_id') as $gid) {
            $guruBerjadwal[$gid] = true;
        }

        DB::table('nilai_erapor')
            ->whereNull('jadwal_pelajaran_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($norm, $semMap, $index, $guruBerjadwal, &$matched, &$unmatched) {
                foreach ($rows as $n) {
                    $sids = $semMap[$norm($n->semester) . '|' . trim((string) $n->tahun_ajaran)] ?? null;
                    if ($sids === null) {
                        $unmatched['semester_tak_cocok']++;
                        continue;
                    }
                    if (!isset($guruBerjadwal[$n->guru_id])) {
                        $unmatched['guru_tak_punya_jadwal']++;
                        continue;
                    }
                    $ditemukan = null;
                    foreach ($sids as $sid) {
                        $kunci = $n->guru_id . '|' . $norm($n->mata_pelajaran) . '|' . $norm($n->kelas) . '|' . $sid;
                        if (isset($index[$kunci])) {
                            $ditemukan = $index[$kunci];
                            break;
                        }
                    }
                    if ($ditemukan === null) {
                        // Diagnosis dimensi mana yang gagal (untuk laporan, bukan matching).
                        $adaMapel = false;
                        $adaKelas = false;
                        foreach ($index as $jk => $_) {
                            $pecah = explode('|', $jk);
                            if (($pecah[0] ?? null) == $n->guru_id) {
                                if (($pecah[1] ?? null) === $norm($n->mata_pelajaran)) {
                                    $adaMapel = true;
                                }
                                if (($pecah[2] ?? null) === $norm($n->kelas)) {
                                    $adaKelas = true;
                                }
                                if ($adaMapel && $adaKelas) {
                                    break;
                                }
                            }
                        }
                        if (!$adaMapel) {
                            $unmatched['mapel_tak_cocok']++;
                        } elseif (!$adaKelas) {
                            $unmatched['kelas_tak_cocok']++;
                        } else {
                            $unmatched['semester_tak_cocok']++;
                        }
                        Log::warning('nilai_erapor tanpa pasangan jadwal', [
                            'nilai_id' => $n->id,
                            'guru_id' => $n->guru_id,
                            'mata_pelajaran' => $n->mata_pelajaran,
                            'kelas' => $n->kelas,
                            'semester' => $n->semester,
                            'tahun_ajaran' => $n->tahun_ajaran,
                        ]);
                        continue;
                    }
                    DB::table('nilai_erapor')->where('id', $n->id)->update(['jadwal_pelajaran_id' => $ditemukan]);
                    $matched++;
                }
            });

        Log::info("Populate jadwal_pelajaran_id selesai: {$matched} matched, " . json_encode($unmatched) . ", {$ambigu} baris jadwal ambigu (hari ganda).");
    }

    public function down(): void
    {
        // Reset ke null agar rollback tidak menghapus data nilai.
        DB::table('nilai_erapor')->update(['jadwal_pelajaran_id' => null]);
    }
};
