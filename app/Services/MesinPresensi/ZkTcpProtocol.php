<?php

namespace App\Services\MesinPresensi;

use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Paket protokol ZKTeco standalone (pull) via TCP port 4370.
 *
 * Format paket TCP:
 *   50 50 82 7D | panjang payload (uint32 LE)
 *   | command (uint16) | checksum (uint16) | session (uint16) | reply (uint16) | data
 *
 * Format UDP memakai header 50 50 + panjang 16 bit. Memakai header UDP pada
 * koneksi TCP membuat mesin menutup koneksi atau membalas paket yang tidak
 * bisa diurai ("respons terlalu pendek").
 */
class ZkTcpProtocol
{
    public const MAGIC = "\x50\x50\x82\x7d";

    public const UKURAN_HEADER = 8;

    /**
     * Bentuk satu paket TCP lengkap dengan checksum.
     */
    public static function paket(int $command, int $sessionId, int $replyId, string $data = ''): string
    {
        $payload = pack('v', $command)
            .pack('v', 0)
            .pack('v', $sessionId)
            .pack('v', $replyId)
            .$data;

        $payload = substr_replace($payload, pack('v', self::checksum($payload)), 2, 2);

        return self::MAGIC.pack('V', strlen($payload)).$payload;
    }

    /**
     * Checksum protokol ZK: jumlah 16 bit little endian, lipat carry, lalu
     * komplemen satu.
     */
    public static function checksum(string $payload): int
    {
        if (strlen($payload) % 2 === 1) {
            $payload .= "\x00";
        }

        $jumlah = 0;
        $panjang = strlen($payload);
        for ($i = 0; $i < $panjang; $i += 2) {
            $jumlah += unpack('v', substr($payload, $i, 2))[1];
        }

        while ($jumlah > 0xFFFF) {
            $jumlah = ($jumlah & 0xFFFF) + ($jumlah >> 16);
        }

        return (~$jumlah) & 0xFFFF;
    }

    /**
     * Urai payload (tanpa header 8 byte) menjadi field-field protokol.
     *
     * @return array{command: int, sessionId: int, replyId: int, data: string}
     */
    public static function urai(string $payload): array
    {
        if (strlen($payload) < 8) {
            throw new RuntimeException('Payload balasan mesin terlalu pendek ('.strlen($payload).' byte).');
        }

        return [
            'command' => (int) unpack('v', substr($payload, 0, 2))[1],
            'sessionId' => (int) unpack('v', substr($payload, 4, 2))[1],
            'replyId' => (int) unpack('v', substr($payload, 6, 2))[1],
            'data' => substr($payload, 8),
        ];
    }

    /**
     * Hitung communication key dari password mesin dan session id.
     *
     * Algoritma ini sama dengan MakeKey() pada commpro.c / pyzk:
     * balik 32 bit kunci, tambahkan session id, XOR dengan "ZKSO", tukar
     * dua bagian 16 bit, lalu sisipkan nilai ticks pada byte 0, 1, dan 3.
     */
    public static function commKey(int $key, int $sessionId, int $ticks = 50): string
    {
        $key &= 0xFFFFFFFF;

        $k = 0;
        for ($i = 0; $i < 32; $i++) {
            $k = (($k << 1) | (($key >> $i) & 1)) & 0xFFFFFFFF;
        }

        $k = ($k + ($sessionId & 0xFFFF)) & 0xFFFFFFFF;

        $b = [
            ($k & 0xFF) ^ 0x5A,          // 'Z'
            (($k >> 8) & 0xFF) ^ 0x4B,   // 'K'
            (($k >> 16) & 0xFF) ^ 0x53,  // 'S'
            (($k >> 24) & 0xFF) ^ 0x4F,  // 'O'
        ];

        // Tukar dua bagian 16 bit (little endian).
        [$b[0], $b[1], $b[2], $b[3]] = [$b[2], $b[3], $b[0], $b[1]];

        $t = $ticks & 0xFF;
        $b[0] ^= $t;
        $b[1] ^= $t;
        $b[2] = $t;
        $b[3] ^= $t;

        return pack('C4', $b[0], $b[1], $b[2], $b[3]);
    }

    /**
     * Waktu mesin dikodekan sebagai:
     * ((tahun%100)*12*31 + (bulan-1)*31 + (hari-1))*86400 + jam*3600 + menit*60 + detik.
     *
     * Dekode per komponen; addSeconds() pada kalender biasa akan bergeser
     * karena protokol menganggap setiap bulan 31 hari.
     */
    public static function waktu(int $detik): Carbon
    {
        $detik = max(0, $detik);

        $second = $detik % 60;
        $detik = intdiv($detik, 60);
        $minute = $detik % 60;
        $detik = intdiv($detik, 60);
        $hour = $detik % 24;
        $detik = intdiv($detik, 24);
        $hari = $detik % 31 + 1;
        $detik = intdiv($detik, 31);
        $bulan = $detik % 12 + 1;
        $tahun = intdiv($detik, 12) + 2000;

        return Carbon::create($tahun, $bulan, $hari, $hour, $minute, $second);
    }

    /**
     * Urai satu record presensi sesuai ukuran record firmware.
     *
     * @return array{userId: string, waktu: Carbon, state: int, verifyType: int}|null
     */
    public static function record(string $record, int $recordSize): ?array
    {
        if ($recordSize <= 0 || strlen($record) < $recordSize) {
            return null;
        }

        if ($recordSize === 8) {
            $userId = (string) unpack('v', substr($record, 0, 2))[1];
            $state = ord($record[2]);
            $detik = (int) unpack('V', substr($record, 3, 4))[1];
            $verifyType = ord($record[7]);
        } elseif ($recordSize === 16) {
            $userId = (string) unpack('V', substr($record, 0, 4))[1];
            $detik = (int) unpack('V', substr($record, 4, 4))[1];
            $state = ord($record[8]);
            $verifyType = ord($record[9]);
        } elseif ($recordSize === 40) {
            $userId = trim(substr($record, 2, 9), "\x00 ");
            $verifyType = ord($record[26]);
            $detik = (int) unpack('V', substr($record, 27, 4))[1];
            $state = ord($record[31]);

            // Record 40 byte menyimpan user id sebagai teks; tolak data biner
            // agar record 16 byte tidak salah dikenali sebagai record 40 byte.
            if ($userId === '' || preg_match('/^[\x20-\x7E]+$/', $userId) !== 1) {
                return null;
            }
        } else {
            return null;
        }

        if ($userId === '' || $userId === '0') {
            return null;
        }

        try {
            $waktu = self::waktu($detik);
        } catch (Throwable) {
            return null;
        }

        return [
            'userId' => $userId,
            'waktu' => $waktu,
            'state' => $state,
            'verifyType' => $verifyType,
        ];
    }

    /**
     * Tebak ukuran record dari panjang data: 40 (teks), 16, atau 8 byte.
     */
    public static function ukuranRecord(string $data): int
    {
        foreach ([40, 16, 8] as $ukuran) {
            if (strlen($data) < $ukuran || strlen($data) % $ukuran !== 0) {
                continue;
            }

            if (self::record(substr($data, 0, $ukuran), $ukuran) !== null) {
                return $ukuran;
            }
        }

        return 0;
    }
}
