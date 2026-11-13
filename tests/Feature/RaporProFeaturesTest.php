<?php

namespace Tests\Feature;

use App\Models\NilaiErapot;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaporProFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $guru;
    private Rombel $rombel;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'npsn' => '40301234',
            'nama_sekolah' => 'SD Negeri Uji Pro',
            'kode_sekolah' => 'sd-uji-pro',
            'aktif' => true,
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'aktif' => true,
        ]);

        $this->guru = User::factory()->guru()->create([
            'tenant_id' => $this->tenant->id,
            'aktif' => true,
        ]);

        $semester = Semester::create([
            'tenant_id' => $this->tenant->id,
            'semester_id' => '20261',
            'nama_semester' => 'ganjil',
            'tahun_ajaran' => '2026/2027',
            'aktif' => true,
        ]);

        $this->rombel = Rombel::create([
            'tenant_id' => $this->tenant->id,
            'dapodik_id' => 'RB-PRO-6A',
            'semester_id' => $semester->id,
            'nama_rombel' => '6A',
            'tingkat' => 6,
            'guru_id' => $this->guru->id,
            'kunci_input_rapor' => false,
        ]);

        $this->siswa = Siswa::create([
            'tenant_id' => $this->tenant->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'guru_id' => $this->guru->id,
            'nama_guru' => $this->guru->nama_lengkap,
            'rombel_id' => $this->rombel->id,
            'nis' => '2026001',
            'nisn' => '0099887766',
            'nama_peserta_didik' => 'Alya Putri',
            'kelas' => '6A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
    }

    public function test_matriks_kelengkapan_nilai_akhir_tampil_dengan_persentase(): void
    {
        NilaiErapot::create([
            'tenant_id' => $this->tenant->id,
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'semester' => 'Ganjil',
            'tahun_ajaran' => date('Y') . '/' . (date('Y') + 1),
            'nilai_akhir' => 88.5,
            'predikat' => 'B',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cetak.kelengkapan', ['kelas' => '6A', 'semester' => 'Ganjil']))
            ->assertOk()
            ->assertSee('Matriks Kelengkapan')
            ->assertSee('Alya Putri')
            ->assertSee('Matematika')
            ->assertSee('100.0%');
    }

    public function test_pengaturan_rapor_tersimpan_dan_diterapkan_ke_cetak_nilai(): void
    {
        SekolahSettings::create([
            'tenant_id' => $this->tenant->id,
            'nama_sekolah' => 'SD Negeri Uji Pro',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cetak.pengaturan-rapor.simpan'), [
                'kementerian' => 'Kemendikbudristek',
                'ukuran_kertas' => 'F4',
                'margin_atas_cm' => 2.0,
                'kapitalisasi_nama' => 'upper',
                'format_desimal' => '2',
                'page_break_catatan' => '1',
            ])
            ->assertRedirect(route('admin.cetak.pengaturan-rapor'));

        NilaiErapot::create([
            'tenant_id' => $this->tenant->id,
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'semester' => 'Ganjil',
            'tahun_ajaran' => date('Y') . '/' . (date('Y') + 1),
            'nilai_akhir' => 88.5,
            'predikat' => 'B',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cetak.nilai.show', $this->siswa))
            ->assertOk()
            ->assertSee('KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI')
            ->assertSee('ALYA PUTRI')
            ->assertSee('88,50');
    }

    public function test_kunci_input_rapor_memblokir_simpan_nilai_dan_catatan_serta_batas_karakter(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.peta-kelas.toggle-kunci', $this->rombel))
            ->assertRedirect();

        $this->assertTrue($this->rombel->fresh()->kunci_input_rapor);

        $this->actingAs($this->guru)
            ->postJson(route('guru.nilai-erapor.auto-save'), [
                'siswa_id' => $this->siswa->id,
                'field' => 'formatif',
                'value' => 90,
                'kelas' => '6A',
                'mapel' => 'Matematika',
                'semester' => 'Ganjil',
                'tahun_ajaran' => '2026/2027',
            ])
            ->assertStatus(423);

        $this->actingAs($this->guru)
            ->post(route('guru.catatan.store'), [
                'siswa_id' => $this->siswa->id,
                'tanggal' => now()->format('Y-m-d'),
                'jenis' => 'umum',
                'catatan' => 'Catatan saat terkunci',
            ])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post(route('admin.peta-kelas.toggle-kunci', $this->rombel))
            ->assertRedirect();

        $this->actingAs($this->guru)
            ->post(route('guru.catatan.store'), [
                'siswa_id' => $this->siswa->id,
                'tanggal' => now()->format('Y-m-d'),
                'jenis' => 'umum',
                'catatan' => str_repeat('a', 1001),
            ])
            ->assertSessionHasErrors('catatan');
    }

    public function test_admin_dapat_impersonasi_guru_se_tenant_dan_kembali_ke_admin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $this->guru))
            ->assertRedirect(route('guru.dashboard'));

        $this->assertSame($this->guru->id, auth()->id());
        $this->assertSame($this->admin->id, session('admin_impersonator_id'));

        $this->post(route('admin.impersonate.leave'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame($this->admin->id, auth()->id());
        $this->assertFalse(session()->has('admin_impersonator_id'));

        $otherTenant = Tenant::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'npsn' => '40309999',
            'nama_sekolah' => 'SD Lain',
            'kode_sekolah' => 'sd-lain',
            'aktif' => true,
        ]);
        $otherGuru = User::factory()->guru()->create(['tenant_id' => $otherTenant->id, 'aktif' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.users.impersonate', $otherGuru))
            ->assertForbidden();
    }
}
