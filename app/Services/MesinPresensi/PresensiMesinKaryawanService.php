<?php

namespace App\Services\MesinPresensi;

use App\Models\PengaturanGuru;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Support\InitialPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
 * Nama/PIN yang belum cocok disimpan ke daftar pemetaan sekolah atau
 * otomatis dibuatkan akun pegawai baru bila opsi auto-akun diaktifkan.
 */
class PresensiMesinKaryawanService
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    private const PERAN_AUTO_VALID = ['tendik', 'guru'];

    private const TOKEN_GELAR = [
        's', 'm', 'a', 'pd', 'si', 'sos', 'kom', 'ag', 'hi', 'ip', 'pt', 'ab',
        'iq', 'fil', 'psi', 'keb', 'kes', 'farm', 'pod', 'kp', 'kep', 'th',
        'mm', 'se', 'sh', 'ma', 'mba', 'phd', 'apt', 'drs', 'dra', 'prof',
        'st', 'mt', 'ssi', 'sst', 'spt', 'skm', 'sip', 'shi', 'sag',
    ];

    /**
     * @param  list<array{nik: string, nama: string}>  $pasangan
     * @return array{baris: int, diperbarui: int, akun_dibuat: int, tetap: int, tidak_cocok: int, ambigu: int, bentrok: int, contoh_tidak_cocok: list<string>}
     */
    public function simpanNikKaryawan(array $pasangan, ?bool $autoCreateOverride = null, ?string $peranOverride = null): array
    {
        $opsiAuto = $this->pengaturanAutoAkun();
        $autoCreate = $autoCreateOverride ?? $opsiAuto['aktif'];
        $peranDefault = in_array($peranOverride, self::PERAN_AUTO_VALID, true)
            ? $peranOverride
            : $opsiAuto['peran'];

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
            'akun_dibuat' => 0,
            'tetap' => 0,
            'tidak_cocok' => 0,
            'ambigu' => 0,
            'bentrok' => 0,
            'contoh_tidak_cocok' => [],
        ];

        $unmappedBaru = [];
        $nikSudahTerpetakan = [];

        DB::transaction(function () use (
            $pasangan,
            $users,
            $autoCreate,
            $peranDefault,
            &$nikTerpakai,
            &$hasil,
            &$unmappedBaru,
            &$nikSudahTerpetakan
        ) {
            foreach ($pasangan as $item) {
                $nik = $this->normalizeNikValue($item['nik'] ?? null);
                $nama = trim((string) ($item['nama'] ?? ''));

                if ($nik === null || $nama === '') {
                    continue;
                }

                $hasil['baris']++;

                $kandidat = $this->matchUsersByName($nama, $users);
                if ($kandidat === []) {
                    if ($autoCreate && ! isset($nikTerpakai[$nik])) {
                        $dibuat = $this->buatAkunPegawaiInternal($nik, $nama, $peranDefault);
                        $users->push($dibuat['user']);
                        $nikTerpakai[$nik] = $dibuat['user']->id;
                        $nikSudahTerpetakan[] = $nik;
                        $hasil['akun_dibuat']++;

                        continue;
                    }

                    $hasil['tidak_cocok']++;
                    if (count($hasil['contoh_tidak_cocok']) < 8) {
                        $hasil['contoh_tidak_cocok'][] = $nama;
                    }

                    $unmappedBaru[$nik] = [
                        'nik' => $nik,
                        'nama' => $nama,
                        'alasan' => isset($nikTerpakai[$nik]) ? 'bentrok' : 'tidak_cocok',
                        'sumber' => 'karyawan',
                        'diperbarui_at' => now()->toDateTimeString(),
                    ];

                    continue;
                }
                if (count($kandidat) > 1) {
                    $hasil['ambigu']++;
                    $unmappedBaru[$nik] = [
                        'nik' => $nik,
                        'nama' => $nama,
                        'alasan' => 'ambigu',
                        'sumber' => 'karyawan',
                        'diperbarui_at' => now()->toDateTimeString(),
                    ];

                    continue;
                }

                /** @var User $user */
                $user = $kandidat[0];
                $nikLama = $this->normalizeNikValue($user->nik);

                if ($nikLama !== null && $nikLama !== $nik) {
                    $hasil['bentrok']++;
                    $unmappedBaru[$nik] = [
                        'nik' => $nik,
                        'nama' => $nama,
                        'alasan' => 'bentrok',
                        'sumber' => 'karyawan',
                        'diperbarui_at' => now()->toDateTimeString(),
                    ];

                    continue;
                }
                if ($nikLama === $nik) {
                    $hasil['tetap']++;
                    $nikSudahTerpetakan[] = $nik;

                    continue;
                }

                $pemilik = $nikTerpakai[$nik] ?? null;
                if ($pemilik !== null && $pemilik !== $user->id) {
                    $hasil['bentrok']++;
                    $unmappedBaru[$nik] = [
                        'nik' => $nik,
                        'nama' => $nama,
                        'alasan' => 'bentrok',
                        'sumber' => 'karyawan',
                        'diperbarui_at' => now()->toDateTimeString(),
                    ];

                    continue;
                }

                $user->nik = $nik;
                $user->save();

                $nikTerpakai[$nik] = $user->id;
                $nikSudahTerpetakan[] = $nik;
                $hasil['diperbarui']++;
            }
        });

        $this->catatBelumTerpetakan(array_values($unmappedBaru), $nikSudahTerpetakan);

        return $hasil;
    }

    /**
     * @return array{aktif: bool, peran: string}
     */
    public function pengaturanAutoAkun(): array
    {
        $sekolah = SekolahSettings::query()->first();
        $pengaturan = is_array($sekolah?->pengaturan ?? null) ? $sekolah->pengaturan : [];
        $peran = (string) ($pengaturan['presensi_mesin_default_peran'] ?? 'tendik');

        return [
            'aktif' => (bool) ($pengaturan['presensi_mesin_auto_akun'] ?? false),
            'peran' => in_array($peran, self::PERAN_AUTO_VALID, true) ? $peran : 'tendik',
        ];
    }

    public function simpanPengaturanAutoAkun(bool $aktif, string $peran): void
    {
        $peranValid = in_array($peran, self::PERAN_AUTO_VALID, true) ? $peran : 'tendik';
        $sekolah = SekolahSettings::query()->first();
        $pengaturan = is_array($sekolah?->pengaturan ?? null) ? $sekolah->pengaturan : [];
        $pengaturan['presensi_mesin_auto_akun'] = $aktif;
        $pengaturan['presensi_mesin_default_peran'] = $peranValid;

        if ($sekolah) {
            $sekolah->update(['pengaturan' => $pengaturan]);
        } else {
            SekolahSettings::create([
                'nama_sekolah' => (string) config('app.name', 'SIMANTAP'),
                'pengaturan' => $pengaturan,
            ]);
        }
    }

    /**
     * Ambil daftar PIN/Nama dari mesin yang belum terhubung ke akun pegawai.
     *
     * @return list<array{nik: string, nama: string, alasan: string, sumber: string, diperbarui_at: string}>
     */
    public function daftarBelumTerpetakan(): array
    {
        $sekolah = SekolahSettings::query()->first();
        $pengaturan = is_array($sekolah?->pengaturan ?? null) ? $sekolah->pengaturan : [];
        $tersimpan = is_array($pengaturan['presensi_mesin_unmapped'] ?? null)
            ? $pengaturan['presensi_mesin_unmapped']
            : [];

        if ($tersimpan === []) {
            return [];
        }

        $nikTerpakai = [];
        foreach (User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)->whereNotNull('nik')->pluck('nik') as $nikUser) {
            $norm = $this->normalizeNikValue($nikUser);
            if ($norm !== null) {
                $nikTerpakai[$norm] = true;
            }
        }

        $daftar = [];
        $berubah = false;

        foreach ($tersimpan as $key => $item) {
            if (! is_array($item)) {
                $berubah = true;

                continue;
            }

            $nik = $this->normalizeNikValue($item['nik'] ?? $key);
            if ($nik === null) {
                $berubah = true;

                continue;
            }

            if (isset($nikTerpakai[$nik]) && ($item['alasan'] ?? '') !== 'bentrok') {
                unset($tersimpan[$key]);
                $berubah = true;

                continue;
            }

            $daftar[] = [
                'nik' => $nik,
                'nama' => trim((string) ($item['nama'] ?? '')),
                'alasan' => (string) ($item['alasan'] ?? 'tidak_cocok'),
                'sumber' => (string) ($item['sumber'] ?? 'mesin'),
                'diperbarui_at' => (string) ($item['diperbarui_at'] ?? now()->toDateTimeString()),
            ];
        }

        if ($berubah && $sekolah) {
            $pengaturan['presensi_mesin_unmapped'] = $tersimpan;
            $sekolah->update(['pengaturan' => $pengaturan]);
        }

        usort($daftar, fn (array $a, array $b): int => [$a['nama'] === '' ? 1 : 0, mb_strtolower($a['nama']), $a['nik']]
            <=> [$b['nama'] === '' ? 1 : 0, mb_strtolower($b['nama']), $b['nik']]);

        return $daftar;
    }

    /**
     * @param  list<array{nik: string, nama?: string, alasan?: string, sumber?: string, diperbarui_at?: string}>  $items
     * @param  list<string>  $nikSudahTerpetakan
     */
    public function catatBelumTerpetakan(array $items, array $nikSudahTerpetakan = []): void
    {
        if ($items === [] && $nikSudahTerpetakan === []) {
            return;
        }

        $sekolah = SekolahSettings::query()->first();
        if (! $sekolah && $items === []) {
            return;
        }
        $pengaturan = is_array($sekolah?->pengaturan ?? null) ? $sekolah->pengaturan : [];
        $map = is_array($pengaturan['presensi_mesin_unmapped'] ?? null)
            ? $pengaturan['presensi_mesin_unmapped']
            : [];

        foreach ($nikSudahTerpetakan as $nikSelesai) {
            $norm = $this->normalizeNikValue($nikSelesai);
            if ($norm !== null) {
                unset($map[$norm]);
            }
        }

        foreach ($items as $item) {
            $nik = $this->normalizeNikValue($item['nik'] ?? null);
            if ($nik === null) {
                continue;
            }

            $namaBaru = trim((string) ($item['nama'] ?? ''));
            $namaLama = trim((string) ($map[$nik]['nama'] ?? ''));

            $map[$nik] = [
                'nik' => $nik,
                'nama' => $namaBaru !== '' ? $namaBaru : $namaLama,
                'alasan' => (string) ($item['alasan'] ?? ($map[$nik]['alasan'] ?? 'tidak_cocok')),
                'sumber' => (string) ($item['sumber'] ?? ($map[$nik]['sumber'] ?? 'mesin')),
                'diperbarui_at' => (string) ($item['diperbarui_at'] ?? now()->toDateTimeString()),
            ];
        }

        // Batasi maksimal 500 entri agar kolom JSON tetap ramping
        if (count($map) > 500) {
            $map = array_slice($map, -500, 500, true);
        }

        $pengaturan['presensi_mesin_unmapped'] = $map;

        if ($sekolah) {
            $sekolah->update(['pengaturan' => $pengaturan]);
        } else {
            SekolahSettings::create([
                'nama_sekolah' => (string) config('app.name', 'SIMANTAP'),
                'pengaturan' => $pengaturan,
            ]);
        }
    }

    public function hapusBelumTerpetakan(string $nik): bool
    {
        $norm = $this->normalizeNikValue($nik);
        if ($norm === null) {
            return false;
        }

        $sekolah = SekolahSettings::query()->first();
        if (! $sekolah) {
            return false;
        }

        $pengaturan = is_array($sekolah->pengaturan) ? $sekolah->pengaturan : [];
        $map = is_array($pengaturan['presensi_mesin_unmapped'] ?? null)
            ? $pengaturan['presensi_mesin_unmapped']
            : [];

        if (! array_key_exists($norm, $map)) {
            return false;
        }

        unset($map[$norm]);
        $pengaturan['presensi_mesin_unmapped'] = $map;
        $sekolah->update(['pengaturan' => $pengaturan]);

        return true;
    }

    /**
     * Pasangkan PIN mesin ke akun pegawai yang dipilih Admin dari dropdown.
     */
    public function pasangkanPinKePegawai(string $nik, int $userId): User
    {
        $norm = $this->normalizeNikValue($nik);
        if ($norm === null) {
            throw new InvalidArgumentException('Nomor PIN/NIK mesin tidak valid.');
        }

        /** @var User|null $target */
        $target = User::forSchool()
            ->whereIn('peran', self::PERAN_PEGAWAI)
            ->whereKey($userId)
            ->first();

        if (! $target) {
            throw new InvalidArgumentException('Akun pegawai tidak ditemukan di sekolah ini.');
        }

        $pemilikLain = User::forSchool()
            ->whereIn('peran', self::PERAN_PEGAWAI)
            ->where('id', '!=', $target->id)
            ->get()
            ->first(fn (User $u) => $this->normalizeNikValue($u->nik) === $norm);

        if ($pemilikLain) {
            throw new InvalidArgumentException("PIN {$norm} sudah dipakai oleh {$pemilikLain->nama_lengkap}.");
        }

        $target->nik = $norm;
        $target->save();

        $this->hapusBelumTerpetakan($norm);

        return $target;
    }

    /**
     * Buat 1 akun pegawai baru dari PIN & nama mesin absensi.
     *
     * @return array{user: User, username: string, password_awal: string}
     */
    public function buatAkunPegawaiMesin(string $nik, string $nama, string $peran = 'tendik'): array
    {
        $norm = $this->normalizeNikValue($nik);
        if ($norm === null) {
            throw new InvalidArgumentException('Nomor PIN/NIK mesin tidak valid.');
        }

        $peranValid = in_array($peran, self::PERAN_AUTO_VALID, true) ? $peran : 'tendik';

        $pemilik = User::forSchool()
            ->whereIn('peran', self::PERAN_PEGAWAI)
            ->get()
            ->first(fn (User $u) => $this->normalizeNikValue($u->nik) === $norm);

        if ($pemilik) {
            throw new InvalidArgumentException("PIN {$norm} sudah terhubung ke akun {$pemilik->nama_lengkap}.");
        }

        $hasil = DB::transaction(fn () => $this->buatAkunPegawaiInternal($norm, $nama, $peranValid));

        $this->hapusBelumTerpetakan($norm);

        return $hasil;
    }

    /**
     * Buat akun baru secara massal untuk seluruh daftar PIN mesin yang belum terpetakan.
     *
     * @return array{dibuat: int, dilewati: int, akun: list<array{nama: string, nik: string, username: string, peran: string}>}
     */
    public function buatSemuaAkunBelumTerpetakan(?string $peranOverride = null): array
    {
        $peranValid = in_array($peranOverride, self::PERAN_AUTO_VALID, true)
            ? $peranOverride
            : $this->pengaturanAutoAkun()['peran'];

        $daftar = $this->daftarBelumTerpetakan();
        $nikTerpakai = [];
        foreach (User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)->whereNotNull('nik')->pluck('nik') as $nikUser) {
            $norm = $this->normalizeNikValue($nikUser);
            if ($norm !== null) {
                $nikTerpakai[$norm] = true;
            }
        }

        $dibuat = 0;
        $dilewati = 0;
        $akunBaru = [];
        $nikSelesai = [];

        DB::transaction(function () use ($daftar, $peranValid, &$nikTerpakai, &$dibuat, &$dilewati, &$akunBaru, &$nikSelesai) {
            foreach ($daftar as $item) {
                $nik = $this->normalizeNikValue($item['nik'] ?? null);
                if ($nik === null || isset($nikTerpakai[$nik])) {
                    $dilewati++;

                    continue;
                }

                $nama = trim((string) ($item['nama'] ?? ''));
                if ($nama === '') {
                    $nama = 'Pegawai Mesin '.$nik;
                }

                $res = $this->buatAkunPegawaiInternal($nik, $nama, $peranValid);
                $nikTerpakai[$nik] = true;
                $nikSelesai[] = $nik;
                $dibuat++;

                $akunBaru[] = [
                    'nama' => $res['user']->nama_lengkap,
                    'nik' => $nik,
                    'username' => $res['username'],
                    'peran' => $peranValid,
                ];
            }
        });

        if ($nikSelesai !== []) {
            $this->catatBelumTerpetakan([], $nikSelesai);
        }

        return [
            'dibuat' => $dibuat,
            'dilewati' => $dilewati,
            'akun' => $akunBaru,
        ];
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
            ->orderBy('nama_lengkap')
            ->get()
            ->all();
    }

    /**
     * @return array{user: User, username: string, password_awal: string}
     */
    private function buatAkunPegawaiInternal(string $nik, string $nama, string $peran): array
    {
        $namaBersih = trim($nama) !== '' ? trim($nama) : ('Pegawai Mesin '.$nik);
        $slugNama = Str::slug($namaBersih, '');
        $baseUsername = $slugNama !== '' ? substr($slugNama, 0, 24) : ('pegawai'.$nik);

        $username = $this->resolveUniqueUsername($baseUsername);
        $email = $this->resolveUniqueEmail($username);
        $plainPassword = InitialPassword::generate();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => TenantContext::id(),
            'nama_lengkap' => $namaBersih,
            'nama_pengguna' => $username,
            'email' => $email,
            'kata_sandi' => Hash::make($plainPassword),
            'peran' => $peran,
            'nik' => $nik,
            'aktif' => true,
            'force_password_change' => true,
            'sandi_awal_diterbitkan_at' => now(),
        ]);

        if ($peran === 'guru') {
            $semesterAktif = Semester::orderByDesc('semester_id')->first()
                ?? Semester::orderByDesc('id')->first();
            $namaSemester = $semesterAktif?->nama_semester ? ucfirst($semesterAktif->nama_semester) : 'Ganjil';
            $tahunAjaran = $semesterAktif?->tahun_ajaran ?? (date('Y').'/'.(date('Y') + 1));
            $kkmDefault = function_exists('getKKM') ? getKKM() : 75;

            PengaturanGuru::firstOrCreate(
                ['guru_id' => $user->id],
                [
                    'nama_guru' => $user->nama_lengkap,
                    'kkm' => $kkmDefault,
                    'semester' => $namaSemester,
                    'tahun_pelajaran' => $tahunAjaran,
                    'pembaruan' => now(),
                ]
            );
        }

        return [
            'user' => $user,
            'username' => $username,
            'password_awal' => $plainPassword,
        ];
    }

    private function resolveUniqueUsername(string $base): string
    {
        $candidate = strtolower($base);
        $counter = 1;

        while (User::withoutGlobalScopes()->withTrashed()->whereRaw('LOWER(nama_pengguna) = ?', [$candidate])->exists()) {
            $candidate = strtolower($base).'.'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function resolveUniqueEmail(string $username): string
    {
        $candidate = strtolower($username).'@simantap.sch.id';
        $counter = 1;

        while (User::withoutGlobalScopes()->withTrashed()->whereRaw('LOWER(email) = ?', [$candidate])->exists()) {
            $candidate = strtolower($username).'.'.$counter.'@simantap.sch.id';
            $counter++;
        }

        return $candidate;
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
