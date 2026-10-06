<?php

namespace Tests\Unit\Models;

use App\Models\JadwalPelajaran;
use App\Models\NilaiErapot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

// Fase 6.1: unit model hasil alignment Fase 3 (tanpa database,
// tetapi butuh aplikasi ter-boot untuk resolver Eloquent).
class NilaiErapotTest extends TestCase
{
    public function test_fillable_memuat_fk_alignment(): void
    {
        $model = new NilaiErapot();

        $this->assertContains('jadwal_pelajaran_id', $model->getFillable());
    }

    public function test_relasi_jadwal_pelajaran_belongs_to(): void
    {
        $relasi = (new NilaiErapot())->jadwalPelajaran();

        $this->assertInstanceOf(BelongsTo::class, $relasi);
        $this->assertSame('jadwal_pelajaran_id', $relasi->getForeignKeyName());
        $this->assertInstanceOf(JadwalPelajaran::class, $relasi->getRelated());
    }

    public function test_relasi_balik_has_many(): void
    {
        $relasi = (new JadwalPelajaran())->nilaiErapot();

        $this->assertInstanceOf(HasMany::class, $relasi);
        $this->assertInstanceOf(NilaiErapot::class, $relasi->getRelated());
    }

    public function test_hitung_nilai_akhir_skala_100(): void
    {
        $model = new NilaiErapot();
        $model->nilai_formatif = 80;
        $model->nilai_sumatif = 85;
        $model->nilai_sumatif_akhir = 90;

        // 80*.30 + 85*.40 + 90*.30 = 85 (tanpa pengali 100).
        $this->assertSame(85.0, $model->hitungNilaiAkhir());
    }

    public function test_generate_predikat_relatif_kkm(): void
    {
        $model = new NilaiErapot();
        $model->nilai_akhir = 76;

        // KKM 75: selisih 7.5 -> A>=82.5, B>=75+... C>=75. 76 = C.
        $this->assertSame('C', $model->generatePredikat(75));

        $model->nilai_akhir = 60;
        $this->assertSame('D', $model->generatePredikat(75));
    }
}
