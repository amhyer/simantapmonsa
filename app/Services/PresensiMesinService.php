<?php

namespace App\Services;

use App\Models\PresensiPegawai;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan hasil scan mesin fingerprint ke tabel presensi_pegawai.
 *
 * Dipakai bersama oleh impor file (Excel/CSV) dan tarik langsung dari
 * database Attendance Management agar aturan pengamanannya selalu sama:
 * - scan masuk paling awal dan pulang paling akhir per pegawai per hari;
 * - koreksi manual admin serta status izin/sakit/cuti tidak ditimpa;
 * - NIK yang tidak dikenal dilewati.
 */
class PresensiMesinService
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    private const STATUS_DILINDUNGI = ['izin', 'sakit', 'cuti'];

    private const METODE_DILINDUNGI = ['admin', 'manual'];

    /**
     * @param  list<array{nik: string, waktu: Carbon, jenis: string}>  $scans
     * @return array{scan: int, pegawai: int, dibuat: int, diperbarui: int, tetap: int, dilindungi: int, nik_tidak_dikenal: int, contoh_nik_tidak_dikenal: list<string>}
     */
    public function importScans(array $scans, ?int $userId = null): array
    {
        $groups = $this->kelompokkan($scans);

        $usersByNik = [];
        foreach (User::forSchool()->whereIn('peran', self::PERAN_PEGAWAI)->where('aktif', true)->whereNull('archived_at')->get() as $user) {
            $nik = $this->normalizeNik($user->nik);
            if ($nik !== null) {
                // Jangan memilih salah satu akun jika PIN ambigu dalam sekolah.
                if (array_key_exists($nik, $usersByNik)) {
                    $usersByNik[$nik] = null;
                } else {
                    $usersByNik[$nik] = $user;
                }
            }
        }

        $hasil = [
            'scan' => count($scans),
            'pegawai' => 0,
            'dibuat' => 0,
            'diperbarui' => 0,
            'tetap' => 0,
            'dilindungi' => 0,
            'nik_tidak_dikenal' => 0,
            'contoh_nik_tidak_dikenal' => [],
        ];

        DB::transaction(function () use ($groups, $usersByNik, $userId, &$hasil) {
            foreach ($groups as $group) {
                $user = $usersByNik[$group['nik']] ?? null;
                if ($user === null) {
                    $hasil['nik_tidak_dikenal']++;
                    if (count($hasil['contoh_nik_tidak_dikenal']) < 8) {
                        $hasil['contoh_nik_tidak_dikenal'][] = $group['nik'];
                    }

                    continue;
                }

                $hasil['pegawai']++;

                $record = PresensiPegawai::where('user_id', $user->id)
                    ->whereDate('tanggal', $group['tanggal']->toDateString())
                    ->first();

                // Scan pulang saja hanya dapat melengkapi catatan dengan jam masuk.
                if ($group['masuk'] === null && (!$record || !$record->waktu_masuk)) {
                    $hasil['dilindungi']++;
                    continue;
                }

                if ($record !== null) {
                    if (in_array($record->metode, self::METODE_DILINDUNGI, true)
                        || in_array($record->status, self::STATUS_DILINDUNGI, true)) {
                        $hasil['dilindungi']++;

                        continue;
                    }

                    $berubah = false;

                    if ($group['masuk'] !== null
                        && ($record->waktu_masuk === null || $group['masuk']->lt($record->waktu_masuk))) {
                        $record->waktu_masuk = $group['masuk'];
                        $record->metode_masuk = 'mesin';
                        $berubah = true;
                    }

                    if ($group['pulang'] !== null
                        && ($record->waktu_pulang === null || $group['pulang']->gt($record->waktu_pulang))) {
                        $record->waktu_pulang = $group['pulang'];
                        $record->metode_pulang = 'mesin';
                        $berubah = true;
                    }

                    if ($group['masuk'] !== null) {
                        $status = $this->statusMasuk(array_merge($group, ['masuk' => $record->waktu_masuk]));
                        if ($record->status !== $status) {
                            $record->status = $status;
                            $berubah = true;
                        }
                    }

                    if ($berubah) {
                        $record->metode = 'mesin';
                        $record->diperbarui_oleh = $userId;
                        $record->save();
                        $hasil['diperbarui']++;
                    } else {
                        $hasil['tetap']++;
                    }

                    continue;
                }

                PresensiPegawai::create([
                    'user_id' => $user->id,
                    'tanggal' => $group['tanggal']->toDateString(),
                    'status' => $this->statusMasuk($group),
                    'waktu_masuk' => $group['masuk'],
                    'waktu_pulang' => $group['pulang'],
                    'metode' => 'mesin',
                    'metode_masuk' => $group['masuk'] ? 'mesin' : null,
                    'metode_pulang' => $group['pulang'] ? 'mesin' : null,
                    'diperbarui_oleh' => $userId,
                ]);
                $hasil['dibuat']++;
            }
        });

        return $hasil;
    }

    /**
     * @param  list<array{nik: string, waktu: Carbon, jenis: string}>  $scans
     * @return array<string, array{nik: string, tanggal: Carbon, masuk: ?Carbon, pulang: ?Carbon}>
     */
    private function kelompokkan(array $scans): array
    {
        $groups = [];

        foreach ($scans as $scan) {
            $tanggal = $scan['waktu']->copy()->startOfDay();
            $key = $scan['nik'].'|'.$tanggal->toDateString();

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'nik' => $scan['nik'],
                    'tanggal' => $tanggal,
                    'masuk' => null,
                    'pulang' => null,
                ];
            }

            if ($scan['jenis'] === 'masuk') {
                if ($groups[$key]['masuk'] === null || $scan['waktu']->lt($groups[$key]['masuk'])) {
                    $groups[$key]['masuk'] = $scan['waktu'];
                }
            } elseif ($groups[$key]['pulang'] === null || $scan['waktu']->gt($groups[$key]['pulang'])) {
                $groups[$key]['pulang'] = $scan['waktu'];
            }
        }

        return $groups;
    }

    public function statusMasuk(array $group): string
    {
        if ($group['masuk'] === null) {
            return 'hadir';
        }

        $batas = Carbon::parse(
            $group['tanggal']->toDateString().' '.config('presensi.mesin_jam_masuk', '07:15'),
            config('app.timezone'),
        );

        return $group['masuk']->gt($batas) ? 'terlambat' : 'hadir';
    }

    public function normalizeNik($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9A-Za-z]+/', '', $value) ?? '';

        return $value === '' ? null : $value;
    }

    /**
     * Menentukan jenis scan dari CHECKTYPE pada database mesin.
     * Sebagian mesin mengisi "I"/"O", sebagian "0"/"1", sebagian kosong.
     */
    public function jenisScan(?string $tipe, Carbon $waktu): string
    {
        $tipe = mb_strtolower(trim((string) $tipe));

        if ($tipe !== '') {
            if (in_array($tipe, ['1', 'o', 'out', 'keluar', 'pulang'], true)
                || str_contains($tipe, 'keluar')
                || str_contains($tipe, 'pulang')
                || str_contains($tipe, 'out')) {
                return 'pulang';
            }
            if (in_array($tipe, ['0', 'i', 'in', 'masuk', 'datang'], true)
                || str_contains($tipe, 'masuk')
                || str_contains($tipe, 'datang')) {
                return 'masuk';
            }
        }

        return $waktu->hour >= 12 ? 'pulang' : 'masuk';
    }
}
