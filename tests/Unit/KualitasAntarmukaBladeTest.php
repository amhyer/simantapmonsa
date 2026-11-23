<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Guard kualitas antarmuka (audit UI/UX).
 *
 * Audit 2026-11-23 menemukan beberapa kelas cacat yang berulang di 335 berkas
 * Blade: tombol di dalam form tanpa `type` (bisa memicu submit tak sengaja),
 * tombol ikon tanpa nama aksesibel, gambar tanpa `alt`, form POST tanpa
 * `@csrf`, tautan mati, dan `route()` yang menunjuk nama route tak terdaftar
 * (memicu RouteNotFoundException/500 saat halaman dirender).
 *
 * Semua pelanggaran yang ditemukan pada audit sudah diperbaiki, jadi test ini
 * menjaga agar tidak kembali. Jika test gagal, perbaiki berkas Blade yang
 * dilaporkan — jangan melemahkan assertion-nya.
 */
class KualitasAntarmukaBladeTest extends TestCase
{
    /** Atribut tag yang tahan terhadap "-> " di dalam {{ $x->y }}. */
    private const ATTR = '(?:(?:->)|[^>])*';

    /** @return array<string, string> path relatif => isi berkas */
    private function berkasBlade(): array
    {
        $base = base_path('resources/views');
        $hasil = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base)) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relatif = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            // View bawaan paket pihak ketiga bukan tanggung jawab kita.
            if (str_contains($relatif, 'resources/views/vendor/')) {
                continue;
            }

            $hasil[$relatif] = (string) file_get_contents($file->getPathname());
        }

        ksort($hasil);

        return $hasil;
    }

    private function tanpaKomentar(string $isi): string
    {
        $isi = (string) preg_replace('~\{\{--.*?--\}\}~s', '', $isi);
        $isi = (string) preg_replace('~<!--.*?-->~s', '', $isi);

        return (string) preg_replace('~@php\b.*?@endphp~s', '', $isi);
    }

    private function nomorBaris(string $isi, int $offset): int
    {
        return substr_count(substr($isi, 0, $offset), "\n") + 1;
    }

    private function laporkan(array $pelanggaran, string $judul): void
    {
        $this->assertSame([], $pelanggaran, $judul."\n - ".implode("\n - ", array_slice($pelanggaran, 0, 40)));
    }

    public function test_semua_route_di_view_terdaftar(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasBlade() as $berkas => $isi) {
            $bersih = $this->tanpaKomentar($isi);

            if (! preg_match_all("~route\\(\\s*'([^']+)'~", $bersih, $cocok, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($cocok[1] as [$nama, $posisi]) {
                if (preg_match('~\$~', $nama)) {
                    continue; // nama route dinamis, tidak bisa dinilai statis
                }

                // Dirouting lewat Route::has() sebelum dipakai: sudah dijaga aman.
                $sebelum = substr($bersih, max(0, $posisi - 160), 160);
                if (str_contains($sebelum, 'Route::has')) {
                    continue;
                }

                // Fitur opsional yang route-nya hanya didaftarkan saat config aktif
                // (lihat routes/web.php: cbt_enabled). View-nya pun hanya dirender
                // ketika fitur itu aktif, jadi tidak bisa dinilai di lingkungan default.
                if (! config('simantap.cbt_enabled') && str_starts_with($nama, 'cbt.')) {
                    continue;
                }

                if (! Route::has($nama)) {
                    $pelanggaran[] = "{$berkas}:{$this->nomorBaris($bersih, $posisi)} route('{$nama}') tidak terdaftar";
                }
            }
        }

        $this->laporkan($pelanggaran, 'Ada route() di Blade yang menunjuk nama route tak terdaftar.');
    }

    public function test_tombol_di_dalam_form_punya_atribut_type(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasBlade() as $berkas => $isi) {
            $bersih = $this->tanpaKomentar($isi);

            if (! preg_match_all('~<form\b'.self::ATTR.'>(.*?)</form>~is', $bersih, $form, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($form[1] as [$badan, $posisiForm]) {
                if (! preg_match_all('~<button\b('.self::ATTR.')>~i', $badan, $tombol, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($tombol[1] as [$atribut, $posisi]) {
                    if (preg_match('~\btype\s*=~i', $atribut)) {
                        continue;
                    }

                    $baris = $this->nomorBaris($bersih, $posisiForm + $posisi);
                    $pelanggaran[] = "{$berkas}:{$baris} <button> dalam form tanpa type";
                }
            }
        }

        $this->laporkan($pelanggaran, 'Tombol di dalam form wajib punya type eksplisit (submit/button).');
    }

    public function test_gambar_punya_alt(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasBlade() as $berkas => $isi) {
            $bersih = $this->tanpaKomentar($isi);

            if (! preg_match_all('~<img\b'.self::ATTR.'>~i', $bersih, $cocok, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($cocok[0] as [$tag, $posisi]) {
                if (preg_match('~\balt\s*=~i', $tag)) {
                    continue;
                }

                $pelanggaran[] = "{$berkas}:{$this->nomorBaris($bersih, $posisi)} <img> tanpa alt";
            }
        }

        $this->laporkan($pelanggaran, 'Setiap <img> wajib punya atribut alt (boleh kosong bila dekoratif).');
    }

    public function test_form_post_punya_csrf(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasBlade() as $berkas => $isi) {
            $bersih = $this->tanpaKomentar($isi);

            if (! preg_match_all('~<form\b('.self::ATTR.')>(.*?)</form>~is', $bersih, $cocok, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($cocok[1] as $i => [$atribut, $posisi]) {
                if (! preg_match('~method\s*=\s*"?post~i', $atribut)) {
                    continue;
                }

                $badan = $cocok[2][$i][0];
                if (str_contains($badan, '@csrf') || str_contains($badan, 'csrf_field()')) {
                    continue;
                }

                $pelanggaran[] = "{$berkas}:{$this->nomorBaris($bersih, $posisi)} form POST tanpa @csrf";
            }
        }

        $this->laporkan($pelanggaran, 'Form POST wajib menyertakan @csrf.');
    }

    public function test_tidak_ada_tautan_mati(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasBlade() as $berkas => $isi) {
            $bersih = $this->tanpaKomentar($isi);

            if (preg_match_all('~href\s*=\s*"#"~i', $bersih, $cocok, PREG_OFFSET_CAPTURE)) {
                foreach ($cocok[0] as [, $posisi]) {
                    $pelanggaran[] = "{$berkas}:{$this->nomorBaris($bersih, $posisi)} href=\"#\"";
                }
            }

            if (preg_match_all('~javascript:void\(\s*0\s*\)~i', $bersih, $cocok, PREG_OFFSET_CAPTURE)) {
                foreach ($cocok[0] as [, $posisi]) {
                    $pelanggaran[] = "{$berkas}:{$this->nomorBaris($bersih, $posisi)} javascript:void(0)";
                }
            }
        }

        $this->laporkan($pelanggaran, 'Tautan mati (href="#" / javascript:void(0)) wajib diganti tombol atau URL nyata.');
    }

    public function test_tombol_ikon_punya_nama_aksesibel(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasBlade() as $berkas => $isi) {
            $bersih = $this->tanpaKomentar($isi);

            if (! preg_match_all('~<button\b('.self::ATTR.')>(.*?)</button>~is', $bersih, $cocok, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($cocok[1] as $i => [$atribut, $posisi]) {
                $badan = $cocok[2][$i][0];

                if (preg_match('~\baria-label\s*=|\btitle\s*=~i', $atribut)) {
                    continue;
                }

                // Label dari Blade echo ({{ $x ? 'Buka' : 'Kunci' }}) dianggap
                // teks nyata: nilainya baru diketahui saat render, jadi tidak
                // dinilai statis di sini.
                if (str_contains($badan, '{{')) {
                    continue;
                }

                // Teks yang benar-benar terbaca pembaca layar.
                $teks = (string) preg_replace('~<i\b'.self::ATTR.'>\s*</i>~is', '', $badan);
                $teks = (string) preg_replace('~<svg\b.*?</svg>~is', '', $teks);
                $teks = trim((string) preg_replace('~<[^>]+>~s', '', $teks));

                // Emoji/tanda baca saja tidak dihitung sebagai nama aksesibel.
                $huruf = preg_replace('~[^A-Za-z0-9]~', '', $teks);
                if ($huruf === null || $huruf !== '') {
                    continue;
                }

                $pelanggaran[] = "{$berkas}:{$this->nomorBaris($bersih, $posisi)} tombol ikon tanpa aria-label/title";
            }
        }

        $this->laporkan($pelanggaran, 'Tombol yang hanya berisi ikon wajib punya aria-label atau title.');
    }

    public function test_push_script_punya_stack_di_layout(): void
    {
        $berkas = $this->berkasBlade();

        $stack = [];
        foreach ($berkas as $isi) {
            if (preg_match_all("~@stack\\(\\s*'([^']+)'~", $isi, $cocok)) {
                foreach ($cocok[1] as $nama) {
                    $stack[$nama] = true;
                }
            }
        }

        $pelanggaran = [];
        foreach ($berkas as $nama => $isi) {
            if (! preg_match_all("~@push\\(\\s*'([^']+)'~", $this->tanpaKomentar($isi), $cocok)) {
                continue;
            }

            foreach (array_unique($cocok[1]) as $namaStack) {
                if (! isset($stack[$namaStack])) {
                    $pelanggaran[] = "{$nama} @push('{$namaStack}') tanpa @stack di layout";
                }
            }
        }

        $this->laporkan($pelanggaran, 'Setiap @push wajib punya pasangan @stack di layout.');
    }
}
