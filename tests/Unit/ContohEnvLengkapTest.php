<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Guard sinkronisasi contoh environment (review #14).
 *
 * Dulu `.env.example` dan `.env.production.example` menyimpang ~50 kunci:
 * variabel yang dibaca `config/*.php` tidak pernah didokumentasikan, sehingga
 * operator tidak tahu harus mengisinya. Test ini memastikan setiap variabel
 * aplikasi (bukan bawaan framework) yang dipakai di `config/` terdokumentasi
 * di kedua berkas — boleh sebagai komentar `# KEY=`.
 */
class ContohEnvLengkapTest extends TestCase
{
    /** Prefix variabel milik aplikasi (bukan bawaan Laravel). */
    private const PREFIX = [
        'SIMANTAP_',
        'PRESENSI_',
        'DAPODIK_',
        'GEMINI_',
        'GOOGLE_',
        'ERAPOR_',
        'SECURITY_',
        'SCRAMBLE_',
        'BACKUP_',
        'TELESCOPE_ALLOWED_USERS',
    ];

    /**
     * @return list<string>
     */
    private function kunciConfig(): array
    {
        $kunci = [];

        foreach (glob(config_path().'/*.php') ?: [] as $berkas) {
            $isi = (string) file_get_contents($berkas);
            if (preg_match_all("/env\\('([A-Z0-9_]+)'/", $isi, $cocok) > 0) {
                foreach ($cocok[1] as $nama) {
                    $kunci[$nama] = true;
                }
            }
        }

        $hasil = array_keys($kunci);
        sort($hasil);

        return array_values(array_filter($hasil, fn (string $nama): bool => $this->milikAplikasi($nama)));
    }

    private function milikAplikasi(string $nama): bool
    {
        foreach (self::PREFIX as $prefix) {
            if (str_starts_with($nama, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function kunciTerdokumentasi(string $berkas): array
    {
        $isi = (string) file_get_contents(base_path($berkas));
        $hasil = [];

        foreach (preg_split('/\\R/', $isi) ?: [] as $baris) {
            $baris = preg_replace('/^\\s*#\\s?/', '', $baris) ?? $baris;
            if (preg_match('/^([A-Z0-9_]+)=/', trim($baris), $cocok) === 1) {
                $hasil[$cocok[1]] = true;
            }
        }

        return $hasil;
    }

    public function test_env_example_mendokumentasikan_semua_variabel_aplikasi(): void
    {
        $this->pastikanLengkap('.env.example');
    }

    public function test_env_production_example_mendokumentasikan_semua_variabel_aplikasi(): void
    {
        $this->pastikanLengkap('.env.production.example');
    }

    private function pastikanLengkap(string $berkas): void
    {
        $this->assertFileExists(base_path($berkas));

        $terdokumentasi = $this->kunciTerdokumentasi($berkas);
        $hilang = array_values(array_filter(
            $this->kunciConfig(),
            fn (string $nama): bool => ! isset($terdokumentasi[$nama])
        ));

        $this->assertSame([], $hilang, "Variabel berikut dipakai config/ tetapi tidak ada di {$berkas}:\\n".implode("\\n", $hilang));
    }
}
