<?php

namespace App\Http\Controllers\Api;

use App\Services\AktivitasService;
use App\Services\MesinPresensi\PresensiMesinKaryawanService;
use App\Services\MesinPresensi\PresensiMesinService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Endpoint sinkron presensi dari bridge di sekolah (PC Windows dengan
 * ATT2000.MDB) ke website hosting.
 *
 * Semua scan diproses lewat `PresensiMesinService::importScans` sehingga
 * aturan keamanan (izin/sakit/cuti, koreksi admin) tetap dipakai.
 */
class PresensiMesinSyncController extends Controller
{
    private const KUNCI_SINKRON_TERAKHIR = 'presensi.mesin.terakhir_tarik';

    /**
     * Terima payload { scans: [...], pegawai: [...] } dari presensi bridge.
     */
    public function sinkron(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scans' => ['required', 'array', 'max:5000'],
            'scans.*' => ['required', 'array', 'max:10'],
            'scans.*.nik' => ['required', 'string', 'max:50'],
            'scans.*.waktu' => ['required', 'string', 'max:30'],
            'scans.*.jenis' => ['required', 'string', 'in:masuk,pulang'],
            'pegawai' => ['nullable', 'array', 'max:5000'],
            'pegawai.*' => ['required', 'array', 'max:10'],
            'pegawai.*.nik' => ['required', 'string', 'max:50'],
            'pegawai.*.nama' => ['nullable', 'string', 'max:120'],
        ]);

        $karyawan = app(PresensiMesinKaryawanService::class);
        if (array_key_exists('pegawai', $validated) && is_array($validated['pegawai'])) {
            $pasangan = [];
            foreach ($validated['pegawai'] as $item) {
                $nik = trim((string) ($item['nik'] ?? ''));
                $nama = trim((string) ($item['nama'] ?? ''));
                if ($nik === '' || $nama === '') {
                    continue;
                }

                $pasangan[] = ['nik' => $nik, 'nama' => $nama];
            }

            $karyawan->simpanNikKaryawan($pasangan);
        }

        $presensi = app(PresensiMesinService::class);
        $scans = [];
        foreach ($validated['scans'] as $scan) {
            $nik = $presensi->normalizeNik((string) ($scan['nik'] ?? ''));
            try {
                $waktu = Carbon::parse($scan['waktu']);
            } catch (\Throwable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format waktu scan tidak valid.',
                ], 422);
            }

            if ($nik === null || $waktu === false) {
                continue;
            }

            $scans[] = [
                'nik' => $nik,
                'waktu' => $waktu,
                'jenis' => $presensi->jenisScan((string) ($scan['jenis'] ?? ''), $waktu),
            ];
        }

        if ($scans === []) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada scan valid pada payload.',
            ], 422);
        }

        $hasil = $presensi->importScans($scans, $request->user()?->id);
        Cache::forever(self::KUNCI_SINKRON_TERAKHIR, now()->toDateTimeString());

        AktivitasService::log(
            'tambah',
            'Sinkron presensi via bridge mesin',
            'Bridge mesin mengirim '.count($scans).' scan: '.$hasil['dibuat'].' data dibuat, '.$hasil['diperbarui'].' diperbarui, '.$hasil['tetap'].' tetap, '.$hasil['dilindungi'].' dilindungi, '.$hasil['nik_tidak_dikenal'].' NIK tidak dikenal.',
            'presensi_pegawai',
            null,
            null,
            $hasil,
        );

        return response()->json([
            'success' => true,
            'source' => 'presensi-bridge',
            'scan' => count($scans),
            'dibuat' => (int) ($hasil['dibuat'] ?? 0),
            'diperbarui' => (int) ($hasil['diperbarui'] ?? 0),
            'tetap' => (int) ($hasil['tetap'] ?? 0),
            'dilindungi' => (int) ($hasil['dilindungi'] ?? 0),
            'nik_tidak_dikenal' => (int) ($hasil['nik_tidak_dikenal'] ?? 0),
            'contoh_nik_tidak_dikenal' => (array) ($hasil['contoh_nik_tidak_dikenal'] ?? []),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'source' => 'presensi-bridge',
            'status' => 'online',
            'waktu' => now()->toDateTimeString(),
        ]);
    }
}
