<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\Kehadiran;
use App\Models\PengajuanIzinPresensi;
use App\Models\PresensiLokasi;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Presensi\IdentityCardService;
use App\Services\PresensiMesinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PresensiSecurityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['simantap.multi_tenant_enabled' => true]);
        $this->travelTo(Carbon::parse('2026-10-19 08:30:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function school(string $npsn): Tenant
    {
        return Tenant::create(['uuid' => Str::uuid(), 'npsn' => $npsn, 'nama_sekolah' => 'Sekolah '.$npsn, 'subdomain' => 'tes-'.$npsn, 'aktif' => true]);
    }

    private function classAndStudent(Tenant $school, User $wali): array
    {
        $semester = Semester::firstOrCreate(['semester_id' => '20261'], ['tahun_ajaran' => '2026/2027', 'nama_semester' => 'ganjil']);
        return TenantContext::run($school->id, function () use ($school, $wali, $semester) {
            $rombel = Rombel::create(['dapodik_id' => 'R-'.$school->id, 'semester_id' => $semester->id, 'nama_rombel' => '6.A', 'guru_id' => $wali->id]);
            $student = Siswa::create(['guru_id' => $wali->id, 'rombel_id' => $rombel->id, 'nama_peserta_didik' => 'Anak '.$school->id, 'nis' => 'NIS-'.$school->id, 'kelas' => '6.A', 'jenis_kelamin' => 'L', 'aktif' => true]);
            $jadwal = JadwalPelajaran::create(['guru_id' => $wali->id, 'semester_id' => $semester->id, 'rombel_id' => $rombel->id, 'kelas' => '6.A', 'mata_pelajaran' => 'Bahasa Inggris', 'hari' => now()->locale('id')->translatedFormat('l'), 'jam_mulai' => '08:00', 'jam_selesai' => '09:00']);
            return [$student, $jadwal];
        });
    }

    public function test_admin_dan_impor_mesin_tidak_mengakses_pegawai_sekolah_lain(): void
    {
        $a = $this->school('10000001');
        $b = $this->school('10000002');
        $admin = User::factory()->admin()->create(['tenant_id' => $a->id]);
        $local = User::factory()->guru()->create(['tenant_id' => $a->id, 'nama_lengkap' => 'Guru Sekolah A', 'nik' => 'PIN-A']);
        $other = User::factory()->guru()->create(['tenant_id' => $b->id, 'nama_lengkap' => 'Guru Rahasia Sekolah B', 'nik' => 'PIN-B']);

        [$foreignStudent] = $this->classAndStudent($b, $other);
        $this->actingAs($admin)->post(route('admin.presensi.kartu.siswa.issue', $foreignStudent->uuid))->assertNotFound();
        $this->actingAs($admin)->postJson(route('admin.presensi.kartu.bind', $foreignStudent->id), ['qr_payload' => 'FOREIGN'])->assertNotFound();
        $this->actingAs($admin)->get(route('admin.presensi.index'))->assertOk()->assertSee('Guru Sekolah A')->assertDontSee('Guru Rahasia Sekolah B');
        $this->actingAs($admin)->get(route('admin.presensi.kartu.index', ['jenis' => 'pegawai']))->assertOk()->assertDontSee('Guru Rahasia Sekolah B');
        $this->actingAs($admin)->post(route('admin.presensi.kartu.pegawai.issue', $other->uuid))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.presensi.kartu.pegawai.cetak', $other->uuid))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.presensi.pegawai.koreksi'), [
            'user_id' => $other->id, 'tanggal' => now()->toDateString(), 'status' => 'hadir', 'alasan_koreksi' => 'Uji',
        ])->assertNotFound();
        $this->actingAs($admin)->post(route('admin.presensi.mesin.tarik'), [])->assertStatus(503);
        $this->actingAs($admin)->post(route('admin.presensi.mesin.karyawan.tarik'))->assertStatus(503);
        $this->actingAs($admin)->post(route('admin.presensi.mesin.karyawan.import'), [
            'file' => UploadedFile::fake()->createWithContent('karyawan.csv', "nik,nama\nPIN-X,Guru Rahasia Sekolah B\n"),
        ])->assertRedirect();
        $this->assertSame('PIN-B', $other->fresh()->nik);

        $results = TenantContext::run($a->id, fn () => app(PresensiMesinService::class)->importScans([
            ['nik' => 'PIN-B', 'waktu' => now(), 'jenis' => 'masuk'],
            ['nik' => 'PIN-A', 'waktu' => now(), 'jenis' => 'masuk'],
        ], $admin->id));
        $this->assertSame(1, $results['nik_tidak_dikenal']);
        $this->assertDatabaseHas('presensi_pegawai', ['user_id' => $local->id, 'tenant_id' => $a->id]);
        $this->assertDatabaseMissing('presensi_pegawai', ['user_id' => $other->id]);
    }

    public function test_akun_tanpa_sekolah_ditolak_pada_menu_guru_ortu_dan_pegawai(): void
    {
        $this->school('10000001');
        $guru = User::factory()->guru()->create();
        $ortu = User::factory()->ortu()->create();
        $this->actingAs($guru)->get(route('presensi.pegawai.index'))->assertForbidden();
        $this->actingAs($guru)->get(route('guru.kehadiran.index'))->assertForbidden();
        $this->actingAs($ortu)->get(route('ortu.kehadiran.index'))->assertForbidden();
    }

    public function test_qr_diblokir_dan_tidak_hidup_lagi_sesudah_terbit_ulang(): void
    {
        $school = $this->school('10000001');
        $admin = User::factory()->admin()->create(['tenant_id' => $school->id]);
        $wali = User::factory()->guru()->create(['tenant_id' => $school->id]);
        [$student] = $this->classAndStudent($school, $wali);
        $cards = app(IdentityCardService::class);
        [$v1, $v2] = TenantContext::run($school->id, function () use ($cards, $student) {
            return [$cards->issueSiswaCard($student, true), $cards->issueSiswaCard($student, true)];
        });
        $this->assertNotNull(TenantContext::run($school->id, fn () => $cards->resolveSiswaFromPayload($v1)));
        $this->actingAs($admin)->delete(route('admin.presensi.kartu.reset', $student->id))->assertRedirect();
        $this->assertNull(TenantContext::run($school->id, fn () => $cards->resolveSiswaFromPayload($v1)));
        $this->assertNull(TenantContext::run($school->id, fn () => $cards->resolveSiswaFromPayload($v2)));
        $this->assertNull(TenantContext::run($school->id, fn () => $cards->ensureSiswaCard($student->fresh())));
        $v3 = TenantContext::run($school->id, fn () => $cards->issueSiswaCard($student->fresh(), true));
        $this->assertNotSame($v2, $v3);
        $this->assertNull(TenantContext::run($school->id, fn () => $cards->resolveSiswaFromPayload($v1)));
        $this->assertNotNull(TenantContext::run($school->id, fn () => $cards->resolveSiswaFromPayload($v3)));
    }

    public function test_gps_wajib_tidak_menerima_scan_jika_tidak_ada_zona_atau_akurasi(): void
    {
        config(['presensi.wajib_gps_scan_qr_pegawai' => true]);
        $school = $this->school('10000001');
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id]);
        $payload = TenantContext::run($school->id, fn () => app(IdentityCardService::class)->issuePegawaiCard($guru, true));
        $this->actingAs($guru)->postJson(route('presensi.pegawai.scan'), ['qr_payload' => $payload, 'jenis' => 'masuk', 'latitude' => -5.1, 'longitude' => 119.4, 'accuracy' => 10])->assertStatus(422);
        TenantContext::run($school->id, fn () => PresensiLokasi::create(['nama' => 'Sekolah', 'latitude' => -5.1, 'longitude' => 119.4, 'radius_meter' => 100, 'akurasi_maksimal_meter' => 50, 'aktif' => true]));
        $this->actingAs($guru)->postJson(route('presensi.pegawai.scan'), ['qr_payload' => $payload, 'jenis' => 'masuk', 'latitude' => -5.1, 'longitude' => 119.4])->assertStatus(422);
        $this->actingAs($guru)->postJson(route('presensi.pegawai.scan'), ['qr_payload' => $payload, 'jenis' => 'masuk', 'latitude' => -5.1, 'longitude' => 119.4, 'accuracy' => 10])->assertOk();
    }

    public function test_hadir_massal_mapel_menyimpan_waktu_dan_scan_tidak_mengganti_izin(): void
    {
        $school = $this->school('10000001');
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id]);
        [$student, $jadwal] = $this->classAndStudent($school, $guru);
        $this->actingAs($guru)->post(route('guru.kehadiran.tandai-semua', $jadwal))->assertRedirect();
        $this->assertDatabaseHas('kehadiran_mapel', ['siswa_id' => $student->id, 'jadwal_pelajaran_id' => $jadwal->id, 'waktu_hadir' => '2026-10-19 08:30:00']);
        TenantContext::run($school->id, fn () => Kehadiran::where('siswa_id', $student->id)->update(['status' => 'I']));
        $payload = TenantContext::run($school->id, fn () => app(IdentityCardService::class)->issueSiswaCard($student, true));
        $this->actingAs($guru)->postJson(route('guru.kehadiran.scan', $jadwal), ['qr_payload' => $payload])->assertStatus(409);
        $this->assertDatabaseHas('kehadiran', ['siswa_id' => $student->id, 'status' => 'I']);
    }

    public function test_izin_orang_tua_harus_diverifikasi_wali_yang_tepat(): void
    {
        $a = $this->school('10000001');
        $b = $this->school('10000002');
        $wali = User::factory()->guru()->create(['tenant_id' => $a->id]);
        $guruLain = User::factory()->guru()->create(['tenant_id' => $a->id]);
        $guruB = User::factory()->guru()->create(['tenant_id' => $b->id]);
        [$student] = $this->classAndStudent($a, $wali);
        // Bila guru_id siswa berbeda, wali rombel tetap otoritas utama.
        TenantContext::run($a->id, fn () => $student->update(['guru_id' => $guruLain->id]));
        [$studentB] = $this->classAndStudent($b, $guruB);
        $ortu = User::factory()->ortu()->create(['tenant_id' => $a->id, 'terhubung_dengan' => [$student->id]]);
        $data = ['siswa_id' => $student->id, 'tanggal' => now()->toDateString(), 'jenis' => 'S', 'alasan' => 'Kurang sehat'];
        $this->actingAs($ortu)->post(route('ortu.kehadiran.izin.store'), array_merge($data, ['siswa_id' => $studentB->id]))->assertForbidden();
        $this->actingAs($ortu)->post(route('ortu.kehadiran.izin.store'), $data)->assertRedirect()->assertSessionHas('success');
        $izin = TenantContext::run($a->id, fn () => PengajuanIzinPresensi::where('siswa_id', $student->id)->firstOrFail());
        $this->assertDatabaseMissing('kehadiran', ['siswa_id' => $student->id]);
        $this->actingAs($guruLain)->post(route('guru.kehadiran.izin.review', $izin->id), ['keputusan' => 'setujui'])->assertNotFound();
        $this->actingAs($guruB)->post(route('guru.kehadiran.izin.review', $izin->id), ['keputusan' => 'setujui'])->assertNotFound();
        $this->actingAs($wali)->post(route('guru.kehadiran.izin.review', $izin->id), ['keputusan' => 'setujui'])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('kehadiran', ['siswa_id' => $student->id, 'status' => 'S', 'tenant_id' => $a->id]);
        $this->assertDatabaseHas('pengajuan_izin_presensi', ['id' => $izin->id, 'status' => 'disetujui', 'diperiksa_oleh' => $wali->id]);
        $this->actingAs($wali)->post(route('guru.kehadiran.izin.review', $izin->id), ['keputusan' => 'tolak', 'catatan' => 'Uji'])->assertStatus(409);
    }
    public function test_scan_pulang_saja_tidak_membuat_status_hadir(): void
    {
        $school = $this->school('10000001');
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id, 'nik' => 'PIN001']);
        $mesin = app(PresensiMesinService::class);
        $out = ['nik' => 'PIN001', 'waktu' => now()->copy()->setTime(15, 0), 'jenis' => 'pulang'];
        TenantContext::run($school->id, fn () => $mesin->importScans([$out]));
        $this->assertDatabaseMissing('presensi_pegawai', ['user_id' => $guru->id]);
        TenantContext::run($school->id, fn () => $mesin->importScans([
            ['nik' => 'PIN001', 'waktu' => now()->copy()->setTime(7, 0), 'jenis' => 'masuk'],
        ]));
        TenantContext::run($school->id, fn () => $mesin->importScans([$out]));
        $this->assertDatabaseHas('presensi_pegawai', ['user_id' => $guru->id, 'metode_masuk' => 'mesin', 'metode_pulang' => 'mesin']);
    }

    public function test_koreksi_menolak_jam_pulang_sebelum_jam_masuk(): void
    {
        $school = $this->school('10000001');
        $admin = User::factory()->admin()->create(['tenant_id' => $school->id]);
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id]);
        $this->actingAs($admin)->post(route('admin.presensi.pegawai.koreksi'), [
            'user_id' => $guru->id, 'tanggal' => now()->toDateString(), 'status' => 'hadir',
            'jam_masuk' => '09:00', 'jam_pulang' => '08:00', 'alasan_koreksi' => 'Koreksi',
        ])->assertSessionHas('error');
        $this->assertDatabaseMissing('presensi_pegawai', ['user_id' => $guru->id]);
    }

    public function test_izin_tidak_menimpa_kehadiran_yang_sudah_hadir(): void
    {
        $school = $this->school('10000001');
        $wali = User::factory()->guru()->create(['tenant_id' => $school->id]);
        [$student] = $this->classAndStudent($school, $wali);
        $ortu = User::factory()->ortu()->create(['tenant_id' => $school->id, 'terhubung_dengan' => [$student->id]]);
        TenantContext::run($school->id, fn () => Kehadiran::create([
            'guru_id' => $wali->id, 'siswa_id' => $student->id, 'tanggal' => now()->toDateString(), 'status' => 'H',
        ]));
        $this->actingAs($ortu)->post(route('ortu.kehadiran.izin.store'), [
            'siswa_id' => $student->id, 'tanggal' => now()->toDateString(), 'jenis' => 'I', 'alasan' => 'Keperluan keluarga',
        ])->assertRedirect();
        $izin = TenantContext::run($school->id, fn () => PengajuanIzinPresensi::firstOrFail());
        $this->actingAs($wali)->post(route('guru.kehadiran.izin.review', $izin->id), ['keputusan' => 'setujui'])->assertStatus(409);
        $this->assertDatabaseHas('kehadiran', ['siswa_id' => $student->id, 'status' => 'H']);
        $this->assertDatabaseHas('pengajuan_izin_presensi', ['id' => $izin->id, 'status' => 'menunggu']);
    }

    public function test_kartu_pegawai_yang_dilepas_tidak_aktif_lagi(): void
    {
        $school = $this->school('10000001');
        $admin = User::factory()->admin()->create(['tenant_id' => $school->id]);
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id]);
        $cards = app(IdentityCardService::class);
        $v1 = TenantContext::run($school->id, fn () => $cards->issuePegawaiCard($guru, true));
        TenantContext::run($school->id, fn () => $cards->issuePegawaiCard($guru, true));
        $this->actingAs($admin)->delete(route('admin.presensi.kartu.pegawai.reset', $guru->uuid))->assertRedirect();
        $this->assertNull(TenantContext::run($school->id, fn () => $cards->resolvePegawaiFromPayload($v1)));
        $v3 = TenantContext::run($school->id, fn () => $cards->issuePegawaiCard($guru->fresh(), true));
        $this->assertNull(TenantContext::run($school->id, fn () => $cards->resolvePegawaiFromPayload($v1)));
        $this->assertNotNull(TenantContext::run($school->id, fn () => $cards->resolvePegawaiFromPayload($v3)));
    }

    public function test_rollback_hanya_ke_versi_transisi_dan_counter_tidak_mundur(): void
    {
        $school = $this->school('10000001');
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id]);
        [$student] = $this->classAndStudent($school, $guru);
        $cards = app(IdentityCardService::class);
        TenantContext::run($school->id, function () use ($cards, $student) {
            $v1 = $cards->issueSiswaCard($student, true);
            $cards->markSiswaAsPrinted($student);
            $v2 = $cards->issueSiswaCard($student, true);
            $this->assertNotNull($cards->resolveSiswaFromPayload($v1));
            $this->assertTrue($cards->rollbackSiswaToPrintedVersion($student));
            $this->assertNull($cards->resolveSiswaFromPayload($v2));
            $cards->issueSiswaCard($student, false);
            $v3 = $cards->issueSiswaCard($student, true);
            $this->assertNotSame($v2, $v3);
            $this->assertNull($cards->resolveSiswaFromPayload($v2));
            $this->assertNotNull($cards->resolveSiswaFromPayload($v3));
        });
    }

    public function test_memastikan_kartu_tidak_mengganti_kartu_fisik_eksternal(): void
    {
        $school = $this->school('10000001');
        $admin = User::factory()->admin()->create(['tenant_id' => $school->id]);
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id]);
        [$student] = $this->classAndStudent($school, $guru);
        $this->actingAs($admin)->postJson(route('admin.presensi.kartu.bind', $student->id), ['qr_payload' => 'QR-KARTU-FISIK'])->assertOk();
        $cards = app(IdentityCardService::class);
        TenantContext::run($school->id, function () use ($cards, $student) {
            $this->assertNull($cards->issueSiswaCard($student->fresh(), false));
            $this->assertNotNull($cards->resolveSiswaFromPayload('QR-KARTU-FISIK'));
        });
    }

    public function test_kartu_lama_format_sebelum_migrasi_tidak_hidup_lagi(): void
    {
        $school = $this->school('10000001');
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id]);
        [$student] = $this->classAndStudent($school, $guru);
        $cards = app(IdentityCardService::class);
        TenantContext::run($school->id, function () use ($cards, $student) {
            // Format lama deterministik v1 bisa pernah dicabut tanpa riwayat versi.
            $legacy = $cards->payloadForSiswa($student, 1);
            $new = $cards->issueSiswaCard($student, true);
            $this->assertNotSame($legacy, $new);
            $this->assertNull($cards->resolveSiswaFromPayload($legacy));
            $this->assertNotNull($cards->resolveSiswaFromPayload($new));
        });
    }

    public function test_perubahan_status_manual_memperbarui_catatan_harian_yang_sama(): void
    {
        $school = $this->school('10000001');
        $guru = User::factory()->guru()->create(['tenant_id' => $school->id]);
        [$student, $jadwal] = $this->classAndStudent($school, $guru);
        $this->actingAs($guru)->postJson(route('guru.kehadiran.status', [$jadwal, $student]), [
            'cakupan' => 'harian', 'status' => 'I', 'keterangan' => 'Izin',
        ])->assertOk()->assertJsonPath('status', 'I');
        $this->assertDatabaseHas('kehadiran', ['siswa_id' => $student->id, 'status' => 'I', 'waktu_masuk' => null]);
        $this->actingAs($guru)->postJson(route('guru.kehadiran.status', [$jadwal, $student]), [
            'cakupan' => 'harian', 'status' => 'H',
        ])->assertOk()->assertJsonPath('status', 'H');
        $this->assertDatabaseCount('kehadiran', 1);
        $this->assertDatabaseHas('kehadiran', ['siswa_id' => $student->id, 'status' => 'H', 'waktu_masuk' => '2026-10-19 08:30:00']);
    }

}
