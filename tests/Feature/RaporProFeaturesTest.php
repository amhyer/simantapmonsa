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

    public function test_pengaturan_naik_kelas_simpan_dan_tampil_di_rapor(): void
    {
        $tahun = date('Y') . '/' . (date('Y') + 1);
        $this->actingAs($this->admin)
            ->post(route('admin.rapor.naik-kelas.simpan'), [
                'kelas' => '6A',
                'semester' => 'Genap',
                'tahun_ajaran' => $tahun,
                'keputusan' => [
                    $this->siswa->id => ['naik_kelas' => '1', 'catatan' => 'Lulus baik'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kenaikan_kelas', [
            'siswa_id' => $this->siswa->id,
            'tahun_ajaran' => $tahun,
            'semester' => 'Genap',
            'naik_kelas' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cetak.nilai.show', ['siswa' => $this->siswa->id, 'semester' => 'Genap', 'tahun_ajaran' => $tahun]))
            ->assertOk()
            ->assertSee('Naik ke kelas berikutnya');
    }

    public function test_teknik_penilaian_dan_siswa_belajar(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.rapor.teknik-penilaian.simpan'), [
                'teknik' => ['Tes Tertulis', 'Proyek STEM'],
            ])
            ->assertRedirect(route('admin.rapor.teknik-penilaian'));

        $this->actingAs($this->admin)
            ->get(route('admin.rapor.teknik-penilaian'))
            ->assertOk()
            ->assertSee('Proyek STEM');

        $this->actingAs($this->admin)
            ->post(route('admin.rapor.siswa-belajar.simpan'), [
                'kelas' => '6A',
                'mapel_list' => ['Matematika', 'Bahasa Inggris'],
                'belajar' => [
                    $this->siswa->id => ['Matematika' => '1'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('siswa_belajar', [
            'siswa_id' => $this->siswa->id,
            'mata_pelajaran' => 'Matematika',
            'aktif' => true,
        ]);
        $this->assertDatabaseHas('siswa_belajar', [
            'siswa_id' => $this->siswa->id,
            'mata_pelajaran' => 'Bahasa Inggris',
            'aktif' => false,
        ]);
    }

    public function test_penilaian_p5bk_dan_cetak_pts_buku_induk_p5bk(): void
    {
        $tahun = date('Y') . '/' . (date('Y') + 1);
        NilaiErapot::create([
            'tenant_id' => $this->tenant->id,
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6A',
            'semester' => 'Ganjil',
            'tahun_ajaran' => $tahun,
            'formatif' => 84,
            'sumatif' => 88,
            'nilai_akhir' => 86,
            'predikat' => 'B',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.rapor.p5.simpan'), [
                'kelas' => '6A',
                'semester' => 'Ganjil',
                'tahun_ajaran' => $tahun,
                'tema' => 'Kearifan Lokal',
                'dimensi' => 'Kreatif',
                'nilai' => [
                    $this->siswa->id => ['predikat' => 'SB', 'catatan' => 'Sangat aktif'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('admin.cetak.pts.show', ['siswa' => $this->siswa->id, 'semester' => 'Ganjil', 'tahun_ajaran' => $tahun]))
            ->assertOk()
            ->assertSee('TENGAH SEMESTER (PTS)');

        $this->actingAs($this->admin)
            ->get(route('admin.cetak.buku-induk.show', $this->siswa))
            ->assertOk()
            ->assertSee('BUKU INDUK PESERTA DIDIK')
            ->assertSee('Matematika');

        $this->actingAs($this->admin)
            ->get(route('admin.cetak.p5bk.show', ['siswa' => $this->siswa->id, 'semester' => 'Ganjil', 'tahun_ajaran' => $tahun]))
            ->assertOk()
            ->assertSee('Kearifan Lokal')
            ->assertSee('SB');
    }

    public function test_forum_pengumuman_beranda_dan_grid_ttd_wali(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.forum.simpan'), [
                'judul' => 'Jadwal Rapat Rapor',
                'pesan' => 'Mohon lengkapi nilai sebelum Jumat.',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Jadwal Rapat Rapor');

        $this->actingAs($this->admin)
            ->get(route('admin.cetak.pengaturan-rapor'))
            ->assertOk()
            ->assertSee('Tanda Tangan Digital per Wali Kelas')
            ->assertSee($this->guru->nama_lengkap);
    }
}
