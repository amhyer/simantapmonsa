<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\PresensiLokasi;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PresensiTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private Rombel $rombel;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 08:30:00', config('app.timezone')));

        $this->guru = User::factory()->guru()->create();
        $semester = Semester::create([
            'semester_id' => '20261',
            'tahun_ajaran' => '2026/2027',
            'nama_semester' => 'ganjil',
        ]);
        $this->rombel = Rombel::create([
            'dapodik_id' => 'RB-PRESENSI-1',
            'semester_id' => $semester->id,
            'nama_rombel' => '6.A',
            'tingkat' => '6',
            'guru_id' => $this->guru->id,
        ]);
        $this->siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $this->guru->id,
            'rombel_id' => $this->rombel->id,
            'nama_guru' => $this->guru->nama_lengkap,
            'nis' => 'QR001',
            'nisn' => '0000000001',
            'nama_peserta_didik' => 'Siswa Presensi',
            'kelas' => '6.A',
            'jenis_kelamin' => 'P',
            'agama' => 'Islam',
        ]);
    }

    private function jadwal(string $mapel = 'Bahasa Inggris'): JadwalPelajaran
    {
        return JadwalPelajaran::create([
            'guru_id' => $this->guru->id,
            'semester_id' => $this->rombel->semester_id,
            'rombel_id' => $this->rombel->id,
            'kelas' => $this->rombel->nama_rombel,
            'mata_pelajaran' => $mapel,
            'hari' => now()->locale('id')->translatedFormat('l'),
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:00:00',
        ]);
    }

    public function test_qr_mencatat_absensi_harian_dan_catatan_mapel_terpisah(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = $this->jadwal('Bahasa Inggris');

        $this->actingAs($admin)->postJson(route('admin.presensi.kartu.bind', $this->siswa), [
            'qr_payload' => 'SIMANTAP-CARD-QR-001',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('siswa', ['id' => $this->siswa->id, 'qr_token_hash' => 'SIMANTAP-CARD-QR-001']);

        $response = $this->actingAs($this->guru)->postJson(route('guru.kehadiran.scan', $jadwal), [
            'qr_payload' => 'SIMANTAP-CARD-QR-001',
        ])->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('student.nama', 'Siswa Presensi')
            ->assertJsonPath('daily.status', 'H')
            ->assertJsonPath('mapel.status', 'H');

        $this->assertDatabaseCount('kehadiran', 1);
        // DB-agnostik: kolom date diserialisasi 'Y-m-d H:i:s' oleh cast 'date',
        // sehingga pencocokan string mentah ('2026-10-19') hanya lolos di PostgreSQL.
        $this->assertTrue(
            \DB::table('kehadiran')
                ->where(['siswa_id' => $this->siswa->id, 'status' => 'H', 'metode' => 'qr'])
                ->whereDate('tanggal', now()->toDateString())
                ->exists(),
            'Baris kehadiran harian (metode QR) tidak ditemukan.'
        );
        $this->assertDatabaseHas('kehadiran_mapel', [
            'jadwal_pelajaran_id' => $jadwal->id,
            'siswa_id' => $this->siswa->id,
            'status' => 'H',
            'metode' => 'qr',
        ]);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 08:45:00', config('app.timezone')));
        $this->postJson(route('guru.kehadiran.status', [$jadwal, $this->siswa]), [
            'cakupan' => 'harian',
            'status' => 'H',
        ])->assertOk()->assertJsonPath('waktu', '08:30:00');

        $this->postJson(route('guru.kehadiran.scan', $jadwal), [
            'qr_payload' => 'SIMANTAP-CARD-QR-001',
        ])->assertOk()->assertJsonPath('mapel.already_present', true);
        $this->assertDatabaseHas('kehadiran', ['siswa_id' => $this->siswa->id, 'waktu_masuk' => '2026-10-19 08:30:00', 'metode' => 'qr']);
        $this->assertDatabaseCount('kehadiran', 1);
        $this->assertDatabaseCount('kehadiran_mapel', 1);
    }

    public function test_endpoint_lama_tidak_menerima_absensi_tanpa_jadwal(): void
    {
        $this->actingAs($this->guru)->postJson(route('guru.kehadiran.store'), [
            'siswa_id' => $this->siswa->id,
            'status' => 'A',
        ])->assertStatus(422);

        $this->assertDatabaseCount('kehadiran', 0);
    }

    public function test_absensi_ditolak_di_luar_jam_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = $this->jadwal('Bahasa Inggris');
        $this->actingAs($admin)->postJson(route('admin.presensi.kartu.bind', $this->siswa), [
            'qr_payload' => 'QR-WINDOW-001',
        ])->assertOk();

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 11:30:00', config('app.timezone')));
        $this->actingAs($this->guru)->postJson(route('guru.kehadiran.scan', $jadwal), [
            'qr_payload' => 'QR-WINDOW-001',
        ])->assertForbidden();

        $this->assertDatabaseCount('kehadiran', 0);
    }

    public function test_qr_siswa_tidak_dapat_dipakai_untuk_rombel_lain(): void
    {
        $admin = User::factory()->admin()->create();
        $teacherTwo = User::factory()->guru()->create();
        $semester = Semester::first();
        $otherClass = Rombel::create([
            'dapodik_id' => 'RB-PRESENSI-2',
            'semester_id' => $semester->id,
            'nama_rombel' => '6.B',
            'tingkat' => '6',
            'guru_id' => $teacherTwo->id,
        ]);
        $jadwalOther = JadwalPelajaran::create([
            'guru_id' => $teacherTwo->id,
            'semester_id' => $semester->id,
            'rombel_id' => $otherClass->id,
            'kelas' => $otherClass->nama_rombel,
            'mata_pelajaran' => 'Bahasa Inggris',
            'hari' => now()->locale('id')->translatedFormat('l'),
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:00:00',
        ]);

        $this->actingAs($admin)->postJson(route('admin.presensi.kartu.bind', $this->siswa), [
            'qr_payload' => 'QR-CLASS-LOCK',
        ])->assertOk();

        $this->actingAs($teacherTwo)->postJson(route('guru.kehadiran.scan', $jadwalOther), [
            'qr_payload' => 'QR-CLASS-LOCK',
        ])->assertForbidden();

        $this->assertDatabaseCount('kehadiran', 0);
        $this->assertDatabaseCount('kehadiran_mapel', 0);
    }

    public function test_siswa_alpa_di_mapel_dapat_ditandai_hadir_ketika_kemudian_scan(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = $this->jadwal('PJOK');
        $this->actingAs($admin)->postJson(route('admin.presensi.kartu.bind', $this->siswa), [
            'qr_payload' => 'QR-PJOK-001',
        ])->assertOk();

        $this->actingAs($this->guru)->postJson(route('guru.kehadiran.status', [$jadwal, $this->siswa]), [
            'cakupan' => 'mapel',
            'status' => 'A',
            'keterangan' => 'Tidak hadir saat pemanggilan.',
        ])->assertOk()->assertJsonPath('status', 'A');

        $this->assertDatabaseHas('kehadiran_mapel', [
            'jadwal_pelajaran_id' => $jadwal->id,
            'siswa_id' => $this->siswa->id,
            'status' => 'A',
            'waktu_hadir' => null,
        ]);

        $this->postJson(route('guru.kehadiran.scan', $jadwal), [
            'qr_payload' => 'QR-PJOK-001',
        ])->assertOk()->assertJsonPath('mapel.status', 'H');

        $this->assertDatabaseHas('kehadiran_mapel', [
            'jadwal_pelajaran_id' => $jadwal->id,
            'siswa_id' => $this->siswa->id,
            'status' => 'H',
            'metode' => 'qr',
        ]);
    }

    public function test_mapel_umum_hanya_mencatat_harian_tidak_membuat_absensi_mapel(): void
    {
        $admin = User::factory()->admin()->create();
        $jadwal = $this->jadwal('Matematika');
        $this->actingAs($admin)->postJson(route('admin.presensi.kartu.bind', $this->siswa), [
            'qr_payload' => 'QR-MTK-001',
        ])->assertOk();

        $this->actingAs($this->guru)->postJson(route('guru.kehadiran.scan', $jadwal), [
            'qr_payload' => 'QR-MTK-001',
        ])->assertOk()->assertJsonPath('mapel', null);

        $this->assertDatabaseCount('kehadiran', 1);
        $this->assertDatabaseCount('kehadiran_mapel', 0);
    }

    public function test_tendik_dapat_mencatat_masuk_pulang_dengan_geofence(): void
    {
        $admin = User::factory()->admin()->create();
        $tendik = User::factory()->tendik()->create();
        $zone = PresensiLokasi::create([
            'nama' => 'Gerbang sekolah',
            'latitude' => -5.147665,
            'longitude' => 119.432732,
            'radius_meter' => 250,
            'akurasi_maksimal_meter' => 100,
            'aktif' => true,
            'dibuat_oleh' => $admin->id,
        ]);

        $this->actingAs($tendik)->postJson(route('presensi.pegawai.masuk'), [
            'latitude' => -5.147665,
            'longitude' => 119.432732,
            'accuracy' => 8,
        ])->assertOk()->assertJsonPath('record.lokasi_masuk', $zone->nama);

        // DB-agnostik (lihat catatan pada test QR): cocokkan via whereDate.
        $this->assertTrue(
            \DB::table('presensi_pegawai')
                ->where(['user_id' => $tendik->id, 'metode' => 'gps'])
                ->whereDate('tanggal', now()->toDateString())
                ->exists(),
            'Baris presensi pegawai (metode GPS) tidak ditemukan.'
        );

        $this->postJson(route('presensi.pegawai.masuk'), [
            'latitude' => -5.147665,
            'longitude' => 119.432732,
            'accuracy' => 8,
        ])->assertStatus(409);

        $this->postJson(route('presensi.pegawai.pulang'), [
            'latitude' => -5.147665,
            'longitude' => 119.432732,
            'accuracy' => 8,
        ])->assertOk()->assertJsonPath('record.waktu_pulang', '08:30:00');
    }

    public function test_geofence_menggunakan_titik_yang_memenuhi_radius_dan_akurasi_gps(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->guru()->create();
        PresensiLokasi::create([
            'nama' => 'Titik akurasi tinggi tetapi jauh',
            'latitude' => -5.200000,
            'longitude' => 119.500000,
            'radius_meter' => 250,
            'akurasi_maksimal_meter' => 20,
            'aktif' => true,
            'dibuat_oleh' => $admin->id,
        ]);
        PresensiLokasi::create([
            'nama' => 'Titik yang sesuai GPS',
            'latitude' => -5.147665,
            'longitude' => 119.432732,
            'radius_meter' => 250,
            'akurasi_maksimal_meter' => 150,
            'aktif' => true,
            'dibuat_oleh' => $admin->id,
        ]);

        $this->actingAs($teacher)->postJson(route('presensi.pegawai.masuk'), [
            'latitude' => -5.147665,
            'longitude' => 119.432732,
            'accuracy' => 75,
        ])->assertOk()->assertJsonPath('record.lokasi_masuk', 'Titik yang sesuai GPS');
    }

    public function test_admin_dapat_mengkoreksi_jam_presensi_dengan_alasan(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->guru()->create();

        $this->actingAs($admin)->post(route('admin.presensi.pegawai.koreksi'), [
            'user_id' => $teacher->id,
            'tanggal' => '2026-10-20',
            'status' => 'terlambat',
            'jam_masuk' => '08:15',
            'jam_pulang' => '16:30',
            'alasan_koreksi' => 'GPS perangkat bermasalah; diverifikasi kepala sekolah.',
        ])->assertRedirect(route('admin.presensi.index', ['tanggal' => '2026-10-20']));

        $record = \App\Models\PresensiPegawai::where('user_id', $teacher->id)->firstOrFail();
        $this->assertSame('terlambat', $record->status);
        $this->assertSame('2026-10-20 08:15:00', $record->waktu_masuk->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 16:30:00', $record->waktu_pulang->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('aktivitas', [
            'jenis' => 'kehadiran',
            'record_id' => $record->id,
            'deskripsi' => 'GPS perangkat bermasalah; diverifikasi kepala sekolah.',
        ]);
    }

    public function test_presensi_pegawai_ditolak_di_luar_geofence(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->guru()->create();
        PresensiLokasi::create([
            'nama' => 'Sekolah',
            'latitude' => -5.147665,
            'longitude' => 119.432732,
            'radius_meter' => 100,
            'akurasi_maksimal_meter' => 100,
            'aktif' => true,
            'dibuat_oleh' => $admin->id,
        ]);

        $this->actingAs($teacher)->postJson(route('presensi.pegawai.masuk'), [
            'latitude' => -5.200000,
            'longitude' => 119.500000,
            'accuracy' => 5,
        ])->assertForbidden();

        $this->assertDatabaseCount('presensi_pegawai', 0);
    }

    public function test_akun_tendik_diarahkan_ke_halaman_presensi(): void
    {
        $tendik = User::factory()->tendik()->create();

        $this->actingAs($tendik)->get('/dashboard')
            ->assertRedirect(route('presensi.pegawai.index'));
        $this->actingAs($tendik)->get(route('presensi.pegawai.index'))->assertOk();
    }

    public function test_admin_dapat_menerbitkan_dan_mencetak_kartu_identitas_siswa_dan_pegawai(): void
    {
        $admin = User::factory()->admin()->create();
        $cardService = app(\App\Services\Presensi\IdentityCardService::class);

        // Siswa sebelum diterbitkan
        $this->assertNull($this->siswa->qr_token_hash);

        // Terbitkan kartu siswa
        $this->actingAs($admin)->post(route('admin.presensi.kartu.siswa.issue', $this->siswa))
            ->assertSessionHas('success');
        $this->siswa->refresh();
        $this->assertNotNull($this->siswa->qr_token_hash);
        $this->assertSame(1, $this->siswa->qr_card_version);

        // Cetak kartu siswa (halaman HTML cetak offline dengan SVG QR lokal)
        $printSiswa = $this->actingAs($admin)->get(route('admin.presensi.kartu.siswa.cetak', $this->siswa));
        $printSiswa->assertOk()
            ->assertSee('KARTU IDENTITAS SISWA')
            ->assertSee($this->siswa->nama_peserta_didik)
            ->assertSee('<svg', false);

        // Terbitkan kartu pegawai (guru)
        $this->actingAs($admin)->post(route('admin.presensi.kartu.pegawai.issue', $this->guru))
            ->assertSessionHas('success');
        $this->guru->refresh();
        $this->assertNotNull($this->guru->qr_token_hash);
        $this->assertSame(1, $this->guru->qr_card_version);

        // Cetak kartu pegawai
        $printPegawai = $this->actingAs($admin)->get(route('admin.presensi.kartu.pegawai.cetak', $this->guru));
        $printPegawai->assertOk()
            ->assertSee('KARTU IDENTITAS GURU')
            ->assertSee($this->guru->nama_lengkap)
            ->assertSee('<svg', false);
    }

    public function test_guru_dan_pegawai_dapat_scan_mandiri_kartu_identitas_sendiri_untuk_masuk_dan_pulang(): void
    {
        $cardService = app(\App\Services\Presensi\IdentityCardService::class);
        $payload = $cardService->issuePegawaiCard($this->guru, false);

        // 1. Scan mandiri jam masuk
        $responseMasuk = $this->actingAs($this->guru)->postJson(route('presensi.pegawai.scan'), [
            'qr_payload' => $payload,
            'jenis' => 'masuk',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(
            \DB::table('presensi_pegawai')
                ->where(['user_id' => $this->guru->id, 'metode' => 'qr', 'status' => 'hadir'])
                ->whereDate('tanggal', now()->toDateString())
                ->exists(),
            'Presensi masuk via scan mandiri kartu identitas tidak tercatat.'
        );

        // 2. Scan ulang jam masuk ditolak (sudah tercatat)
        $this->actingAs($this->guru)->postJson(route('presensi.pegawai.scan'), [
            'qr_payload' => $payload,
            'jenis' => 'masuk',
        ])->assertStatus(409);

        // 3. Scan mandiri jam pulang
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 16:00:00', config('app.timezone')));
        $responsePulang = $this->actingAs($this->guru)->postJson(route('presensi.pegawai.scan'), [
            'qr_payload' => $payload,
            'jenis' => 'pulang',
        ])->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('record.waktu_pulang', '16:00:00');

        $this->assertTrue(
            \DB::table('presensi_pegawai')
                ->where(['user_id' => $this->guru->id, 'metode' => 'qr'])
                ->whereNotNull('waktu_pulang')
                ->exists(),
            'Presensi pulang via scan mandiri kartu identitas tidak tercatat.'
        );
    }

    public function test_scan_mandiri_menolak_kartu_milik_pegawai_lain_dan_kartu_siswa(): void
    {
        $cardService = app(\App\Services\Presensi\IdentityCardService::class);
        $cardService->issuePegawaiCard($this->guru, false);
        $otherTeacher = User::factory()->guru()->create();
        $otherPayload = $cardService->issuePegawaiCard($otherTeacher, false);
        $studentPayload = $cardService->issueSiswaCard($this->siswa, false);

        // Guru scan kartu milik guru lain -> ditolak 403
        $this->actingAs($this->guru)->postJson(route('presensi.pegawai.scan'), [
            'qr_payload' => $otherPayload,
            'jenis' => 'masuk',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Kartu yang dipindai adalah milik guru/pegawai lain. Anda wajib memindai kartu identitas milik sendiri.');

        // Guru scan kartu milik siswa di menu presensi mandiri pegawai -> ditolak 422
        $this->actingAs($this->guru)->postJson(route('presensi.pegawai.scan'), [
            'qr_payload' => $studentPayload,
            'jenis' => 'masuk',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Ini adalah kartu identitas siswa. Gunakan menu Kehadiran Siswa pada jadwal mengajar Anda.');
    }

    public function test_guru_mapel_scan_kartu_siswa_pada_jadwal_hari_ini_mencatat_kehadiran_mapel(): void
    {
        $cardService = app(\App\Services\Presensi\IdentityCardService::class);
        $studentPayload = $cardService->issueSiswaCard($this->siswa, false);
        $jadwal = $this->jadwal('Bahasa Inggris');

        // Guru mengajar scan kartu identitas siswa
        $this->actingAs($this->guru)->postJson(route('guru.kehadiran.scan', $jadwal), [
            'qr_payload' => $studentPayload,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('student.id', $this->siswa->id)
            ->assertJsonPath('daily.status', 'H')
            ->assertJsonPath('mapel.status', 'H');

        $this->assertDatabaseHas('kehadiran_mapel', [
            'jadwal_pelajaran_id' => $jadwal->id,
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'status' => 'H',
            'metode' => 'qr',
        ]);
    }

    public function test_guru_tidak_dapat_scan_siswa_jika_bukan_jadwal_hari_ini_atau_bukan_pengampu(): void
    {
        $cardService = app(\App\Services\Presensi\IdentityCardService::class);
        $studentPayload = $cardService->issueSiswaCard($this->siswa, false);

        // 1. Jadwal bukan hari ini (misal Minggu)
        $jadwalBukanHariIni = JadwalPelajaran::create([
            'guru_id' => $this->guru->id,
            'semester_id' => $this->rombel->semester_id,
            'rombel_id' => $this->rombel->id,
            'kelas' => $this->rombel->nama_rombel,
            'mata_pelajaran' => 'Bahasa Inggris',
            'hari' => 'Minggu',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:00:00',
        ]);

        $this->actingAs($this->guru)->postJson(route('guru.kehadiran.scan', $jadwalBukanHariIni), [
            'qr_payload' => $studentPayload,
        ])->assertForbidden();

        // 2. Jadwal milik guru lain
        $otherTeacher = User::factory()->guru()->create();
        $jadwalOther = JadwalPelajaran::create([
            'guru_id' => $otherTeacher->id,
            'semester_id' => $this->rombel->semester_id,
            'rombel_id' => $this->rombel->id,
            'kelas' => $this->rombel->nama_rombel,
            'mata_pelajaran' => 'Bahasa Inggris',
            'hari' => now()->locale('id')->translatedFormat('l'),
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:00:00',
        ]);

        $this->actingAs($this->guru)->postJson(route('guru.kehadiran.scan', $jadwalOther), [
            'qr_payload' => $studentPayload,
        ])->assertForbidden();
    }

    public function test_guru_dan_siswa_dapat_melihat_dan_mencetak_kartu_identitas_sendiri(): void
    {
        // Guru melihat halaman Presensi Saya dan kartu cetaknya
        $this->actingAs($this->guru)->get(route('presensi.pegawai.index'))
            ->assertOk()
            ->assertSee('Kartu Identitas Saya')
            ->assertSee('Scan Mandiri Kartu Identitas');

        $this->actingAs($this->guru)->get(route('presensi.pegawai.kartu'))
            ->assertOk()
            ->assertSee('KARTU IDENTITAS GURU')
            ->assertSee($this->guru->nama_lengkap);

        // Siswa melihat kartu identitas pada profilnya dan cetak kartu
        $siswaUser = User::factory()->siswa()->create(['nama_pengguna' => $this->siswa->nis]);
        $this->actingAs($siswaUser)->get(route('siswa.profil.show'))
            ->assertOk()
            ->assertSee('Kartu Identitas Absensi Saya');

        $this->actingAs($siswaUser)->get(route('siswa.kartu.cetak'))
            ->assertOk()
            ->assertSee('KARTU IDENTITAS SISWA')
            ->assertSee($this->siswa->nama_peserta_didik);
    }

    public function test_guru_dapat_menandai_semua_siswa_hadir_pada_jadwal_hari_ini(): void
    {
        $this->actingAs($this->guru);
        $jadwal = $this->jadwal();

        $response = $this->post(route('guru.kehadiran.tandai-semua', $jadwal));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('kehadiran', [
            'siswa_id' => $this->siswa->id,
            'status' => 'H',
            'metode' => 'manual',
            'guru_id' => $this->guru->id,
        ]);
    }

    public function test_kartu_fisik_lama_tetap_bisa_discan_meskipun_admin_ganti_qr_dan_dapat_dipulihkan(): void
    {
        $admin = User::factory()->admin()->create();
        $cardService = app(\App\Services\Presensi\IdentityCardService::class);
        $jadwal = $this->jadwal('Bahasa Inggris');

        // 1. Terbitkan kartu v1 dan cetak kartu fisik
        $payloadV1 = $cardService->issueSiswaCard($this->siswa, true);
        $this->actingAs($admin)->get(route('admin.presensi.kartu.siswa.cetak', $this->siswa))->assertOk();

        $this->siswa->refresh();
        $this->assertSame(1, (int) $this->siswa->qr_card_version);
        $this->assertSame(1, (int) $this->siswa->printed_version);
        $this->assertNotNull($this->siswa->printed_at);

        // 2. Admin tiba-tiba klik "Ganti QR" sehingga versi sistem naik ke v2
        $this->actingAs($admin)->post(route('admin.presensi.kartu.siswa.issue', $this->siswa))
            ->assertSessionHas('success');

        $this->siswa->refresh();
        $this->assertSame(2, (int) $this->siswa->qr_card_version);
        $this->assertSame(1, (int) $this->siswa->printed_version);
        $this->assertNotNull($this->siswa->qr_previous_token_hash);

        // 3. Siswa memindai kartu fisik lamanya (v1) di kelas guru -> HARUS TETAP BERHASIL!
        $scanResponse = $this->actingAs($this->guru)->postJson(route('guru.kehadiran.scan', $jadwal), [
            'qr_payload' => $payloadV1,
        ]);

        $scanResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('student.id', $this->siswa->id);

        // 4. Admin menyadari salah klik, lalu klik "Kembalikan ke Versi Tercetak (Rollback)"
        $rollbackResponse = $this->actingAs($admin)->post(route('admin.presensi.kartu.siswa.rollback', $this->siswa));
        $rollbackResponse->assertSessionHas('success');

        $this->siswa->refresh();
        $this->assertSame(1, (int) $this->siswa->qr_card_version);
        $this->assertSame(1, (int) $this->siswa->printed_version);
    }

    public function test_kartu_fisik_lama_pegawai_tetap_sah_setelah_admin_ganti_qr_dan_bisa_dipulihkan(): void
    {
        $admin = User::factory()->admin()->create();
        $cardService = app(\App\Services\Presensi\IdentityCardService::class);

        // 1. Terbitkan kartu v1 dan cetak untuk guru
        $payloadV1 = $cardService->issuePegawaiCard($this->guru, true);
        $this->actingAs($admin)->get(route('admin.presensi.kartu.pegawai.cetak', $this->guru))->assertOk();

        $this->guru->refresh();
        $this->assertSame(1, (int) $this->guru->qr_card_version);
        $this->assertSame(1, (int) $this->guru->printed_version);

        // 2. Admin klik "Ganti QR" sehingga versi pegawai naik ke v2
        $this->actingAs($admin)->post(route('admin.presensi.kartu.pegawai.issue', $this->guru))
            ->assertSessionHas('success');

        $this->guru->refresh();
        $this->assertSame(2, (int) $this->guru->qr_card_version);
        $this->assertSame(1, (int) $this->guru->printed_version);

        // 3. Guru scan mandiri menggunakan kartu fisik v1 -> HARUS TETAP DITERIMA!
        $scanResponse = $this->actingAs($this->guru)->postJson(route('presensi.pegawai.scan'), [
            'qr_payload' => $payloadV1,
            'jenis' => 'masuk',
        ]);

        $scanResponse->assertOk()
            ->assertJsonPath('success', true);

        // 4. Admin pulihkan versi pegawai
        $rollbackResponse = $this->actingAs($admin)->post(route('admin.presensi.kartu.pegawai.rollback', $this->guru));
        $rollbackResponse->assertSessionHas('success');

        $this->guru->refresh();
        $this->assertSame(1, (int) $this->guru->qr_card_version);
        $this->assertSame(1, (int) $this->guru->printed_version);
    }
}
