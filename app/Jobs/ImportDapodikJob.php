<?php

namespace App\Jobs;

use App\Models\Aktivitas;
use App\Models\DapodikSyncLog;
use App\Models\OrangTuaSiswa;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportDapodikJob implements ShouldQueue
{
    use Queueable;

    protected array $data;

    protected $logId;

    protected string $tipeSync;

    protected $userId;

    protected string $namaGuru;

    // Null default juga aman untuk job lama yang sudah terserialisasi.
    protected ?int $tenantId = null;

    public function __construct(array $data, $logId = null, string $tipeSync = 'semua', $userId = null, string $namaGuru = 'Dapodik Bridge', ?int $tenantId = null)
    {
        $this->data = $data;
        $this->logId = $logId;
        $this->tipeSync = $tipeSync;
        $this->userId = $userId;
        $this->namaGuru = $namaGuru;
        $this->tenantId = $tenantId ?? TenantContext::id();
    }

    public function handle(): void
    {
        // Job lama yang tidak menyimpan konteks sekolah tidak boleh menulis
        // tanpa scope pada instalasi multi-tenant atau milik akun ber-tenant.
        if ($this->tenantId === null && (config('simantap.multi_tenant_enabled', false)
            || Tenant::query()->exists()
            || ($this->userId && User::whereKey($this->userId)->whereNotNull('tenant_id')->exists()))) {
            throw new \RuntimeException('Impor Dapodik tidak memiliki konteks sekolah. Jalankan ulang impor dari akun sekolah.');
        }

        if ($this->tenantId !== null && (! Tenant::whereKey($this->tenantId)->where('aktif', true)->exists()
            || ! $this->userId
            || ! User::whereKey($this->userId)->where('tenant_id', $this->tenantId)->where('aktif', true)->exists())) {
            throw new \RuntimeException('Sekolah atau akun pemilik impor tidak lagi valid. Jalankan ulang impor.');
        }

        TenantContext::run($this->tenantId, fn () => $this->import());
    }

    private function import(): void
    {
        set_time_limit(0);

        $berhasil = 0;
        $gagal = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $errors = [];

        $rows = $this->data['siswa'] ?? [];
        $tipeSync = $this->tipeSync;

        foreach (array_chunk($rows, 50) as $chunkIndex => $chunk) {
            try {
                DB::transaction(function () use ($chunk, $chunkIndex, $tipeSync, &$berhasil, &$gagal, &$diperbarui, &$dilewati, &$errors) {
                    foreach ($chunk as $i => $s) {
                        $baris = $chunkIndex * 50 + $i + 1;
                        try {
                            $existing = null;
                            if (!empty($s['nisn'])) {
                                $existing = Siswa::where('nisn', $s['nisn'])->first();
                            }
                            if (!$existing && !empty($s['nik'])) {
                                $existing = Siswa::where('nik', $s['nik'])->first();
                            }

                            if ($existing && $tipeSync === 'baru') {
                                $dilewati++;
                                continue;
                            }

                            $siswaData = [
                                'nis' => $s['nisn'] ?? $s['nis'] ?? 'DAPODIK-' . Str::random(8),
                                'nisn' => $s['nisn'] ?? null,
                                'nik' => $s['nik'] ?? null,
                                'no_kk' => $s['no_kk'] ?? null,
                                'nama_peserta_didik' => $s['nama'],
                                'kelas' => $s['kelas'],
                                'jenis_kelamin' => $s['jenis_kelamin'],
                                'agama' => $s['agama'] ?? null,
                                'tempat_lahir' => $s['tempat_lahir'] ?? null,
                                'tanggal_lahir' => $s['tanggal_lahir'] ?? null,
                                'nama_orang_tua' => $s['nama_ayah'] ?? $s['nama_ibu'] ?? $s['nama_wali'] ?? 'ORT' . rand(1000, 9999),
                                'anak_ke' => $s['anak_ke'] ?? null,
                                'jumlah_saudara' => $s['jumlah_saudara'] ?? null,
                                'tinggi_badan' => $s['tinggi_badan'] ?? null,
                                'berat_badan' => $s['berat_badan'] ?? null,
                                'golongan_darah' => $s['golongan_darah'] ?? null,
                                'penerima_kip' => !empty($s['no_kip']),
                                'no_kip' => $s['no_kip'] ?? null,
                                'aktif' => true,
                                'rekaman' => array_merge($s, [
                                    'dapodik_imported_at' => now()->toIso8601String(),
                                    'tahun_ajaran' => $this->data['tahun_ajaran'] ?? null,
                                    'semester' => $this->data['semester'] ?? null,
                                    'sekolah_npsn' => $this->data['sekolah']['npsn'] ?? null,
                                    'alamat_lengkap' => $s['alamat'] ?? null,
                                    'rt' => $s['rt'] ?? null,
                                    'rw' => $s['rw'] ?? null,
                                    'dusun' => $s['dusun'] ?? null,
                                    'desa' => $s['desa'] ?? null,
                                    'kecamatan' => $s['kecamatan'] ?? null,
                                    'kabupaten' => $s['kabupaten'] ?? null,
                                    'provinsi' => $s['provinsi'] ?? null,
                                    'kode_pos' => $s['kode_pos'] ?? null,
                                    'telepon' => $s['telepon'] ?? null,
                                    'handphone' => $s['handphone'] ?? null,
                                    'email' => $s['email'] ?? null,
                                ]),
                            ];

                            if ($existing) {
                                $existing->update($siswaData);
                                $diperbarui++;
                            } else {
                                $existing = Siswa::create(array_merge($siswaData, [
                                    'uuid' => Str::uuid(),
                                    'guru_id' => null,
                                    'nama_guru' => null,
                                ]));
                                $berhasil++;
                            }

                            $this->saveOrangTua($existing, $s);
                            $this->ensureStudentUser($existing);
                        } catch (\Exception $e) {
                            $gagal++;
                            $errors[] = 'Baris ' . $baris . ': ' . $e->getMessage();
                            Log::warning('Gagal import baris ' . $baris . ': ' . $e->getMessage());
                        }
                    }
                });
            } catch (\Exception $e) {
                Log::error('Gagal commit chunk import dapodik: ' . $e->getMessage());
            }
        }

        if ($this->logId) {
            try {
                $log = DapodikSyncLog::find($this->logId);
                if ($log) {
                    $log->update([
                        'berhasil' => $berhasil,
                        'gagal' => $gagal,
                        'diperbarui' => $diperbarui,
                        'dilewati' => $dilewati,
                        'errors' => $errors,
                        'finished_at' => now(),
                        'duration_seconds' => now()->diffInSeconds($log->started_at ?? now()),
                        'summary' => [
                            'total_diterima' => count($rows),
                            'baru' => $berhasil,
                            'update' => $diperbarui,
                            'skip' => $dilewati,
                        ],
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Gagal update log import dapodik: ' . $e->getMessage());
            }
        }

        try {
            if (TenantContext::check()) {
                return;
            }
            Aktivitas::create([
                'uuid' => Str::uuid(),
                'guru_id' => $this->userId,
                'nama_guru' => $this->namaGuru,
                'jenis' => 'pengaturan',
                'judul' => 'Import dari Dapodik',
                'deskripsi' => sprintf(
                    'Sekolah %s: %d baru, %d diperbarui, %d dilewati, %d gagal',
                    $this->data['sekolah']['nama'] ?? '-', $berhasil, $diperbarui, $dilewati, $gagal
                ),
                'tabel_terkait' => 'siswa',
            ]);
        } catch (\Exception $e) {
            Log::warning('Gagal catat aktivitas import: ' . $e->getMessage());
        }
    }

    protected function saveOrangTua(?Siswa $siswa, array $s): void
    {
        if (!$siswa) {
            return;
        }

        if (!empty($s['nama_ayah'])) {
            OrangTuaSiswa::updateOrCreate(
                ['siswa_id' => $siswa->id, 'tipe' => 'ayah'],
                [
                    'nama' => $s['nama_ayah'],
                    'nik' => $s['nik_ayah'] ?? null,
                    'tahun_lahir' => $s['tahun_lahir_ayah'] ?? null,
                    'pendidikan' => $s['pendidikan_ayah'] ?? null,
                    'pekerjaan' => $s['pekerjaan_ayah'] ?? null,
                    'penghasilan' => $s['penghasilan_ayah'] ?? null,
                    'no_telepon' => $s['telepon_ayah'] ?? $s['handphone'] ?? null,
                ]
            );
        }

        if (!empty($s['nama_ibu'])) {
            OrangTuaSiswa::updateOrCreate(
                ['siswa_id' => $siswa->id, 'tipe' => 'ibu'],
                [
                    'nama' => $s['nama_ibu'],
                    'nik' => $s['nik_ibu'] ?? null,
                    'tahun_lahir' => $s['tahun_lahir_ibu'] ?? null,
                    'pendidikan' => $s['pendidikan_ibu'] ?? null,
                    'pekerjaan' => $s['pekerjaan_ibu'] ?? null,
                    'penghasilan' => $s['penghasilan_ibu'] ?? null,
                    'no_telepon' => $s['telepon_ibu'] ?? $s['handphone'] ?? null,
                ]
            );
        }

        if (!empty($s['nama_wali'])) {
            OrangTuaSiswa::updateOrCreate(
                ['siswa_id' => $siswa->id, 'tipe' => 'wali'],
                [
                    'nama' => $s['nama_wali'],
                    'pekerjaan' => $s['pekerjaan_wali'] ?? null,
                    'no_telepon' => $s['telepon_wali'] ?? null,
                ]
            );
        }
    }

    private function ensureStudentUser(Siswa $siswa): void
    {
        try {
            $username = $siswa->nis ?: ($siswa->nisn ?: 'siswa' . $siswa->id);
            $user = User::where('peran', 'siswa')
                ->where('tenant_id', TenantContext::id())
                ->where(function ($q) use ($siswa, $username) {
                    $q->whereJsonContains('terhubung_dengan', $siswa->id)
                      ->orWhere('nama_pengguna', $username)
                      ->orWhere('nama_pengguna', 'siswa' . $username);
                })
                ->first();

            if ($user) {
                $terhubung = (array) ($user->terhubung_dengan ?? []);
                if (!in_array($siswa->id, $terhubung, true)) {
                    $terhubung[] = $siswa->id;
                }
                $user->update([
                    'nama_lengkap' => $siswa->nama_peserta_didik,
                    'kelas_mata_pelajaran' => $siswa->kelas,
                    'aktif' => (bool) $siswa->aktif,
                    'terhubung_dengan' => array_values(array_unique($terhubung)),
                ]);
            } else {
                // Username unik secara global. Jangan mengubah akun sekolah lain.
                if (User::withTrashed()->where('nama_pengguna', $username)->exists()) {
                    Log::warning("Akun siswa tidak dibuat: username sudah digunakan ({$username}).");
                    return;
                }
                User::create([
                    'uuid' => (string) Str::uuid(),
                    'nama_lengkap' => $siswa->nama_peserta_didik,
                    'nama_pengguna' => $username,
                    // Akun baru harus diaktifkan melalui reset sandi oleh admin.
                    'kata_sandi' => bcrypt(Str::random(32)),
                    'peran' => 'siswa',
                    'terhubung_dengan' => [$siswa->id],
                    'kelas_mata_pelajaran' => $siswa->kelas,
                    'aktif' => (bool) $siswa->aktif,
                    'force_password_change' => true,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Gagal auto-create akun user siswa {$siswa->id}: " . $e->getMessage());
        }
    }
}
