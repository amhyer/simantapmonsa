<?php

namespace Tests\Feature;

use App\Models\PresensiPegawai;
use App\Models\User;
use App\Services\PresensiMesinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PresensiMesinKlasifikasiTest extends TestCase
{
    use RefreshDatabase;

    private function guru(string $nik): User
    {
        return User::factory()->guru()->create([
            'nik' => $nik,
            'nama_lengkap' => 'Guru '.$nik,
            'aktif' => true,
        ]);
    }

    /**
     * @return array{nik: string, waktu: Carbon, jenis: string}
     */
    private function scan(string $nik, string $waktu, string $jenis): array
    {
        return [
            'nik' => $nik,
            'waktu' => Carbon::parse($waktu, config('app.timezone')),
            'jenis' => $jenis,
        ];
    }

    public function test_scan_pulang_sebelum_masuk_dikoreksi_menjadi_masuk(): void
    {
        $user = $this->guru('0000000014');

        $hasil = app(PresensiMesinService::class)->importScans([
            $this->scan('0000000014', '2026-11-17 06:29:44', 'pulang'),
            $this->scan('0000000014', '2026-11-17 06:29:54', 'masuk'),
        ]);

        $this->assertSame(1, $hasil['dibuat']);

        $record = PresensiPegawai::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-11-17 06:29:44', $record->waktu_masuk?->toDateTimeString());
        $this->assertNull($record->waktu_pulang);
        $this->assertSame('hadir', $record->status);
    }

    public function test_pasangan_masuk_dan_pulang_normal_tetap_utuh(): void
    {
        $user = $this->guru('0000000012');

        app(PresensiMesinService::class)->importScans([
            $this->scan('0000000012', '2026-11-16 06:31:00', 'masuk'),
            $this->scan('0000000012', '2026-11-16 14:37:00', 'pulang'),
        ]);

        $record = PresensiPegawai::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-11-16 06:31:00', $record->waktu_masuk?->toDateTimeString());
        $this->assertSame('2026-11-16 14:37:00', $record->waktu_pulang?->toDateTimeString());
    }

    public function test_pulang_cepat_sebelum_jam_14_30_tetap_dicatat_pulang(): void
    {
        $user = $this->guru('0000000044');

        app(PresensiMesinService::class)->importScans([
            $this->scan('0000000044', '2026-11-14 06:46:00', 'masuk'),
            $this->scan('0000000044', '2026-11-14 12:48:00', 'pulang'),
        ]);

        $record = PresensiPegawai::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-11-14 06:46:00', $record->waktu_masuk?->toDateTimeString());
        $this->assertSame('2026-11-14 12:48:00', $record->waktu_pulang?->toDateTimeString());
    }

    public function test_scan_pulang_tanpa_masuk_tidak_membuat_record_palsu(): void
    {
        $user = $this->guru('0000000045');

        $hasil = app(PresensiMesinService::class)->importScans([
            $this->scan('0000000045', '2026-11-14 16:00:00', 'pulang'),
        ]);

        $this->assertSame(0, $hasil['dibuat']);
        $this->assertSame(1, $hasil['dilindungi']);
        $this->assertDatabaseMissing('presensi_pegawai', ['user_id' => $user->id]);
    }

    public function test_scan_setelah_tengah_malam_masuk_ke_hari_sebelumnya(): void
    {
        $user = $this->guru('0000000046');

        app(PresensiMesinService::class)->importScans([
            $this->scan('0000000046', '2026-11-17 06:30:00', 'masuk'),
            $this->scan('0000000046', '2026-11-18 00:30:00', 'pulang'),
        ]);

        $record = PresensiPegawai::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-11-17', $record->tanggal?->toDateString());
        $this->assertSame('2026-11-18 00:30:00', $record->waktu_pulang?->toDateTimeString());

        $this->assertSame(0, PresensiPegawai::query()->where('user_id', $user->id)->whereDate('tanggal', '2026-11-18')->count());
    }

    public function test_tarik_ulang_membersihkan_pulang_yang_lebih_awal_dari_masuk(): void
    {
        $user = $this->guru('0000000047');

        PresensiPegawai::create([
            'user_id' => $user->id,
            'tanggal' => '2026-11-17',
            'status' => 'hadir',
            'waktu_masuk' => '2026-11-17 06:29:54',
            'waktu_pulang' => '2026-11-17 06:29:44',
            'metode' => 'mesin',
            'metode_masuk' => 'mesin',
            'metode_pulang' => 'mesin',
        ]);

        app(PresensiMesinService::class)->importScans([
            $this->scan('0000000047', '2026-11-17 06:29:44', 'pulang'),
            $this->scan('0000000047', '2026-11-17 06:29:54', 'masuk'),
        ]);

        $record = PresensiPegawai::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-11-17 06:29:44', $record->waktu_masuk?->toDateTimeString());
        $this->assertNull($record->waktu_pulang);
        $this->assertNull($record->metode_pulang);
    }
}
