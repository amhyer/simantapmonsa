<?php

namespace App\Services\Exam;

use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

/**
 * Buffer jawaban ujian siswa berbasis skalar/Redis Hash (Redis-first autosave).
 *
 * ATURAN ARSITEKTUR:
 * - HANYA menyimpan array skalar `array<int, string>` (`[questionId => answer]`),
 *   TIDAK PERNAH menyimpan instance Eloquent Model ke cache.
 * - Jika store aktif adalah Redis (`RedisStore`), penulisan menggunakan operasi
 *   atomik `HSET` O(1) sehingga bebas race-condition antar-request.
 * - Jika store aktif adalah `array` / `database` / `file` (mis. PHPUnit atau
 *   instalasi single-node tanpa Redis), otomatis jatuh ke `Cache::get/put`.
 */
class ExamAnswerBuffer
{
    private const TTL_SECONDS = 21600; // 6 jam

    public function key(int $resultId): string
    {
        return "exam:cbt:buffer:{$resultId}";
    }

    public function put(int $resultId, int $questionId, string $answer): void
    {
        $store = Cache::getStore();
        if ($store instanceof RedisStore) {
            $conn = $store->connection();
            $redisKey = $store->getPrefix() . $this->key($resultId);
            $conn->hset($redisKey, (string) $questionId, $answer);
            $conn->expire($redisKey, self::TTL_SECONDS);

            return;
        }

        $current = $this->all($resultId);
        $current[$questionId] = $answer;
        Cache::put($this->key($resultId), $current, self::TTL_SECONDS);
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    public function putMany(int $resultId, array $answers): void
    {
        if ($answers === []) {
            return;
        }

        $normalized = [];
        foreach ($answers as $qId => $ans) {
            if (is_scalar($ans)) {
                $normalized[(int) $qId] = (string) $ans;
            }
        }

        if ($normalized === []) {
            return;
        }

        $store = Cache::getStore();
        if ($store instanceof RedisStore) {
            $conn = $store->connection();
            $redisKey = $store->getPrefix() . $this->key($resultId);
            foreach ($normalized as $qId => $ans) {
                $conn->hset($redisKey, (string) $qId, $ans);
            }
            $conn->expire($redisKey, self::TTL_SECONDS);

            return;
        }

        $current = $this->all($resultId);
        foreach ($normalized as $qId => $ans) {
            $current[$qId] = $ans;
        }
        Cache::put($this->key($resultId), $current, self::TTL_SECONDS);
    }

    /**
     * @return array<int, string>
     */
    public function all(int $resultId): array
    {
        $store = Cache::getStore();
        if ($store instanceof RedisStore) {
            $raw = $store->connection()->hgetall($store->getPrefix() . $this->key($resultId));
            if (! is_array($raw)) {
                return [];
            }

            $out = [];
            foreach ($raw as $qId => $ans) {
                if (is_scalar($ans)) {
                    $out[(int) $qId] = (string) $ans;
                }
            }

            return $out;
        }

        $cached = Cache::get($this->key($resultId), []);
        if (! is_array($cached)) {
            return [];
        }

        $out = [];
        foreach ($cached as $qId => $ans) {
            if (is_scalar($ans)) {
                $out[(int) $qId] = (string) $ans;
            }
        }

        return $out;
    }

    /**
     * Gabungkan jawaban yang sudah tersimpan di database dengan buffer terbaru.
     *
     * @param  array<int|string, mixed>  $persisted
     * @return array<int, string>
     */
    public function merge(int $resultId, array $persisted): array
    {
        $merged = [];
        foreach ($persisted as $qId => $ans) {
            if (is_scalar($ans)) {
                $merged[(int) $qId] = (string) $ans;
            }
        }

        foreach ($this->all($resultId) as $qId => $ans) {
            $merged[$qId] = $ans;
        }

        return $merged;
    }

    public function forget(int $resultId): void
    {
        $store = Cache::getStore();
        if ($store instanceof RedisStore) {
            $store->connection()->del($store->getPrefix() . $this->key($resultId));

            return;
        }

        Cache::forget($this->key($resultId));
    }
}
