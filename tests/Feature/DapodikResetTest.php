<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\CbtQuestionBank;
use App\Models\Materi;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DapodikResetTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_admin_sekolah_dapat_mengosongkan_seluruh_data_sekolahnya_dan_hanya_menyisakan_akun_admin(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Negeri 1',
            'npsn' => '11111111',
            'subdomain' => 'sdn1',
            'aktif' => true,
        ]);

        $tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Negeri 2',
            'npsn' => '22222222',
            'subdomain' => 'sdn2',
            'aktif' => true,
        ]);

        $adminA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $apiKeyAdminA = ApiKey::generate($adminA->id, 'Bridge SDN 1')['model'];

        $guruDapodikA = User::factory()->guru()->create([
            'tenant_id' => $tenantA->id,
            'dapodik_id' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Dapodik A',
        ]);
        $guruManualA = User::factory()->guru()->create([
            'tenant_id' => $tenantA->id,
            'dapodik_id' => null,
            'nama_lengkap' => 'Guru Manual A',
        ]);
        $ortuA = User::factory()->ortu()->create([
            'tenant_id' => $tenantA->id,
            'nama_lengkap' => 'Orang Tua A',
        ]);
        $kepsekA = User::factory()->kepsek()->create([
            'tenant_id' => $tenantA->id,
            'nama_lengkap' => 'Kepsek A',
        ]);
        $userSiswaA = User::factory()->siswa()->create([
            'tenant_id' => $tenantA->id,
            'nama_pengguna' => '1001',
            'nama_lengkap' => 'Siswa A',
        ]);
        $userTrashedA = User::factory()->guru()->create([
            'tenant_id' => $tenantA->id,
            'nama_lengkap' => 'Guru Trashed A',
        ]);
        $userTrashedA->delete();

        $guruDapodikB = User::factory()->guru()->create([
            'tenant_id' => $tenantB->id,
            'dapodik_id' => (string) Str::uuid(),
            'nama_lengkap' => 'Guru Dapodik B',
        ]);
        $guruManualB = User::factory()->guru()->create([
            'tenant_id' => $tenantB->id,
            'dapodik_id' => null,
            'nama_lengkap' => 'Guru Manual B',
        ]);

        $sem = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);

        // Data Sekolah A
        TenantContext::set($tenantA->id);
        $settingsA = SekolahSettings::create([
            'tenant_id' => $tenantA->id,
            'npsn' => '11111111',
            'nama_sekolah' => 'SD Negeri 1',
        ]);
        $ptkA = Ptk::create([
            'tenant_id' => $tenantA->id,
            'semester_id' => $sem->id,
            'nama' => 'Guru Dapodik A',
            'jenis_ptk' => 'Guru Kelas',
        ]);
        $rombelA = Rombel::create([
            'tenant_id' => $tenantA->id,
            'semester_id' => $sem->id,
            'dapodik_id' => (string) Str::uuid(),
            'nama_rombel' => 'Kelas 1.A',
            'tingkat' => '1',
        ]);
        $siswaA = Siswa::create([
            'tenant_id' => $tenantA->id,
            'semester_id' => $sem->id,
            'dapodik_id' => (string) Str::uuid(),
            'rombel_id' => $rombelA->id,
            'uuid' => (string) Str::uuid(),
            'nis' => '1001',
            'nisn' => '1111111111',
            'nama_peserta_didik' => 'Siswa A',
            'kelas' => 'Kelas 1.A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $siswaTrashedA = Siswa::create([
            'tenant_id' => $tenantA->id,
            'semester_id' => $sem->id,
            'uuid' => (string) Str::uuid(),
            'nis' => '1002',
            'nisn' => '1111111112',
            'nama_peserta_didik' => 'Siswa Trashed A',
            'kelas' => 'Kelas 1.A',
            'jenis_kelamin' => 'P',
            'aktif' => false,
        ]);
        $siswaTrashedA->delete();

        $materiA = Materi::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'guru_id' => $guruManualA->id,
            'nama_guru' => $guruManualA->nama_lengkap,
            'judul' => 'Materi Matematika',
            'mata_pelajaran' => 'Matematika',
            'kelas' => 'Kelas 1.A',
            'tujuan_pembelajaran' => 'Memahami penjumlahan dasar',
            'materi_pokok' => 'Konten materi penjumlahan',
            'tanggal' => now()->toDateString(),
        ]);
        $bankA = CbtQuestionBank::create([
            'tenant_id' => $tenantA->id,
            'guru_id' => $guruManualA->id,
            'title' => 'Bank Soal A',
            'subject' => 'Matematika',
        ]);

        // Data Sekolah B
        TenantContext::set($tenantB->id);
        $rombelB = Rombel::create([
            'tenant_id' => $tenantB->id,
            'semester_id' => $sem->id,
            'dapodik_id' => (string) Str::uuid(),
            'nama_rombel' => 'Kelas 1.B',
            'tingkat' => '1',
        ]);
        $siswaB = Siswa::create([
            'tenant_id' => $tenantB->id,
            'semester_id' => $sem->id,
            'dapodik_id' => (string) Str::uuid(),
            'rombel_id' => $rombelB->id,
            'uuid' => (string) Str::uuid(),
            'nis' => '2001',
            'nisn' => '2222222222',
            'nama_peserta_didik' => 'Siswa B',
            'kelas' => 'Kelas 1.B',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);

        // Request reset dengan teks konfirmasi salah -> harus gagal validasi
        TenantContext::set($tenantA->id);
        $resFail = $this->actingAs($adminA)->post(route('admin.dapodik.reset'), [
            'konfirmasi' => 'SALAH',
            'password' => 'password',
        ]);
        $resFail->assertSessionHasErrors('konfirmasi');

        // Request reset valid
        $resSuccess = $this->actingAs($adminA)->post(route('admin.dapodik.reset'), [
            'konfirmasi' => 'KOSONGKAN DATA DAPODIK',
            'password' => 'password',
        ]);
        $resSuccess->assertRedirect(route('admin.dapodik.index'));
        $resSuccess->assertSessionHas('success');

        // Pastikan SELURUH data & akun non-admin Sekolah A kosong (termasuk akun manual & soft-deleted)
        $this->assertDatabaseMissing('siswa', ['id' => $siswaA->id]);
        $this->assertDatabaseMissing('siswa', ['id' => $siswaTrashedA->id]);
        $this->assertDatabaseMissing('rombel', ['id' => $rombelA->id]);
        $this->assertDatabaseMissing('ptk', ['id' => $ptkA->id]);
        $this->assertDatabaseMissing('sekolah_settings', ['id' => $settingsA->id]);
        $this->assertDatabaseMissing('materi', ['id' => $materiA->id]);
        $this->assertDatabaseMissing('cbt_question_banks', ['id' => $bankA->id]);
        $this->assertDatabaseMissing('users', ['id' => $guruDapodikA->id]);
        $this->assertDatabaseMissing('users', ['id' => $guruManualA->id]);
        $this->assertDatabaseMissing('users', ['id' => $ortuA->id]);
        $this->assertDatabaseMissing('users', ['id' => $kepsekA->id]);
        $this->assertDatabaseMissing('users', ['id' => $userSiswaA->id]);
        $this->assertDatabaseMissing('users', ['id' => $userTrashedA->id]);

        // Pastikan HANYA Admin Sekolah A (+ API Key Bridge miliknya) yang tinggal di Sekolah A
        $this->assertDatabaseHas('users', ['id' => $adminA->id]);
        $this->assertSame(1, User::withTrashed()->where('tenant_id', $tenantA->id)->count());
        $this->assertDatabaseHas('api_keys', ['id' => $apiKeyAdminA->id]);

        // Pastikan Sekolah B TETAP UTUH 100%
        $this->assertDatabaseHas('siswa', ['id' => $siswaB->id]);
        $this->assertDatabaseHas('rombel', ['id' => $rombelB->id]);
        $this->assertDatabaseHas('users', ['id' => $guruDapodikB->id]);
        $this->assertDatabaseHas('users', ['id' => $guruManualB->id]);
    }

    public function test_menu_admin_tersembunyi_saat_kosong_dan_muncul_sesuai_data_dapodik_yang_ditarik(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Negeri Dinamis',
            'npsn' => '33333333',
            'subdomain' => 'sdndinamis',
            'aktif' => true,
        ]);

        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $plainKey = ApiKey::generate($admin->id, 'Bridge SDN Dinamis')['plaintext_key'];

        $extractSidebar = function (string $html): string {
            preg_match('/<nav class="sidebar-nav">(.*?)<\/nav>/s', $html, $m);

            return $m[1] ?? '';
        };
        $extractTabs = function (string $html): string {
            preg_match('/<div class="admin-tabs"[^>]*>(.*?)<\/div>/s', $html, $m);

            return $m[1] ?? '';
        };
        $pushDapodik = function (string $modul, array $data) use ($plainKey) {
            return $this->withHeaders(['X-API-Key' => $plainKey])->postJson('/api/dapodik/sync/'.$modul, [
                'semester_id' => '20261',
                'tahun_ajaran' => '2026/2027',
                'nama_semester' => 'ganjil',
                'data' => $data,
            ])->assertOk();
        };

        // 1. Kondisi awal kosong: hanya menu inti Admin yang tampil, menu tanpa data disembunyikan
        TenantContext::set($tenant->id);
        $htmlKosong = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
        $sidebarKosong = $extractSidebar($htmlKosong);
        $tabsKosong = $extractTabs($htmlKosong);

        $this->assertStringContainsString('Dasbor', $sidebarKosong);
        $this->assertStringContainsString('Dapodik &amp; Impor Data', $sidebarKosong);
        $this->assertStringContainsString('Data Pengguna (Manajemen Akun)', $sidebarKosong);
        $this->assertStringContainsString('Kunci API Bridge', $sidebarKosong);

        $this->assertStringNotContainsString('Profil Sekolah', $sidebarKosong);
        $this->assertStringNotContainsString('Data Guru &amp; PTK', $sidebarKosong);
        $this->assertStringNotContainsString('Data Siswa', $sidebarKosong);
        $this->assertStringNotContainsString('Rombel &amp; Peta Kelas', $sidebarKosong);
        $this->assertStringNotContainsString('Mata Pelajaran', $sidebarKosong);
        $this->assertStringNotContainsString('Data Pembelajaran', $sidebarKosong);
        $this->assertStringNotContainsString('Laporan Presensi', $sidebarKosong);
        $this->assertStringNotContainsString('Daftar Tema', $sidebarKosong);
        $this->assertStringNotContainsString('Kirim Nilai Ke Dapodik', $sidebarKosong);
        $this->assertStringNotContainsString('E-Rapor', $tabsKosong);

        // 2. Tarik modul Sekolah (termasuk saat baris SekolahSettings awal sudah ada tanpa NPSN) -> Profil Sekolah & Semester muncul tanpa baris ganda
        TenantContext::set($tenant->id);
        SekolahSettings::create([
            'tenant_id' => $tenant->id,
            'guru_id' => $admin->id,
            'nama_sekolah' => 'Draft Sekolah',
            'npsn' => null,
        ]);
        $pushDapodik('sekolah', [['npsn' => '33333333', 'nama' => 'SD Negeri Dinamis']]);
        TenantContext::set($tenant->id);
        $this->assertSame(1, SekolahSettings::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame('SD Negeri Dinamis', SekolahSettings::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('nama_sekolah'));
        $sidebarSekolah = $extractSidebar($this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent());
        $this->assertStringContainsString('Profil Sekolah', $sidebarSekolah);
        $this->assertStringContainsString('Data Semester &amp; Tahun Ajaran', $sidebarSekolah);
        $this->assertStringNotContainsString('Data Guru &amp; PTK', $sidebarSekolah);
        $this->assertStringNotContainsString('Data Siswa', $sidebarSekolah);

        // 3. Tarik modul PTK -> Data Guru & PTK, Kode Akses, dan Presensi muncul
        $pushDapodik('ptk', [[
            'ptk_id' => 'ptk-dinamis-1',
            'nama' => 'Budi Santoso',
            'nip' => '198501012010011001',
            'jenis_ptk' => 'Guru Kelas',
        ]]);
        TenantContext::set($tenant->id);
        $sidebarPtk = $extractSidebar($this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent());
        $this->assertStringContainsString('Data Guru &amp; PTK', $sidebarPtk);
        $this->assertStringContainsString('Cetak Kode Akses Login', $sidebarPtk);
        $this->assertStringContainsString('Laporan Presensi', $sidebarPtk);
        $this->assertStringNotContainsString('Data Siswa', $sidebarPtk);

        // 4. Tarik modul Rombel & Mata Pelajaran & Pembelajaran -> Kelas, Mapel, Pembelajaran muncul
        $pushDapodik('rombongan-belajar', [[
            'rombongan_belajar_id' => 'rombel-dinamis-1',
            'nama' => 'Kelas 1A',
            'tingkat' => '1',
        ]]);
        $pushDapodik('mata-pelajaran', [[
            'kode' => 'MTK',
            'nama' => 'Matematika',
        ]]);
        $pushDapodik('pembelajaran', [[
            'nama_mata_pelajaran' => 'Matematika',
            'nama_rombel' => 'Kelas 1A',
            'ptk_id' => 'ptk-dinamis-1',
            'jam_mengajar_per_minggu' => 4,
        ]]);
        TenantContext::set($tenant->id);
        $sidebarKelas = $extractSidebar($this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent());
        $this->assertStringContainsString('Rombel &amp; Peta Kelas', $sidebarKelas);
        $this->assertStringContainsString('Mata Pelajaran', $sidebarKelas);
        $this->assertStringContainsString('Data Kelompok Mapel', $sidebarKelas);
        $this->assertStringContainsString('Data Pembelajaran', $sidebarKelas);

        // 5. Tarik modul Peserta Didik -> Data Siswa, Foto Siswa, Kirim Nilai, Kokurikuler, dan Tab E-Rapor muncul
        $pushDapodik('peserta-didik', [[
            'peserta_didik_id' => 'siswa-dinamis-1',
            'nama' => 'Ani Lestari',
            'nisn' => '0011223344',
            'nis' => '1001',
            'nama_rombel' => 'Kelas 1A',
        ]]);
        TenantContext::set($tenant->id);
        $htmlLengkap = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
        $sidebarLengkap = $extractSidebar($htmlLengkap);
        $tabsLengkap = $extractTabs($htmlLengkap);

        $this->assertStringContainsString('Data Siswa', $sidebarLengkap);
        $this->assertStringContainsString('Foto Siswa', $sidebarLengkap);
        $this->assertStringContainsString('Kirim Nilai Ke Dapodik', $sidebarLengkap);
        $this->assertStringContainsString('Daftar Tema', $sidebarLengkap);
        $this->assertStringContainsString('E-Rapor', $tabsLengkap);

        // 6. Kosongkan Data kembali -> semua menu data kembali disembunyikan, hanya menu inti Admin yang tinggal
        $this->actingAs($admin)->post(route('admin.dapodik.reset'), [
            'konfirmasi' => 'KOSONGKAN DATA DAPODIK',
            'password' => 'password',
        ])->assertRedirect(route('admin.dapodik.index'));

        $htmlSetelahReset = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
        $sidebarSetelahReset = $extractSidebar($htmlSetelahReset);
        $tabsSetelahReset = $extractTabs($htmlSetelahReset);

        $this->assertStringNotContainsString('Data Siswa', $sidebarSetelahReset);
        $this->assertStringNotContainsString('Data Guru &amp; PTK', $sidebarSetelahReset);
        $this->assertStringNotContainsString('Rombel &amp; Peta Kelas', $sidebarSetelahReset);
        $this->assertStringNotContainsString('E-Rapor', $tabsSetelahReset);
        $this->assertStringContainsString('Dapodik &amp; Impor Data', $sidebarSetelahReset);
    }

    public function test_admin_sekolah_dapat_melihat_halaman_dan_mengunduh_bridge(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'nama_sekolah' => 'SD Negeri 1',
            'npsn' => '11111111',
            'subdomain' => 'sdn1',
            'aktif' => true,
        ]);

        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        // Tombol unduh hanya dirender bila artefak bridge tersedia, jadi fixture
        // harus dibuat SEBELUM halaman index diminta.
        $bridgePath = public_path('bridge/SIMANTAP-Bridge.exe');
        $created = ! is_file($bridgePath);
        if ($created) {
            File::ensureDirectoryExists(dirname($bridgePath));
            file_put_contents($bridgePath, 'bridge-test-fixture');
        }

        try {
            $resIndex = $this->actingAs($admin)->get(route('admin.dapodik.index'));
            $resIndex->assertOk();
            $resIndex->assertSee('Aplikasi Desktop SIMANTAP Bridge');
            $resIndex->assertSee(route('admin.dapodik.download-bridge'));

            $resDownload = $this->actingAs($admin)->get(route('admin.dapodik.download-bridge'));
            $resDownload->assertOk();
            $this->assertStringContainsString('SIMANTAP-Bridge.exe', $resDownload->headers->get('content-disposition', ''));
        } finally {
            if ($created && is_file($bridgePath)) {
                unlink($bridgePath);
            }
        }
    }
}
