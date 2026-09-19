<?php

// Quick Laravel app bootstrap
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ApiKey;
use App\Models\User;

// Ensure admin user exists
$user = User::firstOrCreate(
    ['email' => 'admin@simantap.local'],
    [
        'nama_lengkap' => 'Admin SIMANTAP',
        'nama_pengguna' => 'admin',
        'kata_sandi' => bcrypt('admin123'),
        'peran' => 'admin',
        'aktif' => true,
    ]
);

echo "[OK] User: {$user->email}\n";

// Delete old keys for clean slate
ApiKey::whereIn('key', [
    '11jtIdsSzunH3UDSAoNywFnLAkM9pGhsaK4RQm0Jafws4HCxC5yqH31lg8ZfSthQ',
])->delete();

// Create API key
$apiKey = ApiKey::create([
    'user_id' => $user->id,
    'name' => 'Dapodik Bridge',
    'key' => '11jtIdsSzunH3UDSAoNywFnLAkM9pGhsaK4RQm0Jafws4HCxC5yqH31lg8ZfSthQ',
    'abilities' => json_encode(['dapodik:import']),
    'active' => true,
]);

echo "[OK] API Key created\n";
echo "Key: {$apiKey->key}\n";
echo "Name: {$apiKey->name}\n";
echo "Abilities: " . json_encode($apiKey->abilities) . "\n";

// Verify it exists
$verify = ApiKey::where('key', $apiKey->key)->first();
if ($verify) {
    echo "[OK] Verification: Key found in database\n";
} else {
    echo "[ERROR] Verification: Key NOT found in database\n";
}
