<?php

namespace App\Services\Exam;

use Illuminate\Support\Facades\Cache;

/**
 * Pelindung idempotensi berbasis atomic lock (Redis / Cache Lock) untuk
 * mencegah race-condition double-submit pada pengumpulan ujian.
 */
class DistributedConsistencyGuard
{
    public function lockKey(string $scope, int|string $key): string
    {
        return "exam:guard:{$scope}:{$key}";
    }

    /**
     * Jalankan callback di dalam atomic lock non-blocking.
     * Jika proses identik sedang berjalan (mis. siswa klik tombol Kumpulkan
     * berkali-kali saat jaringan lambat), tolak langsung dengan HTTP 409 tanpa
     * menahan worker PHP-FPM.
     */
    public function run(string $scope, int|string $key, callable $callback, int $ttlSeconds = 10): mixed
    {
        $lock = Cache::lock($this->lockKey($scope, $key), $ttlSeconds);

        if (! $lock->get()) {
            abort(409, 'Proses pengumpulan sedang berlangsung, mohon tunggu sebentar.');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
