<?php

namespace Tests\Unit;

use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use App\Services\Presensi\MapelAttendancePolicy;
use Tests\TestCase;

class MapelAttendancePolicyTest extends TestCase
{
    public function test_nama_mapel_khusus_dikenali_dalam_variasi_penulisan(): void
    {
        $policy = new MapelAttendancePolicy();

        foreach ([
            'Bahasa Inggris',
            'Pendidikan Agama Islam dan Budi Pekerti',
            'PAI',
            'Pendidikan Agama Kristen',
            'PAK',
            'PJOK',
            'Pendidikan Jasmani, Olahraga, dan Kesehatan',
        ] as $subject) {
            $this->assertTrue($policy->requiresSeparateAttendance($subject), $subject . ' harus punya catatan terpisah.');
        }

        $this->assertFalse($policy->requiresSeparateAttendance('Matematika'));
        $this->assertFalse($policy->requiresSeparateAttendance('Paket Keahlian'));
    }

    public function test_sesi_agama_menyaring_siswa_dengan_agama_yang_berbeda(): void
    {
        $policy = new MapelAttendancePolicy();
        $jadwal = new JadwalPelajaran(['mata_pelajaran' => 'Pendidikan Agama Kristen']);
        $kristen = new Siswa(['nama_peserta_didik' => 'Kristen', 'agama' => 'Kristen']);
        $islam = new Siswa(['nama_peserta_didik' => 'Islam', 'agama' => 'Islam']);
        $kosong = new Siswa(['nama_peserta_didik' => 'Belum diisi', 'agama' => null]);

        $eligible = $policy->siswaSesuaiMapel([$kristen, $islam, $kosong], $jadwal);

        $this->assertSame([$kristen, $kosong], $eligible);
    }
}
