<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\NilaiErapot;
use App\Models\Semester;

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
 */
class JadwalPelajaranResolver
{
    /**
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
     */
    public function alignmentStats(): array
    {
        $total = NilaiErapot::count();
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
}
