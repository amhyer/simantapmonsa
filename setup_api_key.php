<?php

/**
 * Skrip bantu (dev/ops) untuk menyiapkan akun admin + API key bridge Dapodik.
 *
 * PERBAIKAN:
 *  - sebelumnya memakai kolom `email` pada User::firstOrCreate(), padahal tabel
 *    `users` tidak punya kolom itu → skrip selalu gagal dengan SQL error.
 *  - API key tidak lagi ditulis sebagai literal di dalam repo (kebocoran
 *    kredensial). Nilainya diambil dari environment/argumen.
 *
 * Pemakaian:
 *   SIMANTAP_API_KEY="$(php -r 'echo bin2hex(random_bytes(32));')" php setup_api_key.php
 * atau
 *   php setup_api_key.php "<API_KEY_RAHASIA>"
 */

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ApiKey;
use App\Models\User;

$apiKey = getenv('SIMANTAP_API_KEY') ?: ($argv[1] ?? null);

if (!$apiKey || strlen($apiKey) < 32) {
    fwrite(STDERR, "API key wajib diisi (minimal 32 karakter).\n");
    fwrite(STDERR, "Contoh: SIMANTAP_API_KEY=\"\$(php -r 'echo bin2hex(random_bytes(32));')\" php setup_api_key.php\n");
    exit(1);
}

// Akun admin: pakai nama_pengguna (kolom yang benar), bukan email.
$user = User::firstOrCreate(
    ['nama_pengguna' => 'admin'],
    [
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'nama_lengkap' => 'Admin SIMANTAP',
        'kata_sandi' => bcrypt(bin2hex(random_bytes(8))),
        'peran' => 'admin',
        'aktif' => true,
        'force_password_change' => true,
        'terhubung_dengan' => [],
    ]
);

echo "[OK] User: {$user->nama_pengguna} (id {$user->id})\n";

// Bersihkan key lama dengan nama yang sama agar tidak menumpuk.
ApiKey::where('name', 'Dapodik Bridge')->delete();

$apiKeyModel = ApiKey::create([
    'user_id' => $user->id,
    'name' => 'Dapodik Bridge',
    'key' => $apiKey,
    'key_hash' => hash('sha256', $apiKey),
    'abilities' => ['dapodik:import'],
    'active' => true,
]);

echo "[OK] API Key dibuat (id {$apiKeyModel->id})\n";
echo "     Simpan nilainya di bridge (env SIMANTAP_API_KEY), JANGAN di-commit.\n";

$verify = ApiKey::where('key_hash', hash('sha256', $apiKey))->first();
echo $verify ? "[OK] Verifikasi: key ditemukan di database\n" : "[ERROR] Verifikasi: key TIDAK ditemukan\n";
