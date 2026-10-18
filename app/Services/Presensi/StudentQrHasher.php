<?php

namespace App\Services\Presensi;

use InvalidArgumentException;

class StudentQrHasher
{
    /**
     * Hanya fingerprint berbasis APP_KEY yang disimpan, bukan payload QR mentah.
     * Kartu lama dapat tetap dipakai tanpa menyimpan NISN/URL yang dikodekan.
     */
    public function hash(string $payload): string
    {
        $payload = trim($payload);

        if ($payload === '' || strlen($payload) > 2048) {
            throw new InvalidArgumentException('Isi QR kosong atau terlalu panjang.');
        }

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
