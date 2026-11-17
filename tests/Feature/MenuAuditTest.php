<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Kehadiran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MenuAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_semua_menu(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $kepsek = User::factory()->kepsek()->create();
        $ortu = User::factory()->ortu()->create();

        $semester = Semester::create([
            'semester_id' => '20251', 'tahun_ajaran' => '2025/2026', 'nama_semester' => 'ganjil',
        ]);
        MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true]);
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'S001',
            'nama_peserta_didik' => 'Ani Contoh',
            'kelas' => '1.A',
            'jenis_kelamin' => 'P',
        ]);
        $siswaUser = User::factory()->siswa()->create(['nama_pengguna' => 'S001']);
        $ortu->update(['terhubung_dengan' => [$siswa->id]]);

        $tendik = User::factory()->tendik()->create();

        $map = [
            'super_admin' => [$superAdmin, [
                'superadmin.dashboard', 'superadmin.tenants.index', 'superadmin.users.index',
            ]],
            'admin' => [$admin, [
                'admin.dashboard', 'admin.profile', 'admin.dapodik.index',
                'admin.users.index', 'admin.sekolah.index', 'admin.validasi-data.index', 'admin.referensi.guru',
                'admin.users.siswa', 'admin.peta-kelas.index', 'admin.mapel.index',
                'admin.referensi.pembelajaran', 'admin.referensi.ekstrakurikuler',
                'admin.referensi.kelompok-mapel', 'admin.referensi.mapping-rapor',
                'admin.referensi.logo-ttd', 'admin.referensi.tanggal-rapor',
                'admin.referensi.foto-siswa', 'admin.kokurikuler.tema',
                'admin.kokurikuler.kegiatan', 'admin.kokurikuler.kelompok',
                'admin.penilaian.status', 'admin.penilaian.statistik',
                'admin.transkrip.setting', 'admin.transkrip.nomor',
                'admin.transkrip.input', 'admin.transkrip.import',
                'admin.transkrip.import.template', 'admin.transkrip.cetak',
                'admin.cetak.leger', 'admin.cetak.pelengkap', 'admin.cetak.nilai',
                'admin.dapodik.push.index', 'admin.semester.index',
                'admin.kode-akses.index', 'admin.modul.index', 'admin.bobot.index',
                'admin.sheet.index', 'admin.backup.index', 'admin.api-keys.index',
                'admin.log.index',
                'admin.presensi.index',
                'admin.presensi.laporan',
                'admin.presensi.mesin.index',
                'admin.presensi.mesin.sinkron',
                'admin.presensi.kartu.index',
                'admin.presensi.kartu.siswa.cetak-massal',
                'admin.presensi.kartu.pegawai.cetak-massal',
                'admin.tka-kesiapan.index',
                'admin.tka-kesiapan.pengaturan',
            ]],
            'guru' => [$guru, [
                'guru.dashboard', 'guru.kelas-digital.index', 'guru.perangkat.index', 'guru.perangkat.create', 'guru.perangkat.jurnal.index', 'guru.tp.index', 'guru.materi.index', 'guru.dimensi.index',
                'guru.tka.index', 'guru.tka-kesiapan.index', 'guru.input-nilai.index', 'guru.nilai-erapor.index',
                'guru.p5.index', 'guru.cek-penilaian.index',
                'guru.walas.ekskul', 'guru.walas.naik-kelas', 'guru.walas.status-penilaian',
                'guru.walas.leger', 'guru.walas.transkrip',
                'guru.kuis.index', 'guru.nilai.index', 'guru.analisis.index',
                'guru.kehadiran.index', 'guru.catatan.index', 'guru.kebiasaan.index',
                'guru.laporan.index', 'guru.erapor.index', 'guru.siswa.index',
                'guru.integrasi.index', 'guru.pengaturan.index',
                'presensi.pegawai.index', 'presensi.pegawai.kartu',
            ]],
            'siswa' => [$siswaUser, [
                'siswa.dashboard', 'siswa.kelas-digital.index', 'siswa.materi.index', 'siswa.kuis.index',
                'siswa.nilai.index', 'siswa.rapor.capaian', 'siswa.rapor.unduh',
                'siswa.rapor.cetak.akademik', 'siswa.rapor.cetak.p5', 'siswa.rapor.cetak.pelengkap',
                'siswa.profil.show', 'siswa.kartu.cetak', 'siswa.tka-kesiapan.index',
            ]],
            'ortu' => [$ortu, [
                'ortu.dashboard', 'ortu.kebiasaan.index', 'ortu.rekap.index',
                'ortu.nilai.index', 'ortu.kehadiran.index', 'ortu.catatan.index',
                'ortu.laporan.index', 'ortu.tka-kesiapan.index',
            ]],
            'kepsek' => [$kepsek, [
                'kepsek.dashboard', 'kepsek.perangkat.index', 'kepsek.rekap.index', 'kepsek.peta-kelas.index',
                'kepsek.hasil-belajar.index', 'kepsek.pantau.index',
                'kepsek.kebiasaan.index', 'kepsek.aktivitas.index', 'kepsek.tka-kesiapan.index',
                'presensi.pegawai.index', 'presensi.pegawai.kartu',
            ]],
            'tendik' => [$tendik, [
                'presensi.pegawai.index', 'presensi.pegawai.kartu',
            ]],
        ];

        $rows = [];
        $errors = [];
        foreach ($map as $role => [$user, $routes]) {
            foreach ($routes as $name) {
                try {
                    $res = $this->actingAs($user)->get(route($name));
                    $status = $res->getStatusCode();
                    $html = $res->getContent() ?: '';
                    $bodyRows = 0;
                    if (preg_match_all('/<tbody[^>]*>(.*?)<\/tbody>/s', $html, $m)) {
                        foreach ($m[1] as $tb) {
                            $bodyRows += substr_count($tb, '<tr');
                        }
                    }
                    $empty = preg_match('/empty-state|Belum ada data|belum ada data|Tidak ada data|belum tersedia/i', $html) ? 'YA' : '-';
                    $rows[] = [$role, $name, $status, strlen($html), $bodyRows, $empty];
                    if ($status >= 400) {
                        $errors[] = "{$role} {$name} => HTTP {$status}";
                    }
                } catch (\Throwable $e) {
                    $rows[] = [$role, $name, 'EXC', 0, 0, '-'];
                    $errors[] = "{$role} {$name} => " . get_class($e) . ': ' . substr($e->getMessage(), 0, 200);
                }
            }
        }

        $out = "role | route | http | bytes | baris_tbody | tanda_kosong\n";
        foreach ($rows as $r) {
            $out .= implode(' | ', $r) . "\n";
        }
        if ($errors) {
            $out .= "\nERRORS:\n" . implode("\n", $errors) . "\n";
        }
        // Laporan audit ditulis ke storage aplikasi, bukan path Windows absolut:
        // path tetap membuat seluruh suite gagal di Linux/CI.
        $tujuan = storage_path('app/menu_audit.txt');
        if (! is_dir(dirname($tujuan))) {
            mkdir(dirname($tujuan), 0775, true);
        }
        file_put_contents($tujuan, $out);

        $this->assertSame([], $errors, 'Ada halaman error: ' . implode('; ', $errors));
    }

    /**
     * Regresi: rekap bulanan dashboard siswa harus dikelompokkan per bulan
     * (dulu memakai to_char() khusus PostgreSQL sehingga error di sqlite).
     */
    public function test_dashboard_siswa_menampilkan_rekap_bulanan(): void
    {
        $guru = User::factory()->guru()->create();
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(), 'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap, 'nis' => 'S001',
            'nama_peserta_didik' => 'Ani Contoh', 'kelas' => '1.A', 'jenis_kelamin' => 'P',
        ]);
        $su = User::factory()->siswa()->create(['nama_pengguna' => 'S001']);
        $tahun = date('Y');
        Kehadiran::create(['guru_id' => $guru->id, 'siswa_id' => $siswa->id, 'tanggal' => "{$tahun}-01-05", 'status' => 'H']);
        Kehadiran::create(['guru_id' => $guru->id, 'siswa_id' => $siswa->id, 'tanggal' => "{$tahun}-01-06", 'status' => 'S']);
        Kehadiran::create(['guru_id' => $guru->id, 'siswa_id' => $siswa->id, 'tanggal' => "{$tahun}-02-05", 'status' => 'H']);

        $res = $this->actingAs($su)->get(route('siswa.dashboard'))->assertOk();
        $data = $res->viewData('kehadiranBulanan');

        $this->assertCount(2, $data);
        $this->assertSame('Januari', $data[0]['nama']);
        $this->assertSame(1, $data[0]['H']);
        $this->assertSame(1, $data[0]['S']);
        $this->assertSame('Februari', $data[1]['nama']);
        $this->assertSame(1, $data[1]['H']);
    }

    public function test_fitur_penyelarasan_erapor_admin_guru_walas_dan_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $mapel = MataPelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'aktif' => true, 'masuk_transkrip' => true]);
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'S002',
            'nisn' => '0012345678',
            'nama_peserta_didik' => 'Budi Santoso',
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $siswaUser = User::factory()->siswa()->create(['nama_pengguna' => 'S002']);

        // 1. Admin toggle saklar global buka/tutup input nilai
        $this->assertFalse(\App\Models\Rombel::apakahTerkunciGlobal());
        $this->actingAs($admin)->post(route('admin.peta-kelas.toggle-kunci-semua'))->assertRedirect();
        $this->assertTrue(\App\Models\Rombel::apakahTerkunciGlobal());
        $this->assertTrue(\App\Models\Rombel::apakahTerkunciUntukKelas('6.A'));

        // Buka kembali agar Guru/Walas bisa input
        $this->actingAs($admin)->post(route('admin.peta-kelas.toggle-kunci-semua'))->assertRedirect();
        $this->assertFalse(\App\Models\Rombel::apakahTerkunciGlobal());

        // 2. Guru simpan Tujuan Pembelajaran (TP)
        $this->actingAs($guru)->post(route('guru.tp.store'), [
            'kode' => 'TP.6.1',
            'mata_pelajaran' => 'Matematika',
            'fase' => 'Fase C',
            'semester' => 'Genap',
            'deskripsi' => 'Memahami operasi hitung pecahan dan desimal',
        ])->assertRedirect(route('guru.tp.index'));

        // 3. Guru simpan Projek P5
        $this->actingAs($guru)->post(route('guru.p5.simpan'), [
            'kelas' => '6.A',
            'semester' => 'Genap',
            'tahun_ajaran' => '2025/2026',
            'tema' => 'Gaya Hidup Berkelanjutan',
            'dimensi' => 'Mandiri',
            'nilai' => [
                $siswa->id => ['predikat' => 'SB', 'catatan' => 'Sangat mandiri dalam projek'],
            ],
        ])->assertRedirect();
        $this->assertDatabaseHas('p5_penilaian', [
            'siswa_id' => $siswa->id,
            'predikat' => 'SB',
        ]);

        // 4. Wali Kelas simpan Nilai Ekskul, Kenaikan Kelas, dan Transkrip Ijazah
        $this->actingAs($guru)->post(route('guru.walas.ekskul.simpan'), [
            'kelas' => '6.A',
            'ekskul' => [
                $siswa->id => ['nama' => 'Pramuka', 'predikat' => 'Sangat Baik', 'keterangan' => 'Aktif dan disiplin'],
            ],
        ])->assertRedirect();
        $this->assertSame('Pramuka', $siswa->fresh()->rekaman['ekskul']['nama'] ?? null);

        $this->actingAs($guru)->post(route('guru.walas.naik-kelas.simpan'), [
            'kelas' => '6.A',
            'semester' => 'Genap',
            'tahun_ajaran' => '2025/2026',
            'keputusan' => [
                $siswa->id => ['naik_kelas' => '1', 'catatan' => 'Lulus dengan baik'],
            ],
        ])->assertRedirect();
        $this->assertDatabaseHas('kenaikan_kelas', [
            'siswa_id' => $siswa->id,
            'naik_kelas' => 1,
        ]);

        $this->actingAs($guru)->post(route('guru.walas.transkrip.simpan', $siswa->id), [
            'nomor_ijazah' => 'DN-19/SD/0001234',
            'nilai' => [$mapel->id => 92.5],
        ])->assertRedirect();
        $this->assertDatabaseHas('transkrip_nilai', [
            'siswa_id' => $siswa->id,
            'mata_pelajaran_id' => $mapel->id,
        ]);

        // 5. Siswa dapat mengakses halaman Rekap Capaian & Download Rapor Mandiri
        $this->actingAs($siswaUser)->get(route('siswa.rapor.capaian'))
            ->assertOk()
            ->assertSee('Gaya Hidup Berkelanjutan')
            ->assertSee('Pramuka');
        $this->actingAs($siswaUser)->get(route('siswa.rapor.unduh'))->assertOk();
        $this->actingAs($siswaUser)->get(route('siswa.rapor.cetak.akademik'))->assertOk();
        $this->actingAs($siswaUser)->get(route('siswa.rapor.cetak.p5'))->assertOk();
        $this->actingAs($siswaUser)->get(route('siswa.rapor.cetak.pelengkap'))->assertOk();

        // 6. Admin: Aksi Halaman Data Pengguna (/admin/users) setara e-Rapor
        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Tambah Pengguna')
            ->assertSee('Generate Semua Pengguna');

        $resetRes = $this->actingAs($admin)->postJson(route('admin.users.reset-password', $guru->id));
        $resetRes->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['password_baru', 'nama', 'message']);

        $this->actingAs($admin)->post(route('admin.users.toggle-aktif', $guru->id))->assertRedirect();
        $this->assertFalse((bool) $guru->fresh()->aktif);

        $this->actingAs($admin)->post(route('admin.users.toggle-aktif', $guru->id))->assertRedirect();
        $this->assertTrue((bool) $guru->fresh()->aktif);

        // Siapkan data PTK & Siswa tanpa akun untuk menguji Generate Semua Pengguna secara menyeluruh
        \App\Models\Ptk::create([
            'nama' => 'Siti Aminah',
            'nip' => '198501012010012001',
            'gelar_belakang' => 'S.Pd',
            'jenis_ptk' => 'Guru Kelas',
        ]);
        \App\Models\Ptk::create([
            'nama' => 'Ahmad Fauzi',
            'nip' => null,
            'nuptk' => null,
            'jenis_ptk' => 'Guru Mapel',
        ]);
        \App\Models\Ptk::create([
            'nama' => 'Ahmad Fauzi',
            'nip' => null,
            'nuptk' => null,
            'jenis_ptk' => 'Tenaga Kependidikan',
        ]);

        $siswaBaru = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'S099',
            'nisn' => '0099887766',
            'nama_peserta_didik' => 'Citra Lestari',
            'kelas' => '6.A',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);

        // Eksekusi pertama: harus membuat 3 akun PTK (2 guru + 1 tendik) dan 1 akun Siswa baru
        $genRes1 = $this->actingAs($admin)->postJson(route('admin.users.generate-semua'));
        $genRes1->assertOk()->assertJson([
            'success' => true,
            'guru_dibuat' => 3,
            'siswa_dibuat' => 1,
        ]);

        $userGuruBaru = User::where('nama_pengguna', '198501012010012001')->first();
        $this->assertNotNull($userGuruBaru);
        $this->assertSame('Siti Aminah, S.Pd', $userGuruBaru->nama_lengkap);
        $this->assertSame('198501012010012001@simantap.sch.id', $userGuruBaru->email);
        $this->assertDatabaseHas('pengaturan_guru', ['guru_id' => $userGuruBaru->id]);

        // Nama PTK kembar tanpa NIP harus mendapat username unik otomatis (ahmadfauzi & ahmadfauzi.1)
        $this->assertDatabaseHas('users', ['nama_pengguna' => 'ahmadfauzi', 'peran' => 'guru']);
        $this->assertDatabaseHas('users', ['nama_pengguna' => 'ahmadfauzi.1', 'peran' => 'tendik']);

        // Akun siswa baru harus terhubung ke ID siswa & menggunakan NISN sebagai username
        $userSiswaBaru = User::where('nama_pengguna', '0099887766')->first();
        $this->assertNotNull($userSiswaBaru);
        $this->assertSame([$siswaBaru->id], $userSiswaBaru->terhubung_dengan);
        $this->actingAs($userSiswaBaru)->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee('Citra Lestari');

        // Eksekusi kedua (idempoten): tidak boleh membuat duplikat
        $genRes2 = $this->actingAs($admin)->postJson(route('admin.users.generate-semua'));
        $genRes2->assertOk()->assertJson([
            'success' => true,
            'guru_dibuat' => 0,
            'siswa_dibuat' => 0,
        ]);

        // Uji pemulihan akun terhapus (soft-deleted): hapus akun siswa lalu generate ulang
        $userSiswaBaru->delete();
        $this->assertSoftDeleted('users', ['id' => $userSiswaBaru->id]);

        $genRes3 = $this->actingAs($admin)->postJson(route('admin.users.generate-semua'));
        $genRes3->assertOk()->assertJson([
            'success' => true,
            'siswa_dibuat' => 1,
            'dipulihkan' => 1,
        ]);
        $this->assertNotNull(User::find($userSiswaBaru->id));

        // 7. Admin: Pendidik & Peserta Didik (Edit Gelar/NIP Guru, Edit Data Pelengkap Siswa, Foto Siswa)
        $ptkSiti = \App\Models\Ptk::where('nama', 'Siti Aminah')->first();
        $this->actingAs($admin)->put(route('admin.referensi.gelar-ptk.update'), [
            'ptk' => [
                $ptkSiti->id => [
                    'nip' => '198501012010012099',
                    'gelar_depan' => 'Hj.',
                    'gelar_belakang' => 'S.Pd., M.Pd.',
                    'jenis_ptk' => 'Guru Kelas',
                    'jabatan' => 'Wali Kelas 6.A',
                ],
            ],
        ])->assertRedirect();
        $this->assertSame('198501012010012099', $ptkSiti->fresh()->nip);
        $this->assertSame('Hj. Siti Aminah, S.Pd., M.Pd.', $userGuruBaru->fresh()->nama_lengkap);

        $this->actingAs($admin)->put(route('admin.users.siswa.update', $siswaBaru->id), [
            'agama' => 'Islam',
            'tempat_lahir' => 'Makassar',
            'tanggal_lahir' => '2014-05-12',
            'alamat' => 'Jl. Perintis Kemerdekaan No. 10',
            'anak_ke' => 2,
            'status_dalam_keluarga' => 'Anak Kandung',
            'asal_sekolah' => 'TK Pertiwi Makassar',
            'diterima_di_kelas' => 'Kelas 1.A',
            'nama_ayah' => 'Hendra Wijaya',
            'pekerjaan_ayah' => 'Wiraswasta',
            'nama_ibu' => 'Rina Kartika',
            'pekerjaan_ibu' => 'PNS',
            'alamat_ortu' => 'Jl. Perintis Kemerdekaan No. 10',
            'telepon_ortu' => '081234567890',
        ])->assertRedirect();

        $this->assertSame('Jl. Perintis Kemerdekaan No. 10', $siswaBaru->fresh()->alamat);
        $this->assertSame('TK Pertiwi Makassar', $siswaBaru->fresh()->rekaman['pelengkap']['asal_sekolah'] ?? null);

        $this->actingAs($userSiswaBaru)->get(route('siswa.rapor.cetak.pelengkap'))
            ->assertOk()
            ->assertSee('TK Pertiwi Makassar')
            ->assertSee('Hendra Wijaya');

        $this->actingAs($admin)->get(route('admin.referensi.foto-siswa'))
            ->assertOk()
            ->assertSee('Reset dan Upload Masal Foto Siswa');
    }
}
