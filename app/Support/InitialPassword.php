<?php

namespace App\Support;

final class InitialPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Menghasilkan kata sandi awal acak per akun yang mudah dibaca
     * (tanpa karakter ambigu 0, O, o, 1, l, I) dengan panjang minimal 10.
     */
    public static function generate(int $length = 12): string
    {
        $targetLength = max(10, $length);
        $maxIndex = strlen(self::ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < $targetLength; $i++) {
            $password .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $password;
    }

    public static function shouldForceChangeSiswa(): bool
    {
        return (bool) config('simantap.force_change_siswa', true);
    }

    /**
     * Netralkan formula injection Excel/CSV pada sel yang diawali =, +, -, @.
     */
    public static function sanitizeCsvCell(?string $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }
}
