<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\NilaiErapot;
use App\Models\Semester;
<<<<<<< HEAD
use Illuminate\Support\Facades\DB;

/**
 * Resolusi nilai_erapor -> jadwal_pelajaran memakai ATURAN KOREKSI
 * (docs/10-ALIGNMENT-DESIGN.md §3):
 * semester teks dipetakan via tabel semesters, teks dinormalisasi
 * lower(trim()), ambiguitas hari dimenangkan id terkecil.
=======

/**
 * Menghubungkan nilai e-Rapor dengan baris `jadwal_pelajaran` (pembelajaran).
 *
 * Dipakai oleh:
 *   - Guru\NilaiErapotController::simpan()/autoSave() → mengisi FK
 *     `nilai_erapor.jadwal_pelajaran_id`
 *   - Admin\DataReferensiController::alignmentCheck() → statistik keterpetakan
 *
 * Kelas ini sebelumnya TIDAK ADA sehingga penyimpanan nilai e-Rapor dan
 * halaman Cek Alignment melempar error "Class not found".
>>>>>>> origin/main
 */
class JadwalPelajaranResolver
{
    /**
<<<<<<< HEAD
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
=======
     * Cari id jadwal_pelajaran yang cocok untuk kombinasi guru + mapel + kelas.
     * Mengembalikan null bila belum ada jadwal yang cocok (data tetap
     * disimpan, hanya belum terpetakan).
     */
    public function resolve(
        $guruId,
        ?string $mataPelajaran,
        ?string $kelas,
        ?string $semester = null,
        ?string $tahunAjaran = null
    ): ?int {
        if (!$guruId || !$mataPelajaran || !$kelas) {
            return null;
        }

        $kandidat = JadwalPelajaran::query()
            ->where('guru_id', $guruId)
            ->orderBy('id')
            ->get(['id', 'mata_pelajaran', 'kelas', 'semester_id'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'mata_pelajaran' => (string) $row->mata_pelajaran,
                'kelas' => (string) $row->kelas,
                'semester_id' => $row->semester_id !== null ? (int) $row->semester_id : null,
            ])
            ->all();

        return $this->pilihKandidat(
            $kandidat,
            $mataPelajaran,
            $kelas,
            $this->semesterId($semester, $tahunAjaran)
        );
    }

    /**
     * Logika pemilihan kandidat — murni (tanpa akses DB) sehingga dapat
     * diuji terpisah.
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
     * Statistik keterpetakan nilai e-Rapor → pembelajaran untuk halaman
     * Admin > Data Referensi > Cek Alignment.
>>>>>>> origin/main
     */
    public function alignmentStats(): array
    {
        $total = NilaiErapot::count();
<<<<<<< HEAD
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
=======
        $terpetakan = NilaiErapot::whereNotNull('jadwal_pelajaran_id')->count();

        $belum = NilaiErapot::query()
            ->whereNull('jadwal_pelajaran_id')
            ->selectRaw('mata_pelajaran, kelas, COUNT(*) as jumlah')
            ->groupBy('mata_pelajaran', 'kelas')
            ->orderByDesc('jumlah')
            ->get();

        return [
            'total_nilai' => $total,
            'terpetakan' => $terpetakan,
            'belum_terpetakan' => max($total - $terpetakan, 0),
            'persen_terpetakan' => $total > 0 ? round(($terpetakan / $total) * 100, 1) : 0.0,
            'total_jadwal' => JadwalPelajaran::count(),
            'jadwal_tanpa_rombel' => JadwalPelajaran::whereNull('rombel_id')->count(),
            'belum_list' => $belum,
        ];
    }

    /**
     * Samakan bentuk teks untuk pencocokan: huruf kecil, spasi ganda
     * diciutkan, spasi tepi dibuang.
     */
    private function normalisasi(?string $teks): string
    {
        return (string) preg_replace('/\s+/', ' ', strtolower(trim((string) $teks)));
    }

    /**
     * Terjemahkan (nama semester, tahun ajaran) menjadi id baris `semesters`.
     * `nama_semester` disimpan huruf kecil ('ganjil'/'genap') sedangkan form
     * memakai 'Ganjil'/'Genap', jadi pencocokan dilakukan case-insensitive.
     */
    private function semesterId(?string $semester, ?string $tahunAjaran): ?int
    {
        $semester = trim((string) $semester);

        if ($semester === '') {
            return null;
        }

        $query = Semester::query();

        if (trim((string) $tahunAjaran) !== '') {
            $query->where('tahun_ajaran', trim((string) $tahunAjaran));
        }

        $query->where(function ($q) use ($semester) {
            $q->whereRaw('LOWER(nama_semester) = ?', [strtolower($semester)])
                ->orWhere('semester_id', $semester);
        });

        $id = $query->orderByDesc('semester_id')->value('id');

        return $id !== null ? (int) $id : null;
    }
>>>>>>> origin/main
}
