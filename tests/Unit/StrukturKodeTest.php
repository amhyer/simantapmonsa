<?php

namespace Tests\Unit;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Guard struktur kode (review #17).
 *
 * Refactor besar belum dieksekusi sekaligus; sebagai pengganti, berkas yang
 * sudah gemuk dibekukan ukurannya dan berkas baru tidak boleh melewati ambang
 * 800 baris. Rencana pemecahan ada di docs/REFACTOR-RENCANA.md.
 *
 * Jika sebuah berkas di baseline berhasil dipecah, hapus entrinya dari
 * BASELINE; jangan menaikkan angka di sini.
 */
class StrukturKodeTest extends TestCase
{
    private const AMBANG_BARIS = 800;

    private const BASELINE = [
        'app/Services/MesinPresensi/AttendanceDatabaseReader.php' => 1250,
        'app/Services/Dapodik/DapodikSyncService.php' => 1250,
        'app/Services/Tka/TkaCognitiveAnalyticsService.php' => 1100,
        'app/Http/Controllers/Admin/PresensiLaporanController.php' => 1025,
        'app/Http/Controllers/Admin/DataReferensiController.php' => 825,
        'app/Http/Controllers/SuperAdmin/TenantController.php' => 825,
    ];

    private function baris(string $path): int
    {
        return count(file($path) ?: []);
    }

    public function test_berkas_baru_tidak_melewati_ambang_baris(): void
    {
        $pelanggaran = [];

        foreach ($this->daftarBerkasApp() as $relatif => $absolut) {
            if (array_key_exists($relatif, self::BASELINE)) {
                continue;
            }

            $jumlah = $this->baris($absolut);
            if ($jumlah > self::AMBANG_BARIS) {
                $pelanggaran[] = "{$relatif}: {$jumlah} baris";
            }
        }

        $this->assertSame([], $pelanggaran, "Berkas melewati ambang ".self::AMBANG_BARIS." baris:\n".implode("\n", $pelanggaran));
    }

    public function test_berkas_baseline_tidak_bertambah_panjang(): void
    {
        $pelanggaran = [];

        foreach (self::BASELINE as $relatif => $maksimal) {
            $absolut = base_path($relatif);
            $this->assertFileExists($absolut);

            $jumlah = $this->baris($absolut);
            if ($jumlah > $maksimal) {
                $pelanggaran[] = "{$relatif}: {$jumlah} baris (batas {$maksimal})";
            }
        }

        $this->assertSame([], $pelanggaran, "Berkas baseline bertambah panjang:\n".implode("\n", $pelanggaran));
    }

    public function test_entri_baseline_yang_sudah_dipecah_harus_dihapus(): void
    {
        $usang = [];

        foreach (self::BASELINE as $relatif => $maksimal) {
            $absolut = base_path($relatif);
            if (! is_file($absolut)) {
                continue;
            }

            if ($this->baris($absolut) <= self::AMBANG_BARIS) {
                $usang[] = "{$relatif} sudah <= ".self::AMBANG_BARIS.' baris';
            }
        }

        $this->assertSame([], $usang, "Hapus entri baseline yang sudah tidak perlu:\n".implode("\n", $usang));
    }

    public function test_rute_aplikasi_tidak_memakai_closure(): void
    {
        // `php artisan route:cache` menolak rute berbasis Closure. Rute dari
        // paket pihak ketiga (mis. Scramble saat dev tools aktif) tidak dihitung
        // — yang dijaga adalah berkas rute milik aplikasi ini.
        $pelanggaran = [];

        foreach (glob(base_path('routes').'/*.php') ?: [] as $berkas) {
            $baris = file($berkas) ?: [];
            foreach ($baris as $nomor => $isi) {
                if (preg_match('/Route::(get|post|put|patch|delete|any|match|fallback|view|redirect)\s*\(/', $isi) === 1
                    && str_contains($isi, 'function (')) {
                    $pelanggaran[] = basename($berkas).':'.($nomor + 1).' -> '.trim($isi);
                }
            }
        }

        $this->assertSame([], $pelanggaran, "Rute berikut memakai Closure sehingga route:cache gagal:\n".implode("\n", $pelanggaran));
    }

    public function test_routes_web_tidak_bertambah_gemuk(): void
    {
        $jumlah = $this->baris(base_path('routes/web.php'));

        $this->assertLessThanOrEqual(
            1200,
            $jumlah,
            'routes/web.php mencapai '.$jumlah.' baris. Pecah sesuai docs/REFACTOR-RENCANA.md bagian 3.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function daftarBerkasApp(): array
    {
        $hasil = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $berkas) {
            if (! $berkas->isFile() || $berkas->getExtension() !== 'php') {
                continue;
            }

            $absolut = $berkas->getPathname();
            $relatif = str_replace(base_path().DIRECTORY_SEPARATOR, '', $absolut);
            $hasil[str_replace(DIRECTORY_SEPARATOR, '/', $relatif)] = $absolut;
        }

        ksort($hasil);

        return $hasil;
    }
}
