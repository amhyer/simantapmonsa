<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'nama_lengkap' => fake()->name(),
            'nama_pengguna' => fake()->unique()->userName(),
            'kata_sandi' => static::$password ??= Hash::make('password'),
            'peran' => 'guru',
            'terhubung_dengan' => [],
            'aktif' => true,
            'force_password_change' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => 'admin',
            'nama_pengguna' => 'admin_' . Str::random(5),
        ]);
    }

    public function guru(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => 'guru',
        ]);
    }

    public function siswa(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => 'siswa',
        ]);
    }

    public function ortu(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => 'ortu',
            'nama_pengguna' => 'ortu_' . Str::random(5),
        ]);
    }

    public function kepsek(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => 'kepsek',
            'nama_pengguna' => 'kepsek_' . Str::random(5),
        ]);
    }

    public function forcePasswordChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'force_password_change' => true,
        ]);
    }
}
