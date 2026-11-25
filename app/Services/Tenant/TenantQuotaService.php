<?php

namespace App\Services\Tenant;

use App\Models\PembelajaranMendalam\AiUsageLog;
use App\Models\PengaturanGuru;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TenantQuotaService
{
    public const DEFAULT_KUOTA_SISWA = 500;

    public const DEFAULT_KUOTA_STORAGE_MB = 2048;

    public static function defaultAiQuotaForPaket(?string $paket): int
    {
        return match (strtolower((string) ($paket ?? 'standar'))) {
            'pro' => 250,
            'enterprise' => 1000,
            default => 60,
        };
    }

    /**
     * @return array{used: int, limit: int, remaining: int, percent: float, exceeded: bool}
     */
    public function studentQuotaStatus(int $tenantId, ?Tenant $tenant = null): array
    {
        $tenantModel = $tenant ?? Tenant::find($tenantId);
        $limit = max(10, (int) ($tenantModel?->kuota_siswa ?: self::DEFAULT_KUOTA_SISWA));

        $siswaRows = Siswa::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();
        $siswaUsers = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('peran', 'siswa')
            ->count();

        $used = max($siswaRows, $siswaUsers);
        $remaining = max(0, $limit - $used);
        $percent = $limit > 0 ? round(($used / $limit) * 100, 1) : 0.0;

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percent' => $percent,
            'exceeded' => $used >= $limit,
        ];
    }

    public function assertCanAddStudents(?int $tenantId, int $additional = 1, string $field = 'kuota_siswa'): void
    {
        if ($tenantId === null || $additional <= 0) {
            return;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        $status = $this->studentQuotaStatus($tenantId, $tenant);
        if (($status['used'] + $additional) > $status['limit']) {
            throw ValidationException::withMessages([
                $field => sprintf(
                    'Kuota siswa paket sekolah Anda telah mencapai batas (%s dari %s siswa). Hubungi Super Administrator untuk meningkatkan paket layanan.',
                    number_format($status['used']),
                    number_format($status['limit'])
                ),
            ]);
        }
    }

    /**
     * @return array{used_bytes: int, used_mb: float, limit_mb: int, limit_bytes: int, remaining_bytes: int, percent: float, exceeded: bool}
     */
    public function storageQuotaStatus(int $tenantId, ?Tenant $tenant = null, bool $fresh = false): array
    {
        $tenantModel = $tenant ?? Tenant::find($tenantId);
        $limitMb = max(100, (int) ($tenantModel?->kuota_storage_mb ?: self::DEFAULT_KUOTA_STORAGE_MB));
        $limitBytes = $limitMb * 1048576;

        $cacheKey = "tenant:storage_bytes:{$tenantId}";
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $usedBytes = (int) Cache::remember($cacheKey, 60, fn (): int => $this->calculateTenantStorageBytes($tenantId));
        $usedMb = round($usedBytes / 1048576, 2);
        $remainingBytes = max(0, $limitBytes - $usedBytes);
        $percent = $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 1) : 0.0;

        return [
            'used_bytes' => $usedBytes,
            'used_mb' => $usedMb,
            'limit_mb' => $limitMb,
            'limit_bytes' => $limitBytes,
            'remaining_bytes' => $remainingBytes,
            'percent' => $percent,
            'exceeded' => $usedBytes >= $limitBytes,
        ];
    }

    public function assertCanStoreBytes(?int $tenantId, int $incomingBytes, string $field = 'file'): void
    {
        if ($tenantId === null || $incomingBytes <= 0) {
            return;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        $status = $this->storageQuotaStatus($tenantId, $tenant);
        if (($status['used_bytes'] + $incomingBytes) > $status['limit_bytes']) {
            $incomingMb = round($incomingBytes / 1048576, 2);
            throw ValidationException::withMessages([
                $field => sprintf(
                    'Kuota penyimpanan sekolah tidak mencukupi (terpakai %s MB dari %s MB, ukuran unggahan %s MB). Hapus berkas lama atau tingkatkan paket sekolah.',
                    number_format($status['used_mb'], 2),
                    number_format($status['limit_mb']),
                    number_format($incomingMb, 2)
                ),
            ]);
        }
    }

    public function forgetStorageCache(?int $tenantId): void
    {
        if ($tenantId !== null) {
            Cache::forget("tenant:storage_bytes:{$tenantId}");
        }
    }

    /**
     * Hitung ukuran total semua UploadedFile pada request HTTP.
     *
     * @param  array<string, mixed>  $files
     */
    public function sumUploadedFilesBytes(array $files): int
    {
        $total = 0;
        foreach ($files as $item) {
            if ($item instanceof UploadedFile) {
                $size = $item->getSize();
                if (is_int($size) && $size > 0) {
                    $total += $size;
                }
            } elseif (is_array($item)) {
                $total += $this->sumUploadedFilesBytes($item);
            }
        }

        return $total;
    }

    /**
     * Resolusi plafon kuota AI harian sekolah berdasarkan paket tenant & kepemilikan API key.
     *
     * @return array{limit: int, cap: ?int, is_byok: bool, used_today: int, remaining_today: int}
     */
    public function aiQuotaSummary(?int $tenantId, int $requestedQuota, bool $hasOwnApiKey = false): array
    {
        $requested = $requestedQuota > 0 ? $requestedQuota : 60;
        $cap = null;

        if ($tenantId !== null) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $meta = is_array($tenant->meta) ? $tenant->meta : [];
                $customCap = isset($meta['kuota_ai_harian']) ? (int) $meta['kuota_ai_harian'] : 0;
                $cap = $customCap > 0 ? $customCap : self::defaultAiQuotaForPaket($tenant->paket);
            }
        }

        $effectiveLimit = (! $hasOwnApiKey && $cap !== null)
            ? min($requested, $cap)
            : $requested;

        $usedToday = AiUsageLog::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('tanggal', now()->toDateString())
            ->where('status', 'ok')
            ->count();

        return [
            'limit' => $effectiveLimit,
            'cap' => $cap,
            'is_byok' => $hasOwnApiKey,
            'used_today' => $usedToday,
            'remaining_today' => max(0, $effectiveLimit - $usedToday),
        ];
    }

    private function calculateTenantStorageBytes(int $tenantId): int
    {
        $total = 0;
        $seen = [];

        $addFile = function (string $diskName, string $path) use (&$total, &$seen): void {
            $clean = ltrim(trim($path), '/');
            if ($clean === '') {
                return;
            }
            $key = "{$diskName}:{$clean}";
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;

            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($clean)) {
                    $total += max(0, (int) $disk->size($clean));
                }
            } catch (\Throwable) {
                // Abaikan berkas yang tidak dapat diakses pada sistem file sementara.
            }
        };

        // 1. Direktori khusus tenant (E-Learning & direktori tenant generik)
        foreach (['local', 'public'] as $diskName) {
            foreach (["elearning/tenant-{$tenantId}", "tenants/{$tenantId}"] as $dir) {
                try {
                    $disk = Storage::disk($diskName);
                    if ($disk->exists($dir)) {
                        foreach ($disk->allFiles($dir) as $filePath) {
                            $addFile($diskName, $filePath);
                        }
                    }
                } catch (\Throwable) {
                    // Abaikan bila direktori belum ada.
                }
            }
        }

        // 2. Foto peserta didik milik tenant
        $fotoPaths = Siswa::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('foto')
            ->pluck('foto');

        foreach ($fotoPaths as $foto) {
            $path = trim((string) $foto);
            if ($path !== '') {
                $addFile('local', $path);
                $addFile('public', $path);
            }
        }

        // 3. Berkas identitas sekolah & tanda tangan milik tenant
        $settingsRows = SekolahSettings::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get(['pengaturan']);

        foreach ($settingsRows as $row) {
            $cfg = is_array($row->pengaturan) ? $row->pengaturan : [];
            foreach (['logo_sekolah', 'logo_pemda', 'ttd_kepsek', 'ttd_pengawas', 'stempel_sekolah'] as $k) {
                if (! empty($cfg[$k]) && is_string($cfg[$k])) {
                    $addFile('local', $cfg[$k]);
                    $addFile('public', $cfg[$k]);
                }
            }
        }

        $guruSettings = PengaturanGuru::withoutGlobalScopes()
            ->whereIn(
                'guru_id',
                User::withoutGlobalScopes()->where('tenant_id', $tenantId)->select('id')
            )
            ->get(['pengaturan']);

        foreach ($guruSettings as $gRow) {
            $gCfg = is_array($gRow->pengaturan) ? $gRow->pengaturan : [];
            if (! empty($gCfg['ttd_guru']) && is_string($gCfg['ttd_guru'])) {
                $addFile('local', $gCfg['ttd_guru']);
                $addFile('public', $gCfg['ttd_guru']);
            }
        }

        return $total;
    }
}
