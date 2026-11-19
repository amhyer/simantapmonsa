<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\ResetMassalPasswordRequest;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Services\AktivitasService;
use App\Support\InitialPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MassPasswordResetService
{
    public function __construct(
        private readonly InitialPasswordExportService $exporter
    ) {}

    /**
     * @param  array{peran: array<int, string>, kelas?: ?string, hanya_belum_login?: bool, sertakan_sudah_diterbitkan?: bool}  $filters
     */
    public function buildQuery(array $filters, int $actorId): Builder
    {
        $roles = array_values(array_intersect(
            (array) ($filters['peran'] ?? ['siswa']),
            ResetMassalPasswordRequest::ALLOWED_ROLES
        ));

        if ($roles === []) {
            $roles = ['siswa'];
        }

        $query = User::forSchool()
            ->whereNull('deleted_at')
            ->whereNotIn('peran', ['admin', 'super_admin'])
            ->where('id', '!=', $actorId)
            ->whereIn('peran', $roles);

        $kelas = trim((string) ($filters['kelas'] ?? ''));
        if ($kelas !== '') {
            $query->where('kelas_mata_pelajaran', $kelas);
        }

        $hanyaBelumLogin = array_key_exists('hanya_belum_login', $filters)
            ? (bool) $filters['hanya_belum_login']
            : true;

        if ($hanyaBelumLogin) {
            $query->whereNull('terakhir_masuk');
        }

        $sertakanSudahDiterbitkan = (bool) ($filters['sertakan_sudah_diterbitkan'] ?? false);
        if (! $sertakanSudahDiterbitkan) {
            $query->whereNull('sandi_awal_diterbitkan_at');
        }

        return $query->orderBy('id');
    }

    /**
     * @return array{total_cocok: int, akan_diproses: int, sisa: int, batas: int, estimasi_detik: int}
     */
    public function preview(array $filters, int $actorId): array
    {
        $batas = max(1, (int) config('simantap.reset_massal_max', 100));
        $total = $this->buildQuery($filters, $actorId)->count();
        $akanDiproses = min($total, $batas);

        return [
            'total_cocok' => $total,
            'akan_diproses' => $akanDiproses,
            'sisa' => max(0, $total - $akanDiproses),
            'batas' => $batas,
            'estimasi_detik' => (int) ceil($akanDiproses * 0.27),
        ];
    }

    /**
     * @return array{locked: bool, diproses: int, sisa: int, batas: int, ekspor_url: ?string, message: string}
     */
    public function execute(array $filters, User $admin): array
    {
        $tenantId = TenantContext::id();
        $lockKey = 'reset_massal:tenant:'.($tenantId ?? 'single');
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            return [
                'locked' => true,
                'diproses' => 0,
                'sisa' => 0,
                'batas' => max(1, (int) config('simantap.reset_massal_max', 100)),
                'ekspor_url' => null,
                'message' => 'Proses reset sandi massal sedang berjalan untuk sekolah ini. Silakan tunggu sebentar.',
            ];
        }

        try {
            $batas = max(1, (int) config('simantap.reset_massal_max', 100));
            $baseQuery = $this->buildQuery($filters, (int) $admin->id);
            $total = $baseQuery->count();

            if ($total === 0) {
                return [
                    'locked' => false,
                    'diproses' => 0,
                    'sisa' => 0,
                    'batas' => $batas,
                    'ekspor_url' => null,
                    'message' => 'Tidak ada akun yang memenuhi kriteria reset massal.',
                ];
            }

            $targetIds = (clone $baseQuery)->limit($batas)->pluck('id')->all();
            $sisa = max(0, $total - count($targetIds));
            $credentials = [];
            $now = now();
            $eksporUrl = null;
            $rolesLabel = implode(', ', (array) ($filters['peran'] ?? ['siswa']));
            $kelasLabel = trim((string) ($filters['kelas'] ?? '')) ?: 'semua';

            DB::transaction(function () use ($targetIds, $now, $admin, $tenantId, $rolesLabel, $kelasLabel, $sisa, &$credentials, &$eksporUrl): void {
                User::forSchool()
                    ->whereIn('id', $targetIds)
                    ->orderBy('id')
                    ->chunkById(50, function ($users) use (&$credentials, $now): void {
                        foreach ($users as $user) {
                            $plain = InitialPassword::generate();
                            $forceChange = $user->peran === 'siswa'
                                ? InitialPassword::shouldForceChangeSiswa()
                                : true;

                            $user->forceFill([
                                'kata_sandi' => Hash::make($plain),
                                'force_password_change' => $forceChange,
                                'sandi_awal_diterbitkan_at' => $now,
                            ])->save();

                            $credentials[] = [
                                'nama' => (string) $user->nama_lengkap,
                                'kelas' => (string) ($user->kelas_mata_pelajaran ?: '-'),
                                'username' => (string) $user->nama_pengguna,
                                'password' => $plain,
                            ];
                        }
                    });

                AktivitasService::ubah(
                    'Reset Sandi Massal',
                    'Reset sandi massal untuk '.count($credentials)." akun (peran: {$rolesLabel}, kelas: {$kelasLabel}, sisa: {$sisa}).",
                    'users'
                );

                // Simpan ekspor terenkripsi sebelum commit agar kegagalan cache/ekspor ikut me-rollback DB.
                $eksporUrl = $credentials !== []
                    ? $this->exporter->store((int) $admin->id, $tenantId, $credentials)
                    : null;
            });

            $pesan = 'Reset sandi massal selesai untuk '.count($credentials).' akun.';
            if ($sisa > 0) {
                $pesan .= " Masih ada {$sisa} akun tersisa (batas {$batas} per permintaan). Jalankan lagi atau filter per kelas.";
            }

            return [
                'locked' => false,
                'diproses' => count($credentials),
                'sisa' => $sisa,
                'batas' => $batas,
                'ekspor_url' => $eksporUrl,
                'message' => $pesan,
            ];
        } finally {
            $lock->release();
        }
    }
}
