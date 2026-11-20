<?php

namespace App\Services\MesinPresensi;

use App\Modules\Tenant\Support\TenantMode;
use App\Services\AktivitasService;
use App\Services\PresensiMesinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PresensiMesinPullService
{
    public const KUNCI_SINKRON_TERAKHIR = 'presensi.mesin.terakhir_tarik';

    public const MAKS_HARI_TARIK = 31;

    public function tarikLangsungDinonaktifkan(): bool
    {
        return TenantMode::isMultiSchool()
            && ! (bool) config('presensi.mesin_izinkan_tenant', false);
    }

    public function terakhirTarik(): ?string
    {
        if ($this->tarikLangsungDinonaktifkan()) {
            return null;
        }

        $val = Cache::get(self::KUNCI_SINKRON_TERAKHIR);

        return $val !== null ? (string) $val : null;
    }

    public function tandaiTerakhirTarik(): void
    {
        Cache::forever(self::KUNCI_SINKRON_TERAKHIR, now()->toDateTimeString());
    }

    /**
     * Bagi rentang tanggal untuk penarikan penuh: TCP satu kali, Access per 31 hari.
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    public function susunRentangBaca(Carbon $dari, Carbon $sampai, string $engine): array
    {
        if ($engine === 'tcp') {
            return [[$dari->copy()->startOfDay(), $sampai->copy()->startOfDay()]];
        }

        $rentangBaca = [];
        $mulai = $dari->copy()->startOfDay();
        $batasAkhir = $sampai->copy()->startOfDay();

        while ($mulai->lte($batasAkhir)) {
            $batasChunk = $mulai->copy()->addDays(self::MAKS_HARI_TARIK - 1);
            $sampaiChunk = $batasChunk->lt($batasAkhir) ? $batasChunk : $batasAkhir;
            $rentangBaca[] = [$mulai->copy(), $sampaiChunk->copy()];

            if ($sampaiChunk->eq($batasAkhir)) {
                break;
            }

            $mulai = $sampaiChunk->copy()->addDay();
        }

        return $rentangBaca;
    }

    /**
     * Tarik seluruh data dari reader dalam potongan rentang lalu impor ke PresensiMesinService.
     *
     * @return array<string, mixed>
     */
    public function tarikSemuaRentang(
        AttendanceDatabaseReader $reader,
        PresensiMesinService $presensi,
        Carbon $dari,
        Carbon $sampai,
        ?int $userId,
    ): array {
        $hasil = [
            'scans' => 0,
            'dibuat' => 0,
            'diperbarui' => 0,
            'tetap' => 0,
            'dilindungi' => 0,
            'nik_tidak_dikenal' => 0,
            'contoh_nik_tidak_dikenal' => [],
            'baris_diabaikan' => 0,
        ];

        $rentangBaca = $this->susunRentangBaca($dari, $sampai, $reader->engine());

        foreach ($rentangBaca as [$mulaiChunk, $sampaiChunk]) {
            $scans = $reader->baca($mulaiChunk, $sampaiChunk);
            $hasil['scans'] += count($scans);

            if ($scans !== []) {
                $r = $presensi->importScans($scans, $userId);
                $this->catatPerubahanKehadiran($r);
                $hasil['dibuat'] += $r['dibuat'];
                $hasil['diperbarui'] += $r['diperbarui'];
                $hasil['tetap'] += $r['tetap'];
                $hasil['dilindungi'] += $r['dilindungi'];
                $hasil['nik_tidak_dikenal'] += $r['nik_tidak_dikenal'];
                $hasil['contoh_nik_tidak_dikenal'] = array_values(array_unique(array_merge(
                    (array) $hasil['contoh_nik_tidak_dikenal'],
                    (array) $r['contoh_nik_tidak_dikenal'],
                )));
            }
        }

        return $hasil;
    }

    /**
     * Mencatat perubahan kehadiran (mis. terdeteksi terlambat) ke log aktivitas.
     *
     * @param  array<string, mixed>  $hasil
     */
    public function catatPerubahanKehadiran(array $hasil): void
    {
        if (empty($hasil['perubahan'])) {
            return;
        }

        AktivitasService::log(
            'kehadiran',
            'Perubahan kehadiran dari mesin fingerprint',
            'Terdeteksi perubahan kehadiran (mis. terlambat): '.count($hasil['perubahan']).' kejadian.',
            'presensi_pegawai',
            null,
            null,
            $hasil['perubahan'],
        );
    }

    /**
     * @param  array{diperbarui: int, tetap: int, tidak_cocok: int, ambigu: int, bentrok: int, contoh_tidak_cocok?: list<string>}  $hasil
     */
    public function pesanKaryawan(array $hasil): string
    {
        $pesan = "Data karyawan: {$hasil['diperbarui']} NIK diperbarui, {$hasil['tetap']} sudah sesuai, "
            ."{$hasil['tidak_cocok']} nama tidak cocok, {$hasil['ambigu']} ambigu, {$hasil['bentrok']} bentrok.";

        $contoh = (array) ($hasil['contoh_tidak_cocok'] ?? []);
        if ($contoh !== []) {
            $pesan .= ' Contoh nama tidak cocok: '.implode(', ', $contoh).'.';
        }

        return $pesan;
    }

    /**
     * @param  array<string, mixed>  $hasil
     */
    public function pesanHasil(array $hasil, string $awalan): string
    {
        $pesan = "{$awalan}: {$hasil['dibuat']} data dibuat, {$hasil['diperbarui']} diperbarui, "
            ."{$hasil['tetap']} tidak berubah, {$hasil['dilindungi']} dilindungi (koreksi manual/izin/sakit), "
            ."{$hasil['nik_tidak_dikenal']} NIK tidak dikenal";

        if (($hasil['baris_diabaikan'] ?? 0) > 0) {
            $pesan .= ", {$hasil['baris_diabaikan']} baris diabaikan";
        }

        $pesan .= '.';

        $contoh = (array) ($hasil['contoh_nik_tidak_dikenal'] ?? []);
        if ($contoh !== []) {
            $pesan .= ' Contoh NIK tidak dikenal: '.implode(', ', $contoh).'.';
        }

        return $pesan;
    }
}
