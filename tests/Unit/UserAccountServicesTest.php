<?php

namespace Tests\Unit;

use App\Models\Ptk;
use App\Services\Admin\UserBulkGeneratorService;
use Tests\TestCase;

class UserAccountServicesTest extends TestCase
{
    private function generator(): UserBulkGeneratorService
    {
        return app(UserBulkGeneratorService::class);
    }

    public function test_resolve_unique_username_and_email_append_incrementing_suffix_on_collision(): void
    {
        $gen = $this->generator();

        $takenUsernames = [
            'budi' => true,
            'budi.1' => true,
        ];
        $this->assertSame('budi.2', $gen->resolveUniqueUsername('budi', $takenUsernames));
        $this->assertSame('ani', $gen->resolveUniqueUsername('ani', $takenUsernames));

        $takenEmails = [
            'budi@simantap.sch.id' => true,
        ];
        $this->assertSame('budi.1@simantap.sch.id', $gen->resolveUniqueEmail('Budi', $takenEmails));
    }

    public function test_resolve_ptk_role_and_display_name_classify_kepsek_tendik_and_guru(): void
    {
        $gen = $this->generator();

        $kepsek = new Ptk([
            'nama' => 'Siti Aminah',
            'gelar_depan' => 'Dr.',
            'gelar_belakang' => 'M.Pd.',
            'jenis_ptk' => 'Kepala Sekolah',
        ]);
        $this->assertSame('kepsek', $gen->resolvePtkRole($kepsek));
        $this->assertSame('Dr. Siti Aminah, M.Pd.', $gen->formatPtkDisplayName($kepsek));

        $tendik = new Ptk([
            'nama' => 'Rahmat',
            'jenis_ptk' => 'Tenaga Administrasi Sekolah',
        ]);
        $this->assertSame('tendik', $gen->resolvePtkRole($tendik));
        $this->assertSame('Rahmat', $gen->formatPtkDisplayName($tendik));

        $guru = new Ptk([
            'nama' => 'Hasan',
            'jenis_ptk' => 'Guru Kelas',
        ]);
        $this->assertSame('guru', $gen->resolvePtkRole($guru));
    }
}
