<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\User;
use App\Models\Siswa;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProsesAkunMassalJob implements ShouldQueue
{
    use Queueable;

    protected $requestData;

    public function __construct($requestData)
    {
        $this->requestData = $requestData;
    }

    public function handle(): void
    {
        // Job lama tidak menyimpan konteks tenant dan memakai insert mentah.
        // Jangan mengeksekusi job yang sudah antre pada instalasi ber-tenant.
        if (TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
            throw new \RuntimeException('Proses akun massal belum aman untuk multi-sekolah.');
        }

        $siswa = Siswa::whereIn('id', $this->requestData['siswa_ids'] ?? [])->get();
        $created = 0;
        $usersToInsert = [];

        foreach ($siswa as $s) {
            if ($this->requestData['buat_ortu'] ?? false) {
                $username = 'ortu' . $s->nis;
                if (!User::where('nama_pengguna', $username)->exists()) {
                    $usersToInsert[] = [
                        'uuid' => (string) Str::uuid(),
                        'nama_lengkap' => 'Orang Tua ' . $s->nama_peserta_didik,
                        'nama_pengguna' => $username,
                        'kata_sandi' => Hash::make(Str::random(8)),
                        'peran' => 'ortu',
                        'terhubung_dengan' => json_encode([$s->id]),
                        'kelas_mata_pelajaran' => $s->kelas,
                        'aktif' => true,
                        'force_password_change' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $created++;
                }
            }

            if ($this->requestData['buat_siswa'] ?? true) {
                $username = 'siswa' . $s->nis;
                if (!User::where('nama_pengguna', $username)->exists()) {
                    $usersToInsert[] = [
                        'uuid' => (string) Str::uuid(),
                        'nama_lengkap' => $s->nama_peserta_didik,
                        'nama_pengguna' => $username,
                        'kata_sandi' => Hash::make(Str::random(8)),
                        'peran' => 'siswa',
                        'terhubung_dengan' => json_encode([$s->id]),
                        'kelas_mata_pelajaran' => $s->kelas,
                        'aktif' => true,
                        'force_password_change' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $created++;
                }
            }
        }

        // Insert dalam batch 50
        if ($usersToInsert) {
            $chunks = array_chunk($usersToInsert, 50);
            foreach ($chunks as $chunk) {
                User::insert($chunk);
            }
        }

        Log::info("Proses akun massal selesai: {$created} akun berhasil dibuat");
    }
}
