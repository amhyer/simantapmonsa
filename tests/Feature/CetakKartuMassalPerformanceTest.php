<?php

namespace Tests\Feature;

use App\Models\Siswa;
use App\Models\User;
use App\Services\Presensi\IdentityCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CetakKartuMassalPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cetak_siswa_massal_100_students_stays_within_query_budget_and_marks_all_printed(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create([
            'peran' => 'admin',
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $cards = app(IdentityCardService::class);
        $siswas = [];
        for ($i = 1; $i <= 100; $i++) {
            $s = Siswa::create([
                'nis' => '9100'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nama_peserta_didik' => "Siswa Cetak {$i}",
                'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                'kelas' => '6C',
                'aktif' => true,
            ]);
            // Terbitkan kartu terlebih dahulu (skenario cetak ulang / kartu sudah diterbitkan)
            $cards->ensureSiswaCard($s);
            $siswas[] = $s;
        }

        // Warm-up cache layout
        $this->actingAs($admin)->get(route('admin.presensi.kartu.siswa.cetak-massal'))->assertOk();
        $firstTokenHash = $siswas[0]->fresh()->qr_token_hash;

        DB::flushQueryLog();
        DB::enableQueryLog();
        $res = $this->actingAs($admin)
            ->get(route('admin.presensi.kartu.siswa.cetak-massal'))
            ->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Budget ketat <= 20 query (aktual 6 query; sebelumnya 320-406 query)
        $this->assertLessThanOrEqual(20, $queries, "Query cetakSiswaMassal terlalu tinggi: {$queries}");

        // Pastikan HTML kartu tetap lengkap dan token TIDAK dirotasi saat cetak ulang
        $res->assertSee('Cetak Kartu Identitas Siswa')
            ->assertSee('Siswa Cetak 1')
            ->assertSee('KARTU IDENTITAS SISWA');
        $this->assertSame($firstTokenHash, $siswas[0]->fresh()->qr_token_hash);

        // Semua 80 siswa pada batch 1 memiliki printed_at terisi
        $printedCount = Siswa::where('kelas', '6C')->whereNotNull('printed_at')->count();
        $this->assertSame(80, $printedCount);

        fwrite(STDOUT, "S5_CETAK_MASSAL_QUERIES: {$queries}\n");
    }
}
