<?php

namespace Tests\Unit;

use App\Services\NilaiService;
use PHPUnit\Framework\TestCase;

/**
 * Regresi bug "empat implementasi predikat": ambang predikat HARUS berasal
 * dari satu sumber (App\Services\NilaiService) dan relatif terhadap KKM.
 *
 * Sebelum perbaikan, App\Models\NilaiErapot::generatePredikat() memakai
 * ambang mati 90/80/KKM sehingga nilai yang sama bisa berpredikat berbeda
 * antara halaman e-Rapor dan halaman laporan/dashboard.
 */
class PredikatKonsistensiTest extends TestCase
{
    public function test_ambang_predikat_relatif_terhadap_kkm(): void
    {
        $service = new NilaiService();

        // KKM 70 → A ≥ 84, B ≥ 77, C ≥ 70, D < 70
        $this->assertSame('A', $service->getPredikat(84, 70)['huruf']);
        $this->assertSame('A', $service->getPredikat(100, 70)['huruf']);
        $this->assertSame('B', $service->getPredikat(83.99, 70)['huruf']);
        $this->assertSame('B', $service->getPredikat(77, 70)['huruf']);
        $this->assertSame('C', $service->getPredikat(76.99, 70)['huruf']);
        $this->assertSame('C', $service->getPredikat(70, 70)['huruf']);
        $this->assertSame('D', $service->getPredikat(69.99, 70)['huruf']);
    }

    public function test_kkm_yang_berbeda_menggeser_ambang(): void
    {
        $service = new NilaiService();

        // KKM 60 → A ≥ 72, B ≥ 66, C ≥ 60
        $this->assertSame('A', $service->getPredikat(72, 60)['huruf']);
        $this->assertSame('B', $service->getPredikat(71.99, 60)['huruf']);
        $this->assertSame('C', $service->getPredikat(60, 60)['huruf']);
        $this->assertSame('D', $service->getPredikat(59.99, 60)['huruf']);
    }

    public function test_label_dan_kelas_css_konsisten(): void
    {
        $service = new NilaiService();

        $this->assertSame(
            ['huruf' => 'A', 'label' => 'Sangat Baik', 'kelas' => 'ok'],
            $service->getPredikat(90, 70)
        );
        $this->assertSame('Perlu Bimbingan', $service->getPredikat(10, 70)['label']);
        $this->assertContains($service->getPredikat(10, 70)['kelas'], ['ok', 'warn', 'bad']);
    }
}
