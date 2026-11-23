<?php

namespace App\Jobs;

use App\Models\Aktivitas;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\MesinPresensi\AttendanceDatabaseReader;
use App\Services\MesinPresensi\PresensiMesinPullService;
use App\Services\PresensiMesinService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TarikSemuaPresensiJob implements ShouldQueue
{
    use Queueable;

    /** Batas waktu lock: 10 menit, sama dengan TTL lock di controller. */
    public const KUNCI_LOCK = 'presensi-tarik-semua';

    public const TTL_LOCK = 600;

    public static function lockKey(?int $tenantId = null): string
    {
        $scope = tenantCacheScope($tenantId);

        return $scope === 'single' ? self::KUNCI_LOCK : self::KUNCI_LOCK.':'.$scope;
    }

    protected ?int $userId;

    // Null default aman untuk job lama yang sudah terserialisasi.
    protected ?int $tenantId;

    public function __construct(?int $userId, ?int $tenantId = null)
    {
        $this->userId = $userId;
        $this->tenantId = $tenantId ?? TenantContext::id();
    }

    /**
     * Tarik seluruh riwayat scan mesin di background. Dulu dijalankan sinkron
     * dalam request web dengan set_time_limit(600) — pasti menabrak timeout
     * Nginx/PHP-FPM dan bisa jalan ganda. Sekarang lewat queue worker.
     */
    public function handle(
        AttendanceDatabaseReader $reader,
        PresensiMesinService $presensi,
        PresensiMesinPullService $pull,
    ): void {
        try {
            $user = $this->penggunaValid();

            if ($user === null) {
                Log::warning('Tarik semua presensi dibatalkan: akun pemilik tidak valid lagi.');

                return;
            }

            TenantContext::run($this->tenantId, function () use ($reader, $presensi, $pull, $user): void {
                $this->tarik($reader, $presensi, $pull, $user);
            });
        } finally {
            // Lock dilepas di sini, bukan menunggu TTL 10 menit, agar admin bisa
            // mengulang penarikan segera setelah proses ini selesai/gagal.
            Cache::lock(self::lockKey($this->tenantId))->forceRelease();
        }
    }

    private function penggunaValid(): ?User
    {
        if ($this->userId === null) {
            return null;
        }

        if ($this->tenantId !== null && ! Tenant::whereKey($this->tenantId)->where('aktif', true)->exists()) {
            return null;
        }

        $query = User::query()->whereKey($this->userId)->where('aktif', true)->whereNull('archived_at');

        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        }

        return $query->first();
    }

    private function tarik(
        AttendanceDatabaseReader $reader,
        PresensiMesinService $presensi,
        PresensiMesinPullService $pull,
        User $user,
    ): void {
        set_time_limit(0);

        try {
            $rentang = $reader->rentang();
        } catch (\Throwable $e) {
            report($e);
            Log::error('Tarik semua presensi gagal membaca rentang: '.$e->getMessage());

            return;
        }

        $dari = $rentang['min'];
        $sampai = $rentang['maks'];

        if ($dari === null || $sampai === null) {
            $pull->tandaiTerakhirTarik();

            return;
        }

        try {
            $hasil = $pull->tarikSemuaRentang($reader, $presensi, $dari, $sampai, $user->id);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Tarik semua presensi gagal: '.$e->getMessage());

            return;
        }

        $pull->tandaiTerakhirTarik();

        $this->catatAktivitas($user, $dari->toDateString(), $sampai->toDateString(), $hasil);
    }

    private function catatAktivitas(User $user, string $dari, string $sampai, array $hasil): void
    {
        try {
            DB::transaction(fn () => Aktivitas::create([
                'uuid' => Str::uuid(),
                'tenant_id' => $user->tenant_id,
                'guru_id' => $user->id,
                'nama_guru' => $user->nama_lengkap,
                'jenis' => 'tambah',
                'judul' => 'Tarik presensi dari database mesin',
                'deskripsi' => "Tarik data mesin {$dari} s/d {$sampai}: {$hasil['scans']} scan, {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, {$hasil['tetap']} tetap, {$hasil['dilindungi']} dilindungi, {$hasil['nik_tidak_dikenal']} NIK tidak dikenal.",
                'tabel_terkait' => 'presensi_pegawai',
                'data_baru' => $hasil,
            ]));
        } catch (\Throwable $e) {
            // Pencatatan audit tidak boleh menggagalkan penarikan data.
            Log::warning('Gagal catat aktivitas tarik semua presensi: '.$e->getMessage());
        }
    }
}
