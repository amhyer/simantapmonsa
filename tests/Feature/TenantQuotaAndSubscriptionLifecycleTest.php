<?php

namespace Tests\Feature;

use App\Mail\SchoolRegistrationStatusMail;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\PembelajaranMendalam\GeminiPembelajaranService;
use App\Services\Tenant\TenantQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantQuotaAndSubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function buatSekolah(string $npsn, array $tenantOverrides = [], ?string $adminEmail = null): array
    {
        $tenant = Tenant::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'npsn' => $npsn,
            'nama_sekolah' => 'Sekolah Uji '.$npsn,
            'subdomain' => 'uji-'.strtolower($npsn),
            'paket' => 'standar',
            'kuota_siswa' => 500,
            'kuota_storage_mb' => 2048,
            'aktif' => true,
            'meta' => ['jenjang' => 'SMP'],
        ], $tenantOverrides));

        $admin = User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'tenant_owned' => true,
            'email' => $adminEmail ?? "admin-{$npsn}@sekolah.test",
        ]);

        TenantContext::run($tenant->id, fn () => SekolahSettings::create([
            'tenant_id' => $tenant->id,
            'guru_id' => $admin->id,
            'nama_sekolah' => $tenant->nama_sekolah,
            'npsn' => $npsn,
            'jenjang' => 'SMP',
        ]));

        $semester = TenantContext::run($tenant->id, fn () => Semester::create([
            'tenant_id' => $tenant->id,
            'semester_id' => '20261',
            'nama_semester' => 'ganjil',
            'tahun_ajaran' => '2026/2027',
        ]));

        return [$tenant, $admin, $semester];
    }

    public function test_kuota_siswa_ditegakkan_saat_penambahan_siswa_namun_tidak_menghalangi_akun_guru(): void
    {
        config(['simantap.multi_tenant_enabled' => true]);
        [$tenant, $admin] = $this->buatSekolah('83000001', ['kuota_siswa' => 10]);

        $guru = User::factory()->guru()->create([
            'tenant_id' => $tenant->id,
            'tenant_owned' => true,
            'is_wali_kelas' => true,
        ]);

        // Isi tepat 10 siswa sesuai batas kuota_siswa = 10
        TenantContext::run($tenant->id, function () use ($tenant, $guru): void {
            for ($i = 1; $i <= 10; $i++) {
                Siswa::create([
                    'tenant_id' => $tenant->id,
                    'guru_id' => $guru->id,
                    'nama_guru' => $guru->nama_lengkap,
                    'nis' => 'NIS-'.$i,
                    'nisn' => str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                    'nama_peserta_didik' => "Siswa {$i}",
                    'kelas' => '7A',
                    'jenis_kelamin' => 'L',
                    'aktif' => true,
                ]);
            }
        });

        // Penambahan siswa ke-11 melalui form guru ditolak dengan error validasi kuota_siswa
        $this->actingAs($guru)
            ->from(route('guru.siswa.create'))
            ->post(route('guru.siswa.store'), [
                'nama' => 'Siswa Ke-11',
                'nis' => 'NIS-11',
                'jenis_kelamin' => 'P',
                'kelas' => '7A',
            ])
            ->assertRedirect(route('guru.siswa.create'))
            ->assertSessionHasErrors('kuota_siswa');

        $this->assertSame(10, Siswa::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // Pembuatan akun siswa ke-11 melalui Admin juga ditolak oleh middleware EnforceTenantQuota
        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'nama_lengkap' => 'Akun Siswa 11',
                'nama_pengguna' => 'siswa11baru',
                'kata_sandi' => 'PasswordAman123!',
                'peran' => 'siswa',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('kuota_siswa');
    }

    public function test_kuota_storage_terisolasi_antar_tenant_dan_menolak_unggahan_melebihi_batas(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['simantap.multi_tenant_enabled' => true]);

        [$tenantA, $adminA] = $this->buatSekolah('83000002', ['kuota_storage_mb' => 100]);
        [$tenantB] = $this->buatSekolah('83000003', ['kuota_storage_mb' => 100]);

        // Simpan berkas 99.5 MB pada direktori elearning Tenant A
        Storage::disk('local')->put(
            "elearning/tenant-{$tenantA->id}/materi/2026/11/besar.bin",
            str_repeat('A', 99 * 1048576 + 600000)
        );

        $quota = app(TenantQuotaService::class);
        $statusA = $quota->storageQuotaStatus($tenantA->id, $tenantA, true);
        $statusB = $quota->storageQuotaStatus($tenantB->id, $tenantB, true);

        $this->assertGreaterThan(99.0, $statusA['used_mb']);
        $this->assertSame(0.0, $statusB['used_mb']);

        // Unggahan 1 MB tambahan ke Tenant A melebihi 100 MB -> melempar ValidationException
        $this->expectException(ValidationException::class);
        $quota->assertCanStoreBytes($tenantA->id, 1048576, 'logo_sekolah');
    }

    public function test_middleware_menolak_upload_http_saat_kuota_storage_sekolah_penuh(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['simantap.multi_tenant_enabled' => true]);

        [$tenant, $admin] = $this->buatSekolah('83000004', ['kuota_storage_mb' => 100]);
        Storage::disk('local')->put(
            "elearning/tenant-{$tenant->id}/materi/2026/11/penuh.bin",
            str_repeat('X', 100 * 1048576)
        );

        $file = UploadedFile::fake()->image('logo.png', 200, 200)->size(512);

        $this->actingAs($admin)
            ->from(route('admin.referensi.logo-ttd'))
            ->post(route('admin.referensi.logo-ttd.store'), [
                'logo_sekolah' => $file,
            ])
            ->assertRedirect(route('admin.referensi.logo-ttd'))
            ->assertSessionHasErrors('logo_sekolah');
    }

    public function test_plafon_kuota_ai_harian_dibatasi_untuk_shared_key_dan_bebas_untuk_byok(): void
    {
        config([
            'simantap.multi_tenant_enabled' => true,
            'services.gemini.api_key' => 'PLATFORM-SHARED-GEMINI-KEY',
        ]);

        [$tenantStandar] = $this->buatSekolah('83000005', [
            'paket' => 'standar',
            'meta' => ['jenjang' => 'SMP', 'kuota_ai_harian' => 60],
        ]);

        // Sekolah mencoba menaikkan sendiri gemini_daily_quota = 500 tanpa mengisi API key milik sendiri
        SekolahSettings::withoutGlobalScopes()
            ->where('tenant_id', $tenantStandar->id)
            ->firstOrFail()
            ->update(['pengaturan' => ['gemini_daily_quota' => 500]]);

        $gemini = app(GeminiPembelajaranService::class);
        $sharedStatus = TenantContext::run($tenantStandar->id, fn () => $gemini->quotaStatus($tenantStandar->id));
        $this->assertSame(60, $sharedStatus['limit']);

        // Bila sekolah memakai API key sendiri (BYOK), kuota 500 diizinkan penuh
        SekolahSettings::withoutGlobalScopes()
            ->where('tenant_id', $tenantStandar->id)
            ->firstOrFail()
            ->update(['pengaturan' => [
                'gemini_api_key' => 'SCHOOL-OWN-GEMINI-KEY',
                'gemini_daily_quota' => 500,
            ]]);

        $byokStatus = TenantContext::run($tenantStandar->id, fn () => $gemini->quotaStatus($tenantStandar->id));
        $this->assertSame(500, $byokStatus['limit']);
    }

    public function test_siklus_langganan_402_logout_aman_perpanjangan_superadmin_dan_pengingat_idempoten(): void
    {
        Mail::fake();
        config([
            'simantap.multi_tenant_enabled' => true,
            'simantap.school_registration_trial_days' => 30,
        ]);

        // 1. Sekolah kedaluwarsa menerima 402 pada dashboard, tetapi tetap bisa POST /logout
        [$expiredTenant, $expiredAdmin] = $this->buatSekolah(
            '83000006',
            ['tgl_kedaluwarsa' => now()->subDay()->toDateString()],
            'operator-expired@sekolah.test'
        );

        $this->actingAs($expiredAdmin)
            ->get(route('admin.dashboard'))
            ->assertStatus(402)
            ->assertSee('Masa Berlangganan Berakhir');

        $this->actingAs($expiredAdmin)
            ->post(route('logout'))
            ->assertRedirect();
        $this->assertGuest();

        // 2. Super Admin memperpanjang langganan +6 bulan -> email terkirim & akses pulih 200 OK
        $superAdmin = User::factory()->create([
            'peran' => 'super_admin',
            'aktif' => true,
            'tenant_id' => null,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.extend', $expiredTenant->uuid), [
                'months' => 6,
                'catatan_langganan' => 'Perpanjangan semester baru',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $expiredTenant->refresh();
        $this->assertFalse($expiredTenant->isExpired());
        $this->assertSame(
            now()->startOfDay()->addMonthsNoOverflow(6)->toDateString(),
            $expiredTenant->tgl_kedaluwarsa?->toDateString()
        );

        Mail::assertSent(
            SchoolRegistrationStatusMail::class,
            fn (SchoolRegistrationStatusMail $m): bool => $m->tenant->id === $expiredTenant->id && $m->status === 'subscription_renewed'
        );

        $this->actingAs($expiredAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        // 3. Pengingat jatuh tempo H-7 terkirim tepat 1x (idempoten pada eksekusi ulang)
        [$soonTenant] = $this->buatSekolah(
            '83000007',
            ['tgl_kedaluwarsa' => now()->addDays(7)->toDateString()],
            'operator-h7@sekolah.test'
        );

        Artisan::call('tenants:send-expiry-reminders', ['--days' => '14,7,3,1']);

        Mail::assertSent(
            SchoolRegistrationStatusMail::class,
            fn (SchoolRegistrationStatusMail $m): bool => $m->tenant->id === $soonTenant->id && $m->status === 'subscription_expiring'
        );

        $sentCountBeforeSecondRun = count(Mail::sent(SchoolRegistrationStatusMail::class));
        Artisan::call('tenants:send-expiry-reminders', ['--days' => '14,7,3,1']);
        $this->assertCount($sentCountBeforeSecondRun, Mail::sent(SchoolRegistrationStatusMail::class));
    }
}
