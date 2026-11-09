<?php

namespace App\Services\MesinPresensi;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Memetakan daftar pegawai (PIN/No.PIN + nama) dari mesin fingerprint
 * ke akun pegawai SIMANTAP, lalu menyimpan PIN tersebut sebagai
 * `users.nik`.
 *
 * Logika ini dipakai bersama oleh halaman admin (manual) dan endpoint
 * API bridge (POST /api/presensi/mesin/sinkron) agar hasilnya identik.
 *
 * Aturan aman tetap: hanya NIK yang masih kosong yang diisi; NIK yang
 * sudah berbeda tidak ditimpa (dibandingkan sebagai "bentrok").
 */
class PresensiMesinKaryawanService
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    private const TOKEN_GELAR = [
        's', 'm', 'a', 'pd', 'si', 'sos', 'kom', 'ag', 'hi', 'ip', 'pt', 'ab',
        'iq', 'fil', 'psi', 'keb', 'kes', 'farm', 'pod', 'kp', 'kep', 'th',
        'mm', 'se', 'sh', 'ma', 'mba', 'phd', 'apt', 'drs', 'dra', 'prof',
        'st', 'mt', 'ssi', 'sst', 'spt', 'skm', 'sip', 'shi', 'sag',
    ];

    /**
     * @param  list<array{nik: string, nama: string}>  $pasangan
     * @return array{baris: int, diperbarui: int, tetap: int, tidak_cocok: int, ambigu: int, bentrok: int, contoh_tidak_cocok: list<string>}
     */
    public function simpanNikKaryawan(array $pasangan): array
    {
        $users = User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)->get();

        $nikTerpakai = [];
        foreach ($users as $user) {
            $nikLama = $this->normalizeNikValue($user->nik);
            if ($nikLama !== null) {
                $nikTerpakai[$nikLama] = $user->id;
            }
        }

        $hasil = [
            'baris' => 0,
            'diperbarui' => 0,
            'tetap' => 0,
            'tidak_cocok' => 0,
            'ambigu' => 0,
            'bentrok' => 0,
            'contoh_tidak_cocok' => [],
        ];

        DB::transaction(function () use ($pasangan, $users, &$nikTerpakai, &$hasil) {
            foreach ($pasangan as $item) {
                $nik = $this->normalizeNikValue($item['nik']);
                $nama = trim((string) ($item['nama'] ?? ''));

                if ($nik === null || $nama === '') {
                    continue;
                }

                $hasil['baris']++;

                $kandidat = $this->matchUsersByName($nama, $users);
                if ($kandidat === []) {
                    $hasil['tidak_cocok']++;
                    if (count($hasil['contoh_tidak_cocok']) < 8) {
                        $hasil['contoh_tidak_cocok'][] = $nama;
                    }

                    continue;
                }
                if (count($kandidat) > 1) {
                    $hasil['ambigu']++;

                    continue;
                }

                /** @var User $user */
                $user = $kandidat[0];
                $nikLama = $this->normalizeNikValue($user->nik);

                if ($nikLama !== null && $nikLama !== $nik) {
                    $hasil['bentrok']++;

                    continue;
                }
                if ($nikLama === $nik) {
                    $hasil['tetap']++;

                    continue;
                }

                $pemilik = $nikTerpakai[$nik] ?? null;
                if ($pemilik !== null && $pemilik !== $user->id) {
                    $hasil['bentrok']++;

                    continue;
                }

                $user->nik = $nik;
                $user->save();

                $nikTerpakai[$nik] = $user->id;
                $hasil['diperbarui']++;
            }
        });

        return $hasil;
    }

    /**
     * @return list<User>
     */
    public function daftarPengguna(): array
    {
        return User::forSchool()
            ->whereIn('peran', self::PERAN_PEGAWAI)
            ->where('aktif', true)
            ->whereNull('archived_at')
            ->get();
    }

    private function matchUsersByName(string $nama, $users): array
    {
        $target = $this->nameTokens($nama);
        if ($target === []) {
            return [];
        }

        $exact = [];
        $variant = [];

        foreach ($users as $user) {
            $tokens = $this->nameTokens((string) ($user->nama_lengkap ?? ''));
            if ($tokens === []) {
                continue;
            }

            if ($tokens === $target) {
                $exact[] = $user;
            } elseif ($this->isTitleVariant($tokens, $target)) {
                $variant[] = $user;
            }
        }

        return $exact !== [] ? $exact : $variant;
    }

    /**
     * @return list<string>
     */
    private function nameTokens(string $nama): array
    {
        $nama = str_replace("\u{FEFF}", '', $nama);
        $nama = mb_strtolower(trim($nama));

        $potongan = preg_split('/,/', $nama);
        if (is_array($potongan) && isset($potongan[0])) {
            $nama = $potongan[0];
        }

        $nama = preg_replace('/[^a-z0-9]+/u', ' ', $nama) ?? '';
        $nama = preg_replace('/\s+/', ' ', $nama) ?? '';
        $nama = trim($nama);

        return $nama === '' ? [] : explode(' ', $nama);
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return bool
     */
    private function isTitleVariant(array $a, array $b): bool
    {
        $pendek = count($a) <= count($b) ? $a : $b;
        $panjang = count($a) <= count($b) ? $b : $a;

        if ($pendek === [] || count($panjang) <= count($pendek)) {
            return false;
        }

        if (array_slice($panjang, 0, count($pendek)) !== $pendek) {
            return false;
        }

        foreach (array_slice($panjang, count($pendek)) as $token) {
            if (mb_strlen($token) > 2 && ! in_array($token, self::TOKEN_GELAR, true)) {
                return false;
            }
        }

        return true;
    }

    public function normalizeNikValue($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9A-Za-z]+/', '', $value) ?? '';

        return $value === '' ? null : $value;
    }
}
