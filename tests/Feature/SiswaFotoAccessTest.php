<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiswaFotoAccessTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    public function test_role_based_authorization_and_headers_on_media_siswa_foto(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true, 'force_password_change' => false]);
        $guruWali = User::factory()->create(['peran' => 'guru', 'aktif' => true, 'force_password_change' => false]);
        $guruRombel = User::factory()->create(['peran' => 'guru', 'aktif' => true, 'force_password_change' => false]);
        $guruJadwal = User::factory()->create(['peran' => 'guru', 'aktif' => true, 'force_password_change' => false]);
        $guruLain = User::factory()->create(['peran' => 'guru', 'aktif' => true, 'force_password_change' => false]);
        $kepsek = User::factory()->create(['peran' => 'kepsek', 'aktif' => true, 'force_password_change' => false]);
        $tendik = User::factory()->create(['peran' => 'tendik', 'aktif' => true, 'force_password_change' => false]);

        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        $rombel = Rombel::create([
            'dapodik_id' => 'rombel-test-4a',
            'semester_id' => $semester->id,
            'nama_rombel' => '4A',
            'tingkat' => 4,
            'guru_id' => $guruRombel->id,
        ]);

        JadwalPelajaran::create([
            'guru_id' => $guruJadwal->id,
            'rombel_id' => $rombel->id,
            'kelas' => '4A',
            'mata_pelajaran' => 'Matematika',
            'hari' => 'Senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '09:00',
        ]);

        $siswa = Siswa::create([
            'guru_id' => $guruWali->id,
            'rombel_id' => $rombel->id,
            'nis' => '20265501',
            'nama_peserta_didik' => 'Siswa Foto Satu',
            'jenis_kelamin' => 'L',
            'kelas' => '4A',
            'foto' => 'foto-siswa/abc1234567890.jpg', // sengaja .jpg tapi bytes PNG untuk uji deteksi MIME server
            'aktif' => true,
        ]);
        Storage::disk('local')->put($siswa->foto, $this->tinyPngBytes());

        $akunSiswaSendiri = User::factory()->create([
            'peran' => 'siswa',
            'nama_pengguna' => 'siswa20265501',
            'terhubung_dengan' => [$siswa->id],
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $akunSiswaLain = User::factory()->create([
            'peran' => 'siswa',
            'nama_pengguna' => 'siswa99999999',
            'terhubung_dengan' => [],
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $ortuTerhubung = User::factory()->create([
            'peran' => 'ortu',
            'terhubung_dengan' => [$siswa->id],
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $ortuLain = User::factory()->create([
            'peran' => 'ortu',
            'terhubung_dengan' => [],
            'aktif' => true,
            'force_password_change' => false,
        ]);

        $url = route('media.siswa.foto', $siswa);

        // Tamu ditolak (redirect ke login)
        $this->get($url)->assertRedirect(route('login'));

        // File di disk private TIDAK boleh dapat diakses lewat /storage/foto-siswa/<nama>
        $this->get('/storage/'.$siswa->foto)->assertNotFound();

        // Admin diizinkan + cek header ketat
        $res = $this->actingAs($admin)->get($url)->assertOk();
        $this->assertSame('image/png', $res->headers->get('Content-Type'));
        $cacheControl = (string) $res->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertSame('inline', $res->headers->get('Content-Disposition'));

        // Peran yang berhak
        $this->actingAs($akunSiswaSendiri)->get($url)->assertOk();
        $this->actingAs($ortuTerhubung)->get($url)->assertOk();
        $this->actingAs($guruWali)->get($url)->assertOk();
        $this->actingAs($guruRombel)->get($url)->assertOk();
        $this->actingAs($guruJadwal)->get($url)->assertOk();

        // Peran/relasi yang TIDAK berhak -> 403
        $this->actingAs($akunSiswaLain)->get($url)->assertForbidden();
        $this->actingAs($ortuLain)->get($url)->assertForbidden();
        $this->actingAs($guruLain)->get($url)->assertForbidden();
        $this->actingAs($kepsek)->get($url)->assertForbidden();
        $this->actingAs($tendik)->get($url)->assertForbidden();
    }

    public function test_cross_tenant_access_is_blocked_with_404(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $tenantA = Tenant::create(['npsn' => '51001111', 'nama_sekolah' => 'Sekolah Media A', 'subdomain' => 'media-a', 'aktif' => true]);
        $tenantB = Tenant::create(['npsn' => '51002222', 'nama_sekolah' => 'Sekolah Media B', 'subdomain' => 'media-b', 'aktif' => true]);

        $adminA = User::factory()->create([
            'peran' => 'admin',
            'tenant_id' => $tenantA->id,
            'tenant_owned' => true,
            'aktif' => true,
            'force_password_change' => false,
        ]);
        $siswaB = Siswa::create([
            'tenant_id' => $tenantB->id,
            'nis' => '51000002',
            'nama_peserta_didik' => 'Siswa Tenant B',
            'jenis_kelamin' => 'P',
            'kelas' => '5B',
            'foto' => 'foto-siswa/tenantb12345.png',
            'aktif' => true,
        ]);
        Storage::disk('local')->put($siswaB->foto, $this->tinyPngBytes());

        $this->actingAs($adminA)
            ->get(route('media.siswa.foto', $siswaB))
            ->assertNotFound();
    }

    public function test_invalid_paths_missing_file_and_public_fallback_flag(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true, 'force_password_change' => false]);
        $siswa = Siswa::create([
            'nis' => '20265599',
            'nama_peserta_didik' => 'Siswa Path Uji',
            'jenis_kelamin' => 'L',
            'kelas' => '4A',
            'foto' => null,
            'aktif' => true,
        ]);

        foreach (['../.env', 'foto-siswa/../x.jpg', 'foto-siswa/shell.php', 'foto-siswa/sub/a.png', ''] as $badPath) {
            $siswa->forceFill(['foto' => $badPath])->save();
            $this->actingAs($admin)->get(route('media.siswa.foto', $siswa))->assertNotFound();
        }

        // File hilang di kedua disk -> 404 bersih tanpa membocorkan path
        $siswa->forceFill(['foto' => 'foto-siswa/tidakada1234.png'])->save();
        $missing = $this->actingAs($admin)->get(route('media.siswa.foto', $siswa))->assertNotFound();
        $this->assertStringNotContainsString('tidakada1234.png', $missing->getContent());

        // Fallback public aktif vs nonaktif
        $siswa->forceFill(['foto' => 'foto-siswa/lama9999.png'])->save();
        Storage::disk('public')->put($siswa->foto, $this->tinyPngBytes());

        config(['simantap.foto_fallback_public' => true]);
        $this->actingAs($admin)->get(route('media.siswa.foto', $siswa))->assertOk();

        config(['simantap.foto_fallback_public' => false]);
        $this->actingAs($admin)->get(route('media.siswa.foto', $siswa))->assertNotFound();
    }
}
