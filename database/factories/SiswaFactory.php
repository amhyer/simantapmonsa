<?php

namespace Database\Factories;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'guru_id' => User::factory()->guru(),
            'nama_guru' => 'Guru Uji',
            'nis' => 'N'.fake()->unique()->numerify('######'),
            'nisn' => fake()->unique()->numerify('##########'),
            'nama_peserta_didik' => fake()->name(),
            'kelas' => fake()->randomElement(['1.A', '2.A', '3.A', '4.A', '5.A', '6.A']),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'aktif' => true,
        ];
    }

    public function forGuru(User $guru): static
    {
        return $this->state(fn (array $attributes) => [
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
        ]);
    }
}
