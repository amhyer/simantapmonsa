<?php

namespace Tests\Unit;

use App\Services\JadwalPelajaranResolver;
use PHPUnit\Framework\TestCase;

/**
 * Regresi bug "Class App\Services\JadwalPelajaranResolver not found":
 * kelas ini dipakai untuk mengisi FK nilai_erapor.jadwal_pelajaran_id dan
 * untuk halaman Cek Alignment, tetapi sebelumnya belum ada.
 *
 * Logika pemilihan kandidat diuji tanpa basis data.
 */
class JadwalPelajaranResolverTest extends TestCase
{
    private function kandidat(): array
    {
        return [
            ['id' => 11, 'mata_pelajaran' => 'Matematika', 'kelas' => 'V A', 'semester_id' => 1],
            ['id' => 12, 'mata_pelajaran' => 'Matematika', 'kelas' => 'V A', 'semester_id' => 2],
            ['id' => 13, 'mata_pelajaran' => 'Bahasa Indonesia', 'kelas' => 'V A', 'semester_id' => null],
            ['id' => 14, 'mata_pelajaran' => 'Matematika', 'kelas' => 'VI B', 'semester_id' => 1],
        ];
    }

    public function test_memilih_jadwal_sesuai_semester(): void
    {
        $resolver = new JadwalPelajaranResolver();

        $this->assertSame(12, $resolver->pilihKandidat($this->kandidat(), 'Matematika', 'V A', 2));
        $this->assertSame(11, $resolver->pilihKandidat($this->kandidat(), 'Matematika', 'V A', 1));
    }

    public function test_abaikan_besar_kecil_huruf_dan_spasi_ganda(): void
    {
        $resolver = new JadwalPelajaranResolver();

        $this->assertSame(11, $resolver->pilihKandidat($this->kandidat(), '  matematika ', 'v   a', 1));
    }

    public function test_fallback_ke_baris_tanpa_semester(): void
    {
        $resolver = new JadwalPelajaranResolver();

        // Semester 9 tidak ada di kandidat → pakai baris dengan semester_id kosong.
        $this->assertSame(13, $resolver->pilihKandidat($this->kandidat(), 'Bahasa Indonesia', 'V A', 9));
    }

    public function test_null_bila_tidak_ada_yang_cocok(): void
    {
        $resolver = new JadwalPelajaranResolver();

        $this->assertNull($resolver->pilihKandidat($this->kandidat(), 'Fisika', 'V A', 1));
        $this->assertNull($resolver->pilihKandidat([], 'Matematika', 'V A', 1));
    }

    public function test_tanpa_semester_memilih_kandidat_pertama(): void
    {
        $resolver = new JadwalPelajaranResolver();

        $this->assertSame(11, $resolver->pilihKandidat($this->kandidat(), 'Matematika', 'V A', null));
    }
}
