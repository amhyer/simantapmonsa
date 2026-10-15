<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ApiKeySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'uuid' => Str::uuid(),
                'nama_lengkap' => 'Admin',
                'nama_pengguna' => 'admin',
                'kata_sandi' => Hash::make('admin123'),
                'peran' => 'admin',
                'terhubung_dengan' => [],
                'aktif' => true,
            ]);
            $this->command->info("Created admin user: {$user->nama_pengguna}");
        }

        $existing = ApiKey::where('name', 'Dapodik Bridge')->first();
        if (!$existing) {
            // Kunci polos hanya hidup di memori lalu ditampilkan sekali;
            // basis data menyimpan hash-nya saja.
            $plaintextKey = Str::random(64);

            $apiKey = ApiKey::create([
                'user_id' => $user->id,
                'name' => 'Dapodik Bridge',
                'key' => null,
                'key_hash' => hash('sha256', $plaintextKey),
                'abilities' => ['dapodik:import'],
                'active' => true,
            ]);

            $this->command->warn("API Key (tampil sekali, simpan di bridge): {$plaintextKey}");
        } else {
            $this->command->info("API Key already exists");
        }

        $keys = ApiKey::all();
        $this->command->info("Total API Keys: " . count($keys));
    }
}
