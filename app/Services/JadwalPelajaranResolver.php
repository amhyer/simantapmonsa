<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\NilaiErapot;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;

/**
 * Resolusi nilai_erapor -> jadwal_pelajaran memakai ATURAN KOREKSI
 * (docs/10-ALIGNMENT-DESIGN.md §3):
 * semester teks dipetakan via tabel semesters, teks dinormalisasi
 * lower(trim()), ambiguitas hari dimenangkan id terkecil.
 */
class JadwalPelajaranResolver
{
    /**
     * Cari jadwal_pelajaran.id yang cocok, atau null bila tak ada pasangan.
     */
    public function resolve(int $guruId, ?string $mapel, ?string $kelas, ?string $semester, ?string $tahunAjaran): ?int
    {
        $norm = fn ($v) => mb_strtolower(trim((string) $v));

        $semesterIds = Semester::whereRaw('lower(nama_semester) = ?', [$norm($semester)])
            ->where('tahun_ajaran', trim((string) $tahunAjaran))
            ->pluck('id');

        if ($semesterIds->isEmpty()) {
            return null;
        }

        return JadwalPelajaran::where('guru_id', $guruId)
            ->whereRaw('lower(mata_pelajaran) = ?', [$norm($mapel)])
            ->whereRaw('lower(kelas) = ?', [$norm($kelas)])
            ->whereIn('semester_id', $semesterIds)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Statistik keterpetakan untuk halaman alignment-check admin.
     */
    public function alignmentStats(): array
    {
        $total = NilaiErapot::count();
        $mapped = NilaiErapot::whereNotNull('jadwal_pelajaran_id')->count();

        $kategori = [
            'semester_tak_cocok' => 0,
            'guru_tak_punya_jadwal' => 0,
            'mapel_tak_cocok' => 0,
            'kelas_tak_cocok' => 0,
        ];
        $norm = fn ($v) => mb_strtolower(trim((string) $v));

        $semMap = [];
        foreach (DB::table('semesters')->get(['id', 'nama_semester', 'tahun_ajaran']) as $s) {
            $semMap[$norm($s->nama_semester) . '|' . trim((string) $s->tahun_ajaran)][] = $s->id;
        }

        $index = [];
        $guruBerjadwal = [];
        $ambigu = 0;
        foreach (DB::table('jadwal_pelajaran')->orderBy('id')->get(['id', 'guru_id', 'mata_pelajaran', 'kelas', 'semester_id']) as $j) {
            $guruBerjadwal[$j->guru_id] = true;
            $kunci = $j->guru_id . '|' . $norm($j->mata_pelajaran) . '|' . $norm($j->kelas) . '|' . $j->semester_id;
            if (!isset($index[$kunci])) {
                $index[$kunci] = $j->id;
            } else {
                $ambigu++;
            }
        }

        NilaiErapot::whereNull('jadwal_pelajaran_id')->orderBy('id')->chunkById(500, function ($rows) use ($norm, $semMap, $index, $guruBerjadwal, &$kategori) {
            foreach ($rows as $n) {
                $sids = $semMap[$norm($n->semester) . '|' . trim((string) $n->tahun_ajaran)] ?? null;
                if ($sids === null) {
                    $kategori['semester_tak_cocok']++;
                    continue;
                }
                if (!isset($guruBerjadwal[$n->guru_id])) {
                    $kategori['guru_tak_punya_jadwal']++;
                    continue;
                }
                $cocok = false;
                $adaMapel = false;
                $adaKelas = false;
                foreach ($sids as $sid) {
                    $kunci = $n->guru_id . '|' . $norm($n->mata_pelajaran) . '|' . $norm($n->kelas) . '|' . $sid;
                    if (isset($index[$kunci])) {
                        $cocok = true;
                        break;
                    }
                }
                if ($cocok) {
                    continue; // Seharusnya sudah terisi; abaikan (data berubah antar-chunk).
                }
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
                    $kategori['mapel_tak_cocok']++;
                } elseif (!$adaKelas) {
                    $kategori['kelas_tak_cocok']++;
                } else {
                    $kategori['semester_tak_cocok']++;
                }
            }
        });

        return [
            'total' => $total,
            'mapped' => $mapped,
            'unmapped' => $total - $mapped,
            'kategori' => $kategori,
            'jadwal_ambigu' => $ambigu,
            'persen' => $total > 0 ? round($mapped / $total * 100, 1) : 0,
        ];
    }

    /**
     * Logika pemilihan kandidat — murni (tanpa akses DB) sehingga dapat
     * diuji terpisah (tests/Unit/JadwalPelajaranResolverTest).
     *
     * Urutan prioritas:
     *   1. mapel + kelas sama persis (abaikan besar-kecil huruf & spasi ganda)
     *      dan semester_id sama dengan semester yang diminta;
     *   2. kombinasi sama dengan semester_id kosong (data import manual);
     *   3. kombinasi pertama yang cocok.
     *
     * @param  array<int, array{id:int, mata_pelajaran:string, kelas:string, semester_id:?int}>  $kandidat
     */
    public function pilihKandidat(array $kandidat, string $mataPelajaran, string $kelas, ?int $semesterId = null): ?int
    {
        $mapel = $this->normalisasi($mataPelajaran);
        $kelasTarget = $this->normalisasi($kelas);

        $cocok = array_values(array_filter($kandidat, function ($row) use ($mapel, $kelasTarget) {
            return $this->normalisasi($row['mata_pelajaran'] ?? '') === $mapel
                && $this->normalisasi($row['kelas'] ?? '') === $kelasTarget;
        }));

        if ($cocok === []) {
            return null;
        }

        if ($semesterId !== null) {
            foreach ($cocok as $row) {
                if (($row['semester_id'] ?? null) === $semesterId) {
                    return (int) $row['id'];
                }
            }

            foreach ($cocok as $row) {
                if (empty($row['semester_id'])) {
                    return (int) $row['id'];
                }
            }
        }

        return (int) $cocok[0]['id'];
    }

    /**
     * Samakan bentuk teks untuk pencocokan: huruf kecil, spasi ganda
     * diciutkan, spasi tepi dibuang.
     */
    private function normalisasi(?string $teks): string
    {
        return (string) preg_replace('/\s+/', ' ', strtolower(trim((string) $teks)));
    }
}
