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
 * - status "pulang" sebelum scan masuk pertama dianggap scan ganda kedatangan;
 * - hari presensi dimulai pukul 01:00;
 * - koreksi manual admin serta status izin/sakit/cuti tidak ditimpa;
 * - NIK yang tidak dikenal dilewati.
 */
class PresensiMesinService
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    private const STATUS_DILINDUNGI = ['izin', 'sakit', 'cuti'];

    private const METODE_DILINDUNGI = ['admin', 'manual'];

    /**
     * @param  list<array{nik: string, waktu: Carbon, jenis: string, nama?: string}>  $scans
     * @return array{scan: int, pegawai: int, dibuat: int, diperbarui: int, tetap: int, dilindungi: int, nik_tidak_dikenal: int, contoh_nik_tidak_dikenal: list<string>, perubahan: list<array<string, mixed>>}
     */
    public function importScans(array $scans, ?int $userId = null): array
    {
        $namaPerNik = [];
        foreach ($scans as $scan) {
            $normNik = $this->normalizeNik($scan['nik'] ?? null);
            $namaScan = trim((string) ($scan['nama'] ?? ''));
            if ($normNik !== null && $namaScan !== '' && ! isset($namaPerNik[$normNik])) {
                $namaPerNik[$normNik] = $namaScan;
            }
        }

        // Bila file log mesin juga memuat kolom Nama dan opsi Buat Akun Otomatis aktif,
        // cocokkan/buatkan akun pegawai terlebih dahulu sebelum memproses scan kehadiran.
        $karyawanService = app(\App\Services\MesinPresensi\PresensiMesinKaryawanService::class);
        if ($namaPerNik !== [] && $karyawanService->pengaturanAutoAkun()['aktif']) {
            $pasanganAuto = [];
            foreach ($namaPerNik as $nikItem => $namaItem) {
                $pasanganAuto[] = ['nik' => (string) $nikItem, 'nama' => $namaItem];
            }
            $karyawanService->simpanNikKaryawan($pasanganAuto);
        }

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
            'perubahan' => [],
        ];

        $unmappedDariLog = [];

        DB::transaction(function () use ($groups, $usersByNik, $userId, $namaPerNik, &$hasil, &$unmappedDariLog) {
            foreach ($groups as $group) {
                $user = $usersByNik[$group['nik']] ?? null;
                if ($user === null) {
                    $hasil['nik_tidak_dikenal']++;
                    if (count($hasil['contoh_nik_tidak_dikenal']) < 8) {
                        $hasil['contoh_nik_tidak_dikenal'][] = $group['nik'];
                    }

                    $unmappedDariLog[$group['nik']] = [
                        'nik' => $group['nik'],
                        'nama' => $namaPerNik[$group['nik']] ?? '',
                        'alasan' => 'nik_tidak_dikenal',
                        'sumber' => 'log_scan',
                        'diperbarui_at' => now()->toDateTimeString(),
                    ];

                    continue;
                }

                $hasil['pegawai']++;

                $record = PresensiPegawai::where('user_id', $user->id)
                    ->whereDate('tanggal', $group['tanggal']->toDateString())
                    ->first();

                // Scan pulang saja hanya dapat melengkapi catatan dengan jam masuk.
                if ($group['masuk'] === null && (! $record || ! $record->waktu_masuk)) {
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
                    $statusLama = $record->status;
                    $status = $statusLama;

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

                    // Jangan biarkan data lama menyimpan pulang sebelum masuk.
                    if ($record->waktu_masuk !== null
                        && $record->waktu_pulang !== null
                        && $record->waktu_pulang->lte($record->waktu_masuk)) {
                        $record->waktu_pulang = null;
                        $record->metode_pulang = null;
                        $berubah = true;
                    }

                    if ($group['masuk'] !== null) {
                        $status = $this->statusMasuk(array_merge($group, ['masuk' => $record->waktu_masuk]), $user->jenjang);
                        if ($statusLama !== $status) {
                            $record->status = $status;
                            $berubah = true;
                        }
                    }

                    if ($berubah) {
                        $record->metode = 'mesin';
                        $record->diperbarui_oleh = $userId;
                        $record->save();
                        $hasil['diperbarui']++;

                        if ($status === 'terlambat') {
                            $hasil['perubahan'][] = [
                                'user_id' => $user->id,
                                'nik' => $group['nik'],
                                'tanggal' => $group['tanggal']->toDateString(),
                                'status' => $status,
                                'waktu_masuk' => $group['masuk']?->toDateTimeString(),
                            ];
                        }
                    } else {
                        $hasil['tetap']++;
                    }

                    continue;
                }

                $statusBaru = $this->statusMasuk($group, $user->jenjang);

                PresensiPegawai::create([
                    'user_id' => $user->id,
                    'tanggal' => $group['tanggal']->toDateString(),
                    'status' => $statusBaru,
                    'waktu_masuk' => $group['masuk'],
                    'waktu_pulang' => $group['pulang'],
                    'metode' => 'mesin',
                    'metode_masuk' => $group['masuk'] ? 'mesin' : null,
                    'metode_pulang' => $group['pulang'] ? 'mesin' : null,
                    'diperbarui_oleh' => $userId,
                ]);

                if ($statusBaru === 'terlambat') {
                    $hasil['perubahan'][] = [
                        'user_id' => $user->id,
                        'nik' => $group['nik'],
                        'tanggal' => $group['tanggal']->toDateString(),
                        'status' => $statusBaru,
                        'waktu_masuk' => $group['masuk']?->toDateTimeString(),
                    ];
                }

                $hasil['dibuat']++;
            }
        });

        if ($unmappedDariLog !== []) {
            $karyawanService->catatBelumTerpetakan(array_values($unmappedDariLog));
        }

        return $hasil;
    }

    /**
     * Mengelompokkan scan per pegawai per hari, lalu menentukan jam masuk dan
     * pulang. Hari presensi dimulai pukul 01:00 (lihat tanggalPresensi()).
     *
     * @param  list<array{nik: string, waktu: Carbon, jenis: string}>  $scans
     * @return array<string, array{nik: string, tanggal: Carbon, masuk: ?Carbon, pulang: ?Carbon}>
     */
    private function kelompokkan(array $scans): array
    {
        $mentah = [];

        foreach ($scans as $scan) {
            $tanggal = $this->tanggalPresensi($scan['waktu']);
            $nik = $this->normalizeNik($scan['nik']) ?? trim((string) $scan['nik']);
            $key = $nik.'|'.$tanggal->toDateString();

            if (! isset($mentah[$key])) {
                $mentah[$key] = [
                    'nik' => $nik,
                    'tanggal' => $tanggal,
                    'scans' => [],
                ];
            }

            $mentah[$key]['scans'][] = $scan;
        }

        $groups = [];
        foreach ($mentah as $key => $data) {
            $groups[$key] = $this->ringkasHarian($data['nik'], $data['tanggal'], $data['scans']);
        }

        return $groups;
    }

    /**
     * Status 0/1 dari mesin dipakai apa adanya. Bila mesin menandai "pulang"
     * sebelum scan "masuk" pertama pada hari yang sama, scan itu adalah scan
     * ganda kedatangan (mis. dua kali tap dalam hitungan detik) sehingga
     * dihitung sebagai "masuk", bukan pulang.
     *
     * @param  list<array{nik: string, waktu: Carbon, jenis: string}>  $scans
     * @return array{nik: string, tanggal: Carbon, masuk: ?Carbon, pulang: ?Carbon}
     */
    private function ringkasHarian(string $nik, Carbon $tanggal, array $scans): array
    {
        usort($scans, fn (array $a, array $b): int => $a['waktu'] <=> $b['waktu']);

        $masukPertama = null;
        foreach ($scans as $scan) {
            if ($scan['jenis'] === 'masuk') {
                $masukPertama = $scan['waktu'];

                break;
            }
        }

        $masuk = null;
        $pulang = null;

        foreach ($scans as $scan) {
            $jenis = $scan['jenis'];

            if ($jenis === 'pulang' && $masukPertama !== null && $scan['waktu']->lt($masukPertama)) {
                $jenis = 'masuk';
            }

            if ($jenis === 'masuk') {
                if ($masuk === null || $scan['waktu']->lt($masuk)) {
                    $masuk = $scan['waktu'];
                }
            } elseif ($pulang === null || $scan['waktu']->gt($pulang)) {
                $pulang = $scan['waktu'];
            }
        }

        if ($masuk !== null && $pulang !== null && $pulang->lte($masuk)) {
            $pulang = null;
        }

        return ['nik' => $nik, 'tanggal' => $tanggal, 'masuk' => $masuk, 'pulang' => $pulang];
    }

    /**
     * Hari presensi mesin dimulai pukul 01:00, sehingga scan 00:00-00:59
     * dihitung sebagai bagian dari hari sebelumnya.
     */
    private function tanggalPresensi(Carbon $waktu): Carbon
    {
        return $waktu->copy()->subHour()->startOfDay();
    }

    public function statusMasuk(array $group, ?string $jenjang = null): string
    {
        if ($group['masuk'] === null) {
            return 'hadir';
        }

        $batas = Carbon::parse(
            $group['tanggal']->toDateString().' '.$this->jamMasuk($jenjang),
            config('app.timezone'),
        );

        return $group['masuk']->gt($batas) ? 'terlambat' : 'hadir';
    }

    /**
     * Batas jam masuk presensi mesin untuk suatu jenjang.
     * Default ke `presensi.mesin_jam_masuk` bila jenjang belum dikenal.
     */
    public function jamMasuk(?string $jenjang = null): string
    {
        $nilaiJenjang = strtoupper(trim((string) ($jenjang ?? '')));
        $jamPerJenjang = (array) config('presensi.mesin_jam_masuk_jenjang', []);

        if ($nilaiJenjang !== '' && array_key_exists($nilaiJenjang, $jamPerJenjang)) {
            return (string) $jamPerJenjang[$nilaiJenjang];
        }

        return (string) config('presensi.mesin_jam_masuk', '07:15');
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
