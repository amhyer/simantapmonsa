<?php

/**
 * Verifikasi otomatis perbaikan bug SIMANTAP.
 *
 * Jalankan dari akar proyek:
 *     php tools/verifikasi-perbaikan.php
 *
 * Skrip ini TIDAK memerlukan Composer/vendor/koneksi basis data — murni
 * membaca berkas sumber, sehingga bisa dijalankan di mesin mana pun yang
 * punya PHP >= 8.1. Keluaran: daftar pemeriksaan PASS/FAIL dan kode keluar
 * non-nol bila ada yang gagal (cocok untuk CI).
 */

$root = dirname(__DIR__);
$lulus = 0;
$gagal = 0;
$catatan = [];

function rel(string $path, string $root): string
{
    return str_replace(str_replace('\\', '/', $root) . '/', '', str_replace('\\', '/', $path));
}

function baca(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

/**
 * Buang komentar & isi string agar pencocokan kode tidak salah deteksi.
 * Memakai tokenizer PHP (bukan regex) supaya tahan terhadap karakter aneh.
 */
function kodeBersih(string $kode): string
{
    $hasil = '';
    foreach (token_get_all($kode) as $token) {
        if (is_array($token)) {
            $id = $token[0];
            if (in_array($id, [T_COMMENT, T_DOC_COMMENT], true)) {
                $hasil .= ' ';
            } elseif (in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) {
                $hasil .= "''";
            } else {
                $hasil .= $token[1];
            }
        } else {
            $hasil .= $token;
        }
    }

    return $hasil;
}

/**
 * Kumpulan FQCN yang dideklarasikan di app/ & database/ — dipakai agar
 * referensi yang sah di namespace yang sama (mis. User::class di App\Models)
 * tidak dianggap "tanpa use".
 *
 * @return array<string, true>
 */
function kelasDideklarasikan(string $root): array
{
    $kelas = [];
    foreach (semuaPhp($root) as $path) {
        $relatif = rel($path, $root);
        if (!str_starts_with($relatif, 'app/') && !str_starts_with($relatif, 'database/')) {
            continue;
        }
        $kode = kodeBersih(baca($path));
        preg_match('/^namespace\s+([^;]+);/m', $kode, $ns);
        if (!$ns) {
            continue;
        }
        if (preg_match_all('/^(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $kode, $c)) {
            foreach ($c[1] as $nama) {
                $kelas[trim($ns[1]) . '\\' . $nama] = true;
            }
        }
    }

    return $kelas;
}

function semuaPhp(string $root): array
{
    $hasil = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        if (str_contains($path, '/vendor/') || str_contains($path, '/node_modules/') || str_contains($path, '/.git/')) {
            continue;
        }
        if ($file->getExtension() === 'php') {
            $hasil[] = $path;
        }
    }
    sort($hasil);

    return $hasil;
}

function periksa(string $nama, callable $uji): void
{
    global $lulus, $gagal, $catatan;

    try {
        $pesan = $uji();
    } catch (Throwable $e) {
        $pesan = 'ERROR: ' . $e->getMessage();
    }

    if ($pesan === true || $pesan === null) {
        $lulus++;
        echo "  [PASS] {$nama}\n";

        return;
    }

    $gagal++;
    $catatan[] = "{$nama}: {$pesan}";
    echo "  [FAIL] {$nama}\n         {$pesan}\n";
}

echo "=== Verifikasi Perbaikan SIMANTAP ===\n";
echo "Akar proyek: {$root}\n\n";

/* ──────────────────────────────────────────────────────────────
 * 1. Sintaks seluruh berkas PHP
 * ────────────────────────────────────────────────────────────── */
echo "1. Sintaks PHP\n";
periksa('Semua berkas PHP lolos token_get_all(TOKEN_PARSE)', function () use ($root) {
    $rusak = [];
    foreach (semuaPhp($root) as $path) {
        try {
            token_get_all(baca($path), TOKEN_PARSE);
        } catch (ParseError $e) {
            $rusak[] = rel($path, $root) . ' → ' . $e->getMessage();
        }
    }

    return $rusak === [] ? true : implode(' | ', array_slice($rusak, 0, 5));
});

/* ──────────────────────────────────────────────────────────────
 * 2. Referensi kelas tanpa import (fatal saat runtime)
 * ────────────────────────────────────────────────────────────── */
echo "\n2. Kelas global tanpa `use` di dalam namespace\n";
periksa('Tidak ada Log::/Rombel::/Semester:: tanpa import', function () use ($root) {
    $terlarang = [];
    $dideklarasikan = kelasDideklarasikan($root);
    $kelasGlobal = ['Log', 'Rombel', 'Semester', 'Siswa', 'User', 'Kuis', 'Nilai', 'Kehadiran', 'Materi', 'Dimensi', 'Kebiasaan', 'Catatan', 'Ptk', 'MataPelajaran', 'JadwalPelajaran', 'PengaturanGuru', 'RingkasanGuru', 'SekolahSettings', 'HasilKuis', 'NilaiErapot', 'OrangTuaSiswa', 'Aktivitas', 'ApiKey', 'TanggalRapor', 'Ekstrakurikuler'];
    // Hanya nama yang memang bukan kelas (keyword) yang diizinkan tanpa import.
    // CATATAN: 'Log' TIDAK boleh ada di sini — itulah salah satu bug yang diperiksa.
    $izin = ['self', 'static', 'parent'];

    foreach (semuaPhp($root) as $path) {
        if (!str_starts_with(rel($path, $root), 'app/') && !str_starts_with(rel($path, $root), 'database/')) {
            continue;
        }

        $kode = baca($path);
        $bersih = kodeBersih($kode);
        preg_match('/^namespace\s+([^;]+);/m', $bersih, $ns);
        if (!$ns) {
            continue;
        }
        $namespace = trim($ns[1]);

        $imports = [];
        // use A\B\C; dan use A\B\C as D;
        if (preg_match_all('/^use\s+(?:function\s+|const\s+)?(?!.*\{)([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $bersih, $m)) {
            foreach ($m[1] as $i => $fq) {
                $imports[] = $m[2][$i] !== '' ? $m[2][$i] : substr($fq, strrpos($fq, '\\') + 1);
            }
        }
        // group use: use A\B\{C, D as E};
        if (preg_match_all('/^use\s+(?:function\s+|const\s+)?([\w\\\\]+)\\{(.*?)\}\s*;/ms', $bersih, $g)) {
            foreach ($g[2] as $daftar) {
                foreach (preg_split('/[,\s]+/', $daftar) as $bagian) {
                    $bagian = trim($bagian);
                    if ($bagian === '') {
                        continue;
                    }
                    if (preg_match('/^(.+?)\s+as\s+(\w+)$/i', $bagian, $alias)) {
                        $imports[] = $alias[2];
                    } else {
                        $imports[] = $bagian;
                    }
                }
            }
        }

        foreach ($kelasGlobal as $kelas) {
            if (in_array($kelas, $imports, true) || in_array($kelas, $izin, true)) {
                continue;
            }
            // kelas lain di namespace yang sama (mis. User di App\Models)
            if (isset($dideklarasikan[$namespace . '\\' . $kelas])) {
                continue;
            }
            // referensi statis: Kelas::method(
            if (preg_match('/(?<![\w\\\\$>])' . preg_quote($kelas, '/') . '::/', $bersih)) {
                $terlarang[] = rel($path, $root) . " memakai {$kelas}:: tanpa `use` (namespace {$namespace})";
            }
        }
    }

    return $terlarang === [] ? true : implode(' | ', array_slice($terlarang, 0, 5));
});

/* ──────────────────────────────────────────────────────────────
 * 3. Route → controller & view
 * ────────────────────────────────────────────────────────────── */
echo "\n3. Route, controller, dan view\n";
periksa('Semua controller pada routes/*.php memiliki berkasnya', function () use ($root) {
    $hilang = [];
    $routes = baca($root . '/routes/web.php') . baca($root . '/routes/api.php');
    // Controller yang disebut di dalam komentar (route dinonaktifkan) bukan
    // dependensi aktif — pindai kode yang sudah dibuang komentarnya.
    $kodeRoutes = kodeBersih(baca($root . '/routes/web.php')) . kodeBersih(baca($root . '/routes/api.php'));
    preg_match_all('/\\\\?(App\\\\Http\\\\Controllers\\\\[\w\\\\]+)::class/', $kodeRoutes, $m);

    $dalamFlagCbt = str_contains($routes, "config('simantap.cbt_enabled'");

    foreach (array_unique($m[1]) as $kelas) {
        // FQCN App\Http\... → berkas app/Http/....php (akar 'app' huruf kecil)
        $path = $root . '/app/' . str_replace('\\', '/', substr($kelas, 4)) . '.php';
        if (is_file($path)) {
            continue;
        }
        // Controller CBT memang belum ada; boleh hanya bila route-nya
        // dimatikan di balik feature flag.
        if (str_contains($kelas, 'Controllers\\Cbt\\') && $dalamFlagCbt && is_file($root . '/config/simantap.php')) {
            continue;
        }
        $hilang[] = $kelas;
    }

    return $hilang === [] ? true : 'controller tidak ditemukan: ' . implode(', ', $hilang);
});

periksa('Semua view(...) yang dirujuk app/ tersedia (kecuali vendor)', function () use ($root) {
    $hasil = [];
    foreach (semuaPhp($root . '/app') as $path) {
        $kode = baca($path);
        if (preg_match_all("/view\(\s*'([\w.\-]+)'/", $kode, $m)) {
            foreach ($m[1] as $view) {
                if (str_starts_with($view, 'vendor.')) {
                    continue; // view paket pihak ketiga (mis. scramble)
                }
                $file = $root . '/resources/views/' . str_replace('.', '/', $view) . '.blade.php';
                if (!is_file($file)) {
                    $hasil[] = rel($path, $root) . " → {$view}";
                }
            }
        }
    }

    return $hasil === [] ? true : implode(' | ', array_slice($hasil, 0, 5));
});

/* ──────────────────────────────────────────────────────────────
 * 4. Kosakata jenis nilai (enum nilai.jenis)
 * ────────────────────────────────────────────────────────────── */
echo "\n4. Konsistensi `jenis` nilai\n";
periksa("Nilai enum DB = ['Tugas','Ulangan Harian','Praktik','PTS','PAS']", function () use ($root) {
    $migrasi = baca($root . '/database/migrations/2026_09_09_075731_create_nilai_table.php');
    $benar = str_contains($migrasi, "enum('jenis', ['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS'])");

    return $benar ? true : 'definisi enum nilai.jenis tidak sesuai ekspektasi';
});

periksa('UI Input Nilai mengirim nilai enum yang sah (bukan "UH")', function () use ($root) {
    $view = baca($root . '/resources/views/guru/input-nilai/index.blade.php');
    preg_match_all('/<option value="([^"]+)">/', $view, $m);
    $enum = ['Tugas', 'Ulangan Harian', 'Praktik', 'PTS', 'PAS'];
    $salah = array_values(array_diff($m[1], $enum));

    return $salah === [] ? true : 'opsi jenis tidak dikenal: ' . implode(', ', $salah);
});

periksa('InputNilaiController membatasi jenis ke daftar valid', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Guru/InputNilaiController.php');

    return str_contains($kode, "Rule::in") && str_contains($kode, 'JENIS_VALID')
        ? true
        : 'validasi jenis belum dibatasi Rule::in';
});

/* ──────────────────────────────────────────────────────────────
 * 5. Semantik batas_waktu kuis
 * ────────────────────────────────────────────────────────────── */
echo "\n5. Batas waktu kuis\n";
periksa('Model Kuis meng-cast batas_waktu sebagai datetime', function () use ($root) {
    $model = baca($root . '/app/Models/Kuis.php');

    return str_contains($model, "'batas_waktu' => 'datetime'")
        ? true
        : 'cast datetime untuk batas_waktu tidak ditemukan';
});

periksa('Ada migrasi yang mengubah batas_waktu menjadi timestamp', function () use ($root) {
    $files = glob($root . '/database/migrations/*.php');
    foreach ($files as $f) {
        $isi = baca($f);
        if (str_contains($isi, "'kuis'") && str_contains($isi, 'batas_waktu') && str_contains($isi, "timestamp('batas_waktu')")) {
            return true;
        }
    }

    return 'migrasi perubahan batas_waktu → timestamp tidak ditemukan';
});

periksa('Form guru memakai input datetime-local (bukan number menit)', function () use ($root) {
    $gagal = [];
    foreach (['create', 'edit'] as $v) {
        $view = baca($root . "/resources/views/guru/kuis/{$v}.blade.php");
        if (!str_contains($view, 'type="datetime-local" name="batas_waktu"')) {
            $gagal[] = "guru/kuis/{$v}";
        }
        if (str_contains($view, 'Batas Waktu (menit)')) {
            $gagal[] = "guru/kuis/{$v} masih berlabel menit";
        }
    }

    return $gagal === [] ? true : implode(', ', $gagal);
});

periksa('Controller kuis memvalidasi batas_waktu sebagai tanggal', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Guru/KuisController.php');

    return str_contains($kode, "'batas_waktu' => 'nullable|date'")
        ? true
        : 'validasi batas_waktu belum nullable|date';
});

periksa('Seeder tidak lagi menulis batas_waktu integer', function () use ($root) {
    $seeder = baca($root . '/database/seeders/DatabaseSeeder.php');

    return preg_match("/'batas_waktu'\s*=>\s*\d+/", $seeder) !== 1
        ? true
        : 'seeder masih menulis angka menit ke batas_waktu';
});

/* ──────────────────────────────────────────────────────────────
 * 6. Predikat satu sumber
 * ────────────────────────────────────────────────────────────── */
echo "\n6. Sumber kebenaran predikat\n";
periksa('NilaiErapot::generatePredikat memakai NilaiService', function () use ($root) {
    $kode = baca($root . '/app/Models/NilaiErapot.php');

    return str_contains($kode, 'NilaiService') ? true : 'generatePredikat masih memakai ambang sendiri';
});

periksa('Kepsek DashboardController memakai NilaiService', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Kepsek/DashboardController.php');

    return str_contains($kode, 'NilaiService') ? true : 'getPredikat kepsek masih memakai ambang sendiri';
});

periksa('Tidak ada ambang mati 90/80 di predikat e-Rapor', function () use ($root) {
    $kode = baca($root . '/app/Models/NilaiErapot.php');

    return str_contains($kode, '>= 90')
        ? 'masih ada ambang mati >= 90'
        : true;
});

/* ──────────────────────────────────────────────────────────────
 * 7. e-Rapor: resolver, view alignment, dan kolom FK
 * ────────────────────────────────────────────────────────────── */
echo "\n7. e-Rapor (resolver & alignment)\n";
periksa('Kelas App\\Services\\JadwalPelajaranResolver tersedia', function () use ($root) {
    return is_file($root . '/app/Services/JadwalPelajaranResolver.php')
        ? true
        : 'kelas JadwalPelajaranResolver belum ada';
});

periksa('View admin/referensi/alignment-check tersedia', function () use ($root) {
    return is_file($root . '/resources/views/admin/referensi/alignment-check.blade.php')
        ? true
        : 'view alignment-check belum ada';
});

periksa('Kolom nilai_erapor.jadwal_pelajaran_id dibuat oleh migrasi', function () use ($root) {
    foreach (glob($root . '/database/migrations/*.php') as $f) {
        $isi = baca($f);
        if (str_contains($isi, 'nilai_erapor') && str_contains($isi, "foreignId('jadwal_pelajaran_id')")) {
            return true;
        }
    }

    return 'migrasi kolom jadwal_pelajaran_id pada nilai_erapor tidak ditemukan';
});

/* ──────────────────────────────────────────────────────────────
 * 8. Backup vs wipe
 * ────────────────────────────────────────────────────────────── */
echo "\n8. Cakupan backup vs penghapusan data\n";
periksa('Hapus Semua Data memakai registry tabel terpusat (bukan daftar sendiri)', function () use ($root) {
    $controller = baca($root . '/app/Http/Controllers/Admin/BackupController.php');
    if (!str_contains($controller, 'BackupTables::dikosongkan()')) {
        return 'wipeAll masih memakai daftar tabel sendiri';
    }

    $registry = baca($root . '/app/Support/BackupTables.php');
    preg_match('/function dikosongkan\(\).*?\n    \}/s', $registry, $m);
    $body = $m[0] ?? '';
    foreach (['self::tabel()', 'self::tidakDicadangkan()', 'self::tidakDikosongkan()'] as $wajib) {
        if (!str_contains($body, $wajib)) {
            return 'dikosongkan() tidak menurunkan daftar dari ' . $wajib;
        }
    }

    return true;
});

periksa('Registry backup konsisten: tidak ada tabel ganda & daftar pertahankan minimal', function () use ($root) {
    $registry = baca($root . '/app/Support/BackupTables.php');

    preg_match('/function semua\(\).*?return \[(.*?)\n        \];/s', $registry, $r);
    preg_match_all("/'([\w]+)' => \['class'/", $r[1] ?? '', $rt);
    $dicadangkan = $rt[1];

    preg_match('/function tidakDicadangkan\(\).*?return \[(.*?)\n        \];/s', $registry, $e);
    preg_match_all("/'([\w]+)',/", $e[1] ?? '', $et);
    $tidakDicadangkan = $et[1];

    preg_match('/function tidakDikosongkan\(\).*?return \[(.*?)\n        \];/s', $registry, $k);
    preg_match_all("/'([\w]+)',/", $k[1] ?? '', $kt);
    $dipertahankan = $kt[1];

    $ganda = array_values(array_intersect($dicadangkan, $tidakDicadangkan));
    if ($ganda !== []) {
        return 'tabel tercatat dicadangkan sekaligus tidak dicadangkan: ' . implode(', ', $ganda);
    }

    $bolehBertahan = ['users', 'semesters', 'sekolah_settings'];
    $asing = array_values(array_diff($dipertahankan, $bolehBertahan));
    if ($asing !== []) {
        return 'tabel dipertahankan di luar daftar aman: ' . implode(', ', $asing);
    }

    $logDipertahankan = array_values(array_intersect($tidakDicadangkan, $dipertahankan));
    if ($logDipertahankan !== []) {
        return 'log dipertahankan padahal dapat dibuat ulang: ' . implode(', ', $logDipertahankan);
    }

    $data = array_values(array_unique(array_merge($dicadangkan, $tidakDicadangkan)));
    if (array_diff($data, $dipertahankan) === []) {
        return 'tidak ada tabel data yang dikosongkan';
    }

    return true;
});

periksa('Backup terjadwal dan export async berbagi writer streaming', function () use ($root) {
    $command = baca($root . '/app/Console/Commands/BackupData.php');
    $job = baca($root . '/app/Jobs/ExportBackupJob.php');
    $writer = baca($root . '/app/Services/Backup/BackupStreamWriter.php');

    return str_contains($command, 'BackupStreamWriter')
        && str_contains($job, 'BackupStreamWriter')
        && str_contains($writer, 'BackupTables::semua()')
        && str_contains($command, 'php://temp/maxmemory:2097152')
        && str_contains($job, 'php://temp/maxmemory:2097152')
        ? true
        : 'backup terjadwal/export belum berbagi stream writer terpusat';
});

/* ──────────────────────────────────────────────────────────────
 * 9. Kredensial
 * ────────────────────────────────────────────────────────────── */
echo "\n9. Kredensial di repositori\n";
periksa('dapodik-bridge/config.json tidak memuat api_key/token', function () use ($root) {
    $path = $root . '/dapodik-bridge/config.json';
    if (!is_file($path)) {
        return true; // tidak ada berkas = aman (memakai env)
    }
    $cfg = json_decode(baca($path), true);
    $masalah = [];
    foreach (['api_key', 'dapodik_token'] as $kunci) {
        if (!empty($cfg[$kunci])) {
            $masalah[] = $kunci;
        }
    }

    return $masalah === [] ? true : 'masih berisi nilai rahasia: ' . implode(', ', $masalah);
});

periksa('Simpanan rahasia bridge tidak di-track git', function () use ($root) {
    $masalah = [];
    $ignore = baca($root . '/.gitignore');

    if (!str_contains($ignore, 'dapodik-bridge/config.json')) {
        $masalah[] = '.gitignore belum mengecualikan dapodik-bridge/config.json';
    }
    if (!is_file($root . '/dapodik-bridge/config.example.json')) {
        $masalah[] = 'contoh tanpa rahasia (config.example.json) belum ada';
    }

    // Entry .gitignore saja TIDAK melepas berkas yang sudah pernah di-add;
    // jadi bila ini checkout git, pastikan berkasnya benar-benar tak dilacak.
    if (is_dir($root . '/.git') && function_exists('exec')) {
        $keluaran = [];
        $kode = 0;
        @exec('git -C ' . escapeshellarg($root) . ' ls-files --error-unmatch dapodik-bridge/config.json 2>&1', $keluaran, $kode);
        if ($kode === 0) {
            $masalah[] = 'masih dilacak git — jalankan: git rm --cached dapodik-bridge/config.json';
        }
    }

    return $masalah === [] ? true : implode('; ', $masalah);
});

periksa('setup_api_key.php tidak memuat literal API key', function () use ($root) {
    $kode = baca($root . '/tools/setup_api_key.php');
    if (preg_match("/ApiKey::create/", $kode) && !preg_match('/\$apiKey\s*=/', $kode)) {
        return 'API key masih hardcoded';
    }

    return str_contains($kode, 'nama_pengguna')
        ? true
        : 'setup_api_key.php masih memakai kolom yang tidak ada (email)';
});

/* ──────────────────────────────────────────────────────────────
 * 10. Modul CBT tidak boleh 500
 * ────────────────────────────────────────────────────────────── */
echo "\n10. Modul CBT\n";
periksa('Route CBT dimatikan di balik feature flag', function () use ($root) {
    $routes = baca($root . '/routes/web.php');
    $flag = is_file($root . '/config/simantap.php');

    if (!str_contains($routes, "config('simantap.cbt_enabled'")) {
        return 'route CBT masih aktif tanpa flag';
    }

    return $flag ? true : 'config/simantap.php tidak ada';
});

/* ──────────────────────────────────────────────────────────────
 * 11. Autentikasi & sesi
 * ────────────────────────────────────────────────────────────── */
echo "\n11. Autentikasi, sesi, dan kunci API\n";
periksa('Login memeriksa kolom aktif/archived_at', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Auth/LoginController.php');

    if (!str_contains($kode, '$user->aktif')) {
        return 'LoginController tidak memeriksa kolom aktif';
    }

    return str_contains($kode, 'archived_at')
        ? true
        : 'LoginController tidak memeriksa archived_at';
});

periksa('Pendaftaran publik tidak mengaktifkan akun otomatis', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/LandingController.php');

    if (!preg_match("/'aktif'\s*=>\s*false/", $kode)) {
        return "LandingController masih membuat akun 'aktif' => true untuk pendaftar publik";
    }

    return true;
});

periksa('Tabel sessions dibuat oleh migrasi (SESSION_DRIVER=database)', function () use ($root) {
    foreach (glob($root . '/database/migrations/*.php') as $f) {
        if (str_contains(baca($f), "Schema::create('sessions'")) {
            return true;
        }
    }

    return 'migrasi tabel sessions tidak ditemukan';
});

periksa('Kunci API tidak disimpan dalam bentuk polos', function () use ($root) {
    $model = baca($root . '/app/Models/ApiKey.php');
    if (!preg_match("/'key'\s*=>\s*null/", $model)) {
        return 'ApiKey::generate masih menulis kunci polos ke kolom key';
    }

    $middleware = baca($root . '/app/Http/Middleware/ApiKeyMiddleware.php');

    return str_contains($middleware, "where('key',")
        ? 'ApiKeyMiddleware masih mencari kunci polos (fallback)'
        : true;
});

/* ──────────────────────────────────────────────────────────────
 * 12. Integritas data e-Rapor & Dapodik
 * ────────────────────────────────────────────────────────────── */
echo "\n12. Integritas data\n";
periksa('Import e-Rapor menghitung predikat sebelum deskripsi', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Guru/ImportErapotController.php');
    $posPredikat = strpos($kode, '$nilai->predikat = $nilai->generatePredikat');
    $posDeskripsi = strpos($kode, '$nilai->deskripsi_capaian = $nilai->generateDeskripsi');

    if ($posPredikat === false || $posDeskripsi === false) {
        return 'urutan penulisan predikat/deskripsi tidak ditemukan';
    }

    return $posPredikat < $posDeskripsi
        ? true
        : 'generateDeskripsi() masih dipanggil sebelum predikat disimpan';
});

periksa('generateDeskripsi() memakai predikat baru / hitung ulang', function () use ($root) {
    $kode = baca($root . '/app/Models/NilaiErapot.php');

    return str_contains($kode, '$this->predikat ?: $this->generatePredikat()')
        ? true
        : 'generateDeskripsi() tidak memiliki fallback predikat';
});

periksa('sekolah_settings.guru_id nullable (sinkronisasi Dapodik)', function () use ($root) {
    foreach (glob($root . '/database/migrations/*.php') as $f) {
        $isi = baca($f);
        // harus berupa PERUBAHAN kolom guru_id → nullable, bukan sekadar
        // menyebut 'nullable' untuk kolom lain di migrasi create.
        if (str_contains($isi, 'sekolah_settings')
            && preg_match("/foreignId\('guru_id'\)\s*->nullable\(\)\s*->change\(\)/", $isi)) {
            return true;
        }
    }

    return 'belum ada migrasi yang membuat sekolah_settings.guru_id nullable';
});

periksa('Full sync Google Sheets dibatasi ke data guru yang login', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Guru/IntegrasiController.php');

    if (!str_contains($kode, "where('guru_id', \$guruId)")) {
        return 'fullSync masih mengambil data seluruh sekolah';
    }

    return str_contains($kode, 'collect($models)->sum(')
        ? true
        : 'perhitungan jumlah hasil sync belum diperbaiki';
});

periksa('Akun guru hasil sinkronisasi wajib ganti sandi awal', function () use ($root) {
    $berkas = [
        'app/Services/DapodikService.php',
        'app/Services/Dapodik/DapodikSyncService.php',
        'app/Http/Controllers/Api/DapodikSyncController.php',
    ];

    $luput = [];
    foreach ($berkas as $rel) {
        $kode = baca($root . '/' . $rel);
        if (str_contains($kode, "'guru123'") && !str_contains($kode, 'force_password_change')) {
            $luput[] = $rel;
        }
    }

    return $luput === [] ? true : 'tanpa force_password_change: ' . implode(', ', $luput);
});

periksa('Rute guru.dimensi.destroy terdaftar (tombol Hapus di view berfungsi)', function () use ($root) {
    $routes = baca($root . '/routes/web.php');

    return preg_match("/Route::resource\('dimensi',\s*DimensiController::class\)->only\(\[[^\]]*'destroy'[^\]]*\]\)/", $routes) === 1
        ? true
        : 'resource dimensi belum memuat aksi destroy';
});

periksa('DimensiController::destroy memeriksa kepemilikan data (403)', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Guru/DimensiController.php');
    $kurang = [];
    if (!str_contains($kode, 'function destroy(Dimensi $dimensi)')) {
        $kurang[] = 'method destroy';
    }
    if (!str_contains($kode, 'guru_id !== auth()->id()')) {
        $kurang[] = 'cek guru_id';
    }
    if (!str_contains($kode, 'abort(403)')) {
        $kurang[] = 'abort(403)';
    }

    return $kurang === [] ? true : 'belum ada: ' . implode(', ', $kurang);
});

periksa('Submit kuis ganda ditangani sebagai "sudah tersimpan"', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Siswa/KuisController.php');
    $kurang = [];
    if (!str_contains($kode, 'UniqueConstraintViolationException')) {
        $kurang[] = 'catch UniqueConstraintViolationException';
    }
    if (!str_contains($kode, "'Jawaban Anda sudah tersimpan sebelumnya.'")) {
        $kurang[] = 'pesan sudah tersimpan';
    }

    $adaIndex = false;
    foreach (glob($root . '/database/migrations/*hasil_kuis*.php') as $f) {
        if (str_contains(baca($f), "unique(['kuis_id', 'siswa_id'])")) {
            $adaIndex = true;
            break;
        }
    }
    if (!$adaIndex) {
        $kurang[] = 'unique index hasil_kuis (kuis_id, siswa_id)';
    }

    return $kurang === [] ? true : 'belum ada: ' . implode(', ', $kurang);
});

periksa('Header KUIS tetap kompatibel GAS + catatan koordinasi', function () use ($root) {
    $config = baca($root . '/config/google.php');
    $layanan = baca($root . '/app/Services/GoogleSheetsService.php');
    $kurang = [];
    if (!str_contains($config, "'Batas Waktu (menit)'")) {
        $kurang[] = "header lama 'Batas Waktu (menit)' di config/google.php";
    }
    if (!str_contains($config, 'CATATAN KOORDINASI GAS')) {
        $kurang[] = 'catatan koordinasi di config/google.php';
    }
    if (!str_contains($layanan, 'kompatibilitas GAS')) {
        $kurang[] = 'penjelasan kompatibilitas di GoogleSheetsService';
    }

    return $kurang === [] ? true : 'belum ada: ' . implode(', ', $kurang);
});

periksa('Kredensial lama tidak lagi tersimpan di berkas repositori', function () use ($root) {
    // Sidik jari SHA-256 dari nilai yang pernah bocor. Plaintext-nya sengaja
    // TIDAK disimpan di repositori ini — bahkan untuk keperluan pemeriksaan —
    // sehingga kredensial lama tidak hidup kembali di dalam kode.
    $sidik = [
        '17e52cacbfd9dbd5eecf63489ba97c583ba38014319890fcb6ab0ea7637fee9f', // api_key bridge lama (64 karakter)
        '2c18fd4dfd0d86a47aefeab2af806cb6bfdc66b138d0d4210a4e5c008d88ae5e', // dapodik_token lama (15 karakter)
    ];

    $lewati = ['.git', 'node_modules', 'vendor', 'storage', '__pycache__', 'dist', 'build'];
    $ekstensi = ['php', 'py', 'json', 'md', 'js', 'css', 'txt', 'yml', 'yaml', 'sh', 'bat', 'sql', 'html'];
    $temuan = [];

    $dir = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator($dir, function ($item) use ($lewati) {
        return !($item->isDir() && in_array($item->getFilename(), $lewati, true));
    });

    foreach (new RecursiveIteratorIterator($filter) as $berkas) {
        if (!$berkas->isFile()) {
            continue;
        }
        $nama = $berkas->getFilename();
        $ekst = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
        if (!in_array($ekst, $ekstensi, true) && !str_contains($nama, '.env')) {
            continue;
        }
        $isi = @file_get_contents($berkas->getPathname());
        if ($isi === false) {
            continue;
        }
        // Nilai rahasia selalu berupa deretan alfanumerik >= 15 karakter;
        // cukup hash kandidat, bukan membandingkan plaintext.
        preg_match_all('/[A-Za-z0-9_\-]{15,}/', $isi, $cocok);
        foreach ($cocok[0] as $kandidat) {
            if (in_array(hash('sha256', $kandidat), $sidik, true)) {
                $temuan[] = rel($berkas->getPathname(), $root) . ' (panjang ' . strlen($kandidat) . ')';
                break;
            }
        }
    }

    return $temuan === [] ? true : 'kredensial lama masih ada di: ' . implode(', ', array_unique($temuan));
});

periksa('Tidak ada kunci/token hardcoded di skrip bridge & config', function () use ($root) {
    $berkas = array_merge(
        glob($root . '/dapodik-bridge/*.py') ?: [],
        glob($root . '/dapodik-bridge/*.json') ?: [],
        glob($root . '/config/*.php') ?: []
    );
    $temuan = [];
    foreach ($berkas as $f) {
        $nama = basename($f);
        if (str_contains($nama, 'example') || $nama === 'config.json') {
            continue; // contoh memang placeholder; config.json = data lokal (di luar git)
        }
        foreach (preg_split('/\R/', baca($f)) as $no => $baris) {
            if (preg_match('/\b(api_?key|token|password|secret)\b["\']?\s*[:=]\s*["\']([A-Za-z0-9_\-]{12,})["\']/i', $baris, $m) !== 1) {
                continue;
            }
            // Anggap rahasia hanya bila bercampur huruf besar, huruf kecil, dan angka.
            $nilai = $m[2];
            if (preg_match('/[A-Z]/', $nilai) && preg_match('/[a-z]/', $nilai) && preg_match('/\d/', $nilai)) {
                $temuan[] = rel($f, $root) . ':' . ($no + 1);
            }
        }
    }

    return $temuan === [] ? true : 'nilai rahasia hardcoded di: ' . implode(', ', $temuan);
});

/* ──────────────────────────────────────────────────────────────
 * 13. Kesiapan launching (LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md)
 * ────────────────────────────────────────────────────────────── */
echo "\n13. Kesiapan launching\n";

periksa('Admin yang mereset sandi memicu force_password_change', function () use ($root) {
    // Sengaja memakai sumber mentah (bukan kodeBersih): `force_password_change`
    // di sini adalah KUNCI ARRAY berupa string literal, dan kodeBersih() menghapus
    // seluruh literal string sehingga pemeriksaannya tidak akan pernah cocok.
    $kode = baca($root . '/app/Http/Controllers/Admin/UserController.php');

    if (! preg_match(
        "/if\\s*\\(\\s*!\\s*empty\\s*\\(\\s*\\\$validated\\s*\\[\\s*'kata_sandi'\\s*\\]\\s*\\)/",
        $kode,
        $m,
        PREG_OFFSET_CAPTURE
    )) {
        return "pola if (!empty(\$validated['kata_sandi'])) tidak ditemukan di UserController";
    }

    // Pola ini menuntut penetapan `$data['force_password_change'] = true` benar-benar
    // mengikuti `Hash::make(...)`. Mencari teks `force_password_change` saja tidak
    // cukup: pesan flash di akhir method juga menyebut nama yang sama.
    if (preg_match(
        "/Hash::make\\(\\s*\\\$validated\\s*\\[\\s*'kata_sandi'\\s*\\]\\s*\\)\\s*;(?:.|\\n){0,800}?\\\$data\\s*\\[\\s*'force_password_change'\\s*\\]\\s*=\\s*true/",
        $kode
    ) === 1) {
        return true;
    }

    return 'UserController::update mereset sandi tanpa menandai force_password_change';
});

periksa('Akun baru buatan admin ditandai force_password_change', function () use ($root) {
    $kode = baca($root . '/app/Http/Controllers/Admin/UserController.php');

    if (! preg_match('/public function store\((.*?)\n    \}/s', $kode, $m)) {
        return 'method store() tidak ditemukan di UserController';
    }

    return str_contains($m[1], "'force_password_change' => true")
        ? true
        : 'UserController::store tidak membuat akun dengan force_password_change';
});

periksa('Seeder kredensial contoh dijaga SeedGuard', function () use ($root) {
    if (! is_file($root . '/database/seeders/Support/SeedGuard.php')) {
        return 'database/seeders/Support/SeedGuard.php tidak ada';
    }

    $kurang = [];
    foreach (['DatabaseSeeder', 'DemoDataSeeder', 'ApiKeySeeder'] as $seeder) {
        $kode = baca("{$root}/database/seeders/{$seeder}.php");
        if (! str_contains($kode, 'SeedGuard::bolehJalan')) {
            $kurang[] = $seeder;
        }
    }

    return $kurang === [] ? true : 'seeder tanpa penjaga: ' . implode(', ', $kurang);
});

periksa('Semua grup route CBT memasang force.password.change', function () use ($root) {
    $sumber = baca($root . '/routes/web.php');

    if (! preg_match_all(
        '/Route::middleware\(\[([^\]]*)\]\)->prefix\(\'cbt[^\']*\'\)/',
        $sumber,
        $cocok
    )) {
        return 'tidak ada grup route CBT yang cocok dengan pola';
    }

    $tanpa = [];
    foreach ($cocok[1] as $middleware) {
        if (! str_contains($middleware, 'force.password.change')) {
            $tanpa[] = '[' . trim($middleware) . ']';
        }
    }

    return $tanpa === [] ? true : 'grup CBT tanpa middleware: ' . implode(' | ', $tanpa);
});

periksa('Setiap grup route ber-auth memasang force.password.change', function () use ($root) {
    $sumber = baca($root . '/routes/web.php');

    if (! preg_match_all(
        '/Route::middleware\((\[[^\]]*\])\)/',
        $sumber,
        $cocok,
        PREG_OFFSET_CAPTURE
    )) {
        return 'tidak ada Route::middleware([...]) yang cocok dengan pola';
    }

    $tanpa = [];
    foreach ($cocok[1] as $i => $pair) {
        $middleware = $pair[0];

        // Hanya grup yang memang memakai middleware auth.
        if (! preg_match("/['\"]auth['\"]/", $middleware)) {
            continue;
        }

        if (str_contains($middleware, 'force.password.change')) {
            continue;
        }

        // Pengecualian: grup yang MENDAFTARKAN route ganti sandi itu sendiri.
        // Memasang middleware di sana akan menimbulkan redirect melingkar.
        $setelah = substr($sumber, $pair[1], 400);
        if (str_contains($setelah, 'force-password-change')) {
            continue;
        }

        $tanpa[] = trim($middleware);
    }

    return $tanpa === []
        ? true
        : 'grup auth tanpa force.password.change: ' . implode(' | ', $tanpa);
});

periksa('Template .env produksi mematikan debug & mengamankan sesi', function () use ($root) {
    $path = $root . '/.env.production.example';
    if (! is_file($path)) {
        return '.env.production.example tidak ada';
    }

    $isi = baca($path);
    $wajib = [
        'APP_ENV=production',
        'APP_DEBUG=false',
        'SESSION_SECURE_COOKIE=true',
        'TELESCOPE_ENABLED=false',
    ];

    $kurang = array_values(array_filter($wajib, fn ($k) => ! str_contains($isi, $k)));
    if ($kurang !== []) {
        return 'nilai wajib tidak ada: ' . implode(', ', $kurang);
    }

    return preg_match('/^APP_KEY=.+$/m', $isi) === 1
        ? 'APP_KEY terisi di template — harus kosong'
        : true;
});

periksa('Panduan deployment tersedia dan README bukan template Laravel', function () use ($root) {
    if (! is_file($root . '/DEPLOYMENT.md')) {
        return 'DEPLOYMENT.md tidak ada';
    }

    $deploy = baca($root . '/DEPLOYMENT.md');
    $kurang = [];
    foreach (['schedule:run', 'queue:work', 'npm run build', 'migrate --force'] as $frasa) {
        if (! str_contains($deploy, $frasa)) {
            $kurang[] = $frasa;
        }
    }
    if ($kurang !== []) {
        return 'DEPLOYMENT.md tidak menyebut: ' . implode(', ', $kurang);
    }

    $readme = baca($root . '/README.md');
    if (str_contains($readme, 'Laravel is accessible, powerful, and provides tools')) {
        return 'README.md masih memakai teks template Laravel';
    }

    return str_contains($readme, 'DEPLOYMENT.md')
        ? true
        : 'README.md tidak menautkan DEPLOYMENT.md';
});

/* ──────────────────────────────────────────────────────────────
 * 14. Hardening operasional (S1, S2, S3, S5, S7)
 * ────────────────────────────────────────────────────────────── */
echo "\n14. Hardening operasional\n";

periksa('Middleware SecurityHeaders terdaftar global', function () use ($root) {
    if (! is_file($root . '/app/Http/Middleware/SecurityHeaders.php')) {
        return 'app/Http/Middleware/SecurityHeaders.php tidak ada';
    }

    $bootstrap = baca($root . '/bootstrap/app.php');

    return str_contains($bootstrap, 'SecurityHeaders::class')
        ? true
        : 'SecurityHeaders tidak didaftarkan di bootstrap/app.php';
});

periksa('Config keamanan ada dan CSP sengaja kosong', function () use ($root) {
    if (! is_file($root . '/config/security.php')) {
        return 'config/security.php tidak ada';
    }

    $isi = baca($root . '/config/security.php');

    if (! str_contains($isi, "'content_security_policy'")) {
        return 'kunci content_security_policy tidak ada di config/security.php';
    }

    // Presensi memakai geolokasi & QR memakai kamera — jangan dimatikan.
    return str_contains($isi, 'geolocation=(self)') && str_contains($isi, 'camera=(self)')
        ? true
        : 'Permissions-Policy mematikan geolokasi/kamera yang dipakai presensi & QR';
});

periksa('Halaman error kustom lengkap', function () use ($root) {
    $kurang = [];
    foreach ([403, 404, 419, 429, 500, 503] as $kode) {
        if (! is_file("{$root}/resources/views/errors/{$kode}.blade.php")) {
            $kurang[] = $kode;
        }
    }

    if ($kurang !== []) {
        return 'halaman error tidak ada: ' . implode(', ', $kurang);
    }

    // Halaman error tidak boleh bergantung pada aset build maupun CDN.
    // Dicocokkan sebagai PEMANGGILAN (`@vite(`, `<script src=...`), bukan teks
    // bebas — kata "@vite" di dalam komentar pernah membuat pemeriksaan ini
    // melaporkan kegagalan palsu.
    $bergantung = [];
    $pola = ['@vite(', '@vite (', 'cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'unpkg.com'];
    foreach (glob($root . '/resources/views/errors/*.blade.php') ?: [] as $f) {
        $isi = baca($f);
        foreach ($pola as $jarum) {
            if (str_contains($isi, $jarum)) {
                $bergantung[] = basename($f) . " ({$jarum})";
            }
        }
    }

    return $bergantung === []
        ? true
        : 'halaman error bergantung aset eksternal: ' . implode(', ', $bergantung);
});

periksa('favicon.ico berisi ikon valid', function () use ($root) {
    $path = $root . '/public/favicon.ico';

    if (! is_file($path)) {
        return 'public/favicon.ico tidak ada';
    }

    $ukuran = filesize($path);
    if ($ukuran < 100) {
        return "public/favicon.ico berukuran {$ukuran} byte (kosong?)";
    }

    $isi = (string) file_get_contents($path);
    $kepala = unpack('vreserved/vtype/vcount', substr($isi, 0, 6));

    return ($kepala['reserved'] === 0 && $kepala['type'] === 1 && $kepala['count'] >= 1)
        ? true
        : 'struktur ICO tidak valid (reserved/type/count salah)';
});

periksa('Backup terjadwal punya rotasi dan salinan offsite', function () use ($root) {
    $kode = baca($root . '/app/Console/Commands/BackupData.php');

    // Dicocokkan sebagai PEMBACAAN KONFIGURASI & PEMANGGILAN nyata, bukan teks
    // bebas: nama "retensi_hari" juga muncul di komentar dan pesan konsol,
    // sehingga pencocokan teks polos pernah membuat pemeriksaan ini lolos palsu.
    $wajib = [
        "config('backup.retensi_hari'" => 'pembacaan konfigurasi retensi',
        "config('backup.salinan_disk'" => 'pembacaan konfigurasi disk salinan',
        '->delete(' => 'pemanggilan penghapusan berkas',
        'POLA_BERKAS' => 'pembatasan pola berkas yang dihapus',
    ];

    $kurang = [];
    foreach ($wajib as $jarum => $label) {
        if (! str_contains($kode, $jarum)) {
            $kurang[] = $label;
        }
    }

    if ($kurang !== []) {
        return 'BackupData tanpa: ' . implode(', ', $kurang);
    }

    return is_file($root . '/config/backup.php')
        ? true
        : 'config/backup.php tidak ada';
});

periksa('Job & daftar cadangan memakai disk yang sama dengan konfigurasi', function () use ($root) {
    $salah = [];

    foreach ([
        '/app/Jobs/ExportBackupJob.php',
        '/app/Http/Controllers/Admin/BackupController.php',
        '/app/Console/Commands/BackupData.php',
    ] as $relatif) {
        $kode = baca($root . $relatif);
        if (! str_contains($kode, "config('backup.disk'")) {
            $salah[] = ltrim($relatif, '/');
        }
    }

    return $salah === []
        ? true
        : 'masih memakai disk hardcoded: ' . implode(', ', $salah);
});

periksa('Variabel cadangan & keamanan terdokumentasi di template env', function () use ($root) {
    $template = baca($root . '/.env.production.example');

    $kurang = [];
    foreach (['BACKUP_RETENTION_DAYS', 'BACKUP_COPY_DISK', 'SECURITY_HSTS_MAX_AGE'] as $var) {
        if (! str_contains($template, $var)) {
            $kurang[] = $var;
        }
    }

    return $kurang === [] ? true : 'belum ada di .env.production.example: ' . implode(', ', $kurang);
});

/* ──────────────────────────────────────────────────────────────
 * 15. Kebersihan aset & dokumen (P1, P3, P4)
 * ────────────────────────────────────────────────────────────── */
echo "\n15. Kebersihan aset & dokumen\n";

periksa('Tidak ada aset frontend mati', function () use ($root) {
    // Berkas di resources/ yang tidak dirujuk view mana pun dan tidak masuk
    // input Vite adalah kode mati — pernah ada 1.588 baris semacam ini.
    $input = [];
    if (preg_match_all("/['\"]([^'\"]+\\.(?:js|css))['\"]/", baca($root . '/vite.config.js'), $m)) {
        $input = $m[1];
    }

    $dipakai = [];
    foreach (array_merge(
        glob($root . '/resources/views/**/*.blade.php') ?: [],
        glob($root . '/resources/views/*.blade.php') ?: [],
        glob($root . '/resources/views/**/**/*.blade.php') ?: []
    ) as $view) {
        $isi = baca($view);
        if (preg_match_all('/resources\/(?:js|css)\/[\w.-]+\.(?:js|css)/', $isi, $mv)) {
            $dipakai = array_merge($dipakai, $mv[0]);
        }
    }
    $dipakai = array_unique($dipakai);

    // app.css meng-@import layout.css, dan app.js mengimpor presensi-*.js,
    // jadi berkas yang di-@import/diimpor sesama aset juga terhitung terpakai.
    $diimport = [];
    foreach (glob($root . '/resources/css/*.css') ?: [] as $css) {
        if (preg_match_all('/@import\s+["\']\.\/([\w.-]+\.css)["\']/', baca($css), $mi)) {
            foreach ($mi[1] as $nama) {
                $diimport[] = 'resources/css/' . $nama;
            }
        }
    }

    $dirujukJs = [];
    foreach (glob($root . '/resources/js/*.js') ?: [] as $js) {
        if (preg_match_all('/[\'"]\.\/([\w.-]+\.js)[\'"]/', baca($js), $mj)) {
            foreach ($mj[1] as $nama) {
                $dirujukJs[] = 'resources/js/' . $nama;
            }
        }
    }

    $terpakai = array_unique(array_merge($input, $dipakai, $diimport, $dirujukJs));

    $mati = [];
    foreach (array_merge(glob($root . '/resources/js/*.js') ?: [], glob($root . '/resources/css/*.css') ?: []) as $berkas) {
        $relatif = 'resources/' . basename(dirname($berkas)) . '/' . basename($berkas);
        if (! in_array($relatif, $terpakai, true)) {
            $mati[] = $relatif;
        }
    }

    return $mati === [] ? true : 'aset tidak dirujuk: ' . implode(', ', $mati);
});

periksa('html5-qrcode dimuat secara malas, bukan di bundle utama', function () use ($root) {
    $kode = baca($root . '/resources/js/presensi-qr.js');

    // Impor statis di tingkat atas akan menyeret ~369 kB ke app.js yang
    // diunduh di setiap halaman, padahal QR hanya dipakai di satu halaman.
    if (preg_match('/^\s*import\s.*html5-qrcode/m', $kode)) {
        return 'masih ada `import ... from \'html5-qrcode\'` statis di presensi-qr.js';
    }

    return str_contains($kode, "import('html5-qrcode')")
        ? true
        : "tidak ditemukan import('html5-qrcode') dinamis";
});

periksa('Root repositori hanya berisi dokumen aktif', function () use ($root) {
    $izinkan = ['README.md', 'DEPLOYMENT.md', 'AGENTS.md', 'CLAUDE.md', 'LAPORAN_KESIAPAN_LAUNCH_2026-10-25.md'];

    $lebih = [];
    foreach (array_merge(glob($root . '/*.md') ?: [], glob($root . '/*.txt') ?: []) as $berkas) {
        $nama = basename($berkas);
        if (! in_array($nama, $izinkan, true)) {
            $lebih[] = $nama;
        }
    }

    if ($lebih !== []) {
        return 'dokumen yang sebaiknya dipindah ke docs/: ' . implode(', ', $lebih);
    }

    return is_file($root . '/docs/arsip/README.md')
        ? true
        : 'docs/arsip/README.md (indeks arsip) tidak ada';
});

/**
 * Kumpulkan berkas dokumen (.md/.txt) untuk pemeriksaan integritas tautan.
 * `public/` dilewati karena berisi artefak build hasil Vite.
 *
 * @return list<string>
 */
function berkasDokumen(string $root): array
{
    $lewati = ['.git', 'node_modules', 'vendor', 'public', '.arena'];
    $hasil = [];
    $tumpuk = [$root];

    while ($tumpuk !== []) {
        $dir = array_pop($tumpuk);
        foreach (scandir($dir) ?: [] as $nama) {
            if ($nama === '.' || $nama === '..') {
                continue;
            }

            $penuh = $dir . '/' . $nama;

            if (is_dir($penuh)) {
                if (! in_array($nama, $lewati, true)) {
                    $tumpuk[] = $penuh;
                }

                continue;
            }

            if (preg_match('/\.(md|txt)$/i', $nama)) {
                $hasil[] = $penuh;
            }
        }
    }

    sort($hasil);

    return $hasil;
}

periksa('Tautan Markdown relatif menunjuk berkas yang ada', function () use ($root) {
    $rusak = [];
    $diperiksa = 0;

    foreach (berkasDokumen($root) as $berkas) {
        $isi = baca($berkas);
        $dir = dirname($berkas);

        if (! preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $isi, $m)) {
            continue;
        }

        foreach ($m[1] as $tautan) {
            // Tautan eksternal, jangkar, dan placeholder bukan urusan check ini.
            if (preg_match('#^(https?://|mailto:|\#|<)#i', $tautan)) {
                continue;
            }

            $diperiksa++;
            $target = preg_split('/[#?]/', $tautan)[0];

            if ($target === '') {
                continue;
            }

            if (! file_exists($dir . '/' . $target)) {
                $rusak[] = rel($berkas, $root) . ' -> ' . $tautan;
            }
        }
    }

    if ($diperiksa === 0) {
        return 'tidak ada tautan relatif yang diperiksa — check ini tidak membuktikan apa pun';
    }

    return $rusak === [] ? true : implode(' | ', array_slice($rusak, 0, 5));
});

periksa('URL GitHub absolut menunjuk berkas yang ada', function () use ($root) {
    $rusak = [];
    $diperiksa = 0;

    // Kasus nyata yang pernah lolos: dokumen dipindah ke docs/arsip/, tetapi
    // URL absolut `.../blob/main/<berkas>` tetap menunjuk lokasi lama.
    $pola = '#github\.com/amhyer/simantapmonsa/(?:blob|tree)/([^/\s)]+)/([^\s)"\']+)#i';

    foreach (berkasDokumen($root) as $berkas) {
        $isi = baca($berkas);

        if (! preg_match_all($pola, $isi, $m, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($m as $cocok) {
            $diperiksa++;
            $jalur = $cocok[2];

            if (! file_exists($root . '/' . $jalur)) {
                $rusak[] = rel($berkas, $root) . ' -> ' . $jalur;
            }
        }
    }

    if ($diperiksa === 0) {
        return 'tidak ada URL GitHub yang diperiksa — check ini tidak membuktikan apa pun';
    }

    return $rusak === [] ? true : implode(' | ', array_slice($rusak, 0, 5));
});

/* ──────────────────────────────────────────────────────────────
 * 16. Tidak ada aset pihak ketiga dari CDN (S4)
 * ────────────────────────────────────────────────────────────── */
echo "\n16. Aset pihak ketiga di-self-host, bukan CDN\n";

/**
 * Kumpulkan seluruh berkas Blade, tanpa resources/views/vendor/ (milik paket).
 *
 * @return list<string>
 */
function berkasBlade(string $root): array
{
    $hasil = [];
    $tumpuk = [$root . '/resources/views'];

    while ($tumpuk !== []) {
        $dir = array_pop($tumpuk);

        foreach (scandir($dir) ?: [] as $nama) {
            if ($nama === '.' || $nama === '..') {
                continue;
            }

            $penuh = $dir . '/' . $nama;

            if (is_dir($penuh)) {
                // View terbitan paket (scramble, telescope) akan tertimpa saat
                // paket diperbarui, jadi di luar cakupan perbaikan ini.
                if ($nama !== 'vendor') {
                    $tumpuk[] = $penuh;
                }

                continue;
            }

            if (str_ends_with($nama, '.blade.php')) {
                $hasil[] = $penuh;
            }
        }
    }

    sort($hasil);

    return $hasil;
}

periksa('Tidak ada aset pihak ketiga yang dimuat dari CDN', function () use ($root) {
    $temuan = [];

    foreach (berkasBlade($root) as $berkas) {
        $isi = baca($berkas);

        // Hanya tag yang MEMUAT aset: <script src>, <link href>, <img src>.
        // Tautan navigasi <a href="https://..."> jelas boleh.
        $pola = '#<(script|link|img)\b[^>]*\b(?:src|href)\s*=\s*["\']https?://#i';

        if (preg_match_all($pola, $isi, $m)) {
            foreach ($m[0] as $tag) {
                $temuan[] = rel($berkas, $root) . ': ' . substr(trim($tag), 0, 70);
            }
        }
    }

    return $temuan === [] ? true : implode(' | ', array_slice($temuan, 0, 4));
});

periksa('Pustaka self-host terkunci di package.json', function () use ($root) {
    $pkg = json_decode(baca($root . '/package.json'), true);
    $deps = array_merge($pkg['dependencies'] ?? [], $pkg['devDependencies'] ?? []);

    // Semua ini dulu dimuat dari CDN; kini dibundel Vite. Kalau salah satu
    // hilang dari package.json, build akan gagal diam-diam atau jatuh ke CDN lagi.
    $wajib = [
        'chart.js',                        // grafik di 15 halaman
        'aos',                             // animasi scroll halaman landing
        'bootstrap',                       // tata letak /tka-statistik
        '@fontsource/inter',               // font halaman landing
        '@fontsource/figtree',             // font utama aplikasi (Tailwind sans)
        '@fortawesome/fontawesome-free',   // ikon
        'html5-qrcode',                    // pemindai presensi (dimuat malas)
    ];

    $kurang = array_values(array_filter($wajib, fn ($p) => ! isset($deps[$p])));

    if ($kurang !== []) {
        return 'tidak terdaftar di package.json: ' . implode(', ', $kurang);
    }

    // Versi harus spesifik, bukan "*" atau "" yang bisa berubah sendiri.
    $longgar = [];
    foreach ($wajib as $p) {
        if (in_array($deps[$p], ['*', '', 'latest'], true)) {
            $longgar[] = "{$p}={$deps[$p]}";
        }
    }

    return $longgar === [] ? true : 'versi tidak terkunci: ' . implode(', ', $longgar);
});

periksa('Semua input Vite ada di disk', function () use ($root) {
    $config = baca($root . '/vite.config.js');

    if (! preg_match_all("/['\"](resources\/[^'\"]+\.(?:js|css))['\"]/", $config, $m)) {
        return 'tidak ada input yang terbaca dari vite.config.js';
    }

    $hilang = array_values(array_filter(
        array_unique($m[1]),
        fn ($relatif) => ! is_file($root . '/' . $relatif)
    ));

    return $hilang === [] ? true : 'input tidak ditemukan: ' . implode(', ', $hilang);
});

/* ────────────────────────────────────────────────────────────── */
echo "\n=== Ringkasan: {$lulus} lulus, {$gagal} gagal ===\n";
if ($gagal > 0) {
    echo "\nRincian kegagalan:\n";
    foreach ($catatan as $c) {
        echo "  - {$c}\n";
    }
    exit(1);
}

echo "Semua pemeriksaan lulus.\n";
exit(0);
