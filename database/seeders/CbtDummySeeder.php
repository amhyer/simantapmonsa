<?php

namespace Database\Seeders;

use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\CbtExamService;
use Database\Seeders\Support\SeedGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CbtDummySeeder extends Seeder
{
    public function run(): void
    {
        if (! SeedGuard::bolehJalan($this->command)) {
            return;
        }

        $paksaGantiSandi = SeedGuard::paksaGantiSandi();
        $tenantId = Tenant::query()->value('id');

        $guru = User::where('nama_pengguna', 'guru_cbt_dummy')->firstOrCreate([
            'nama_pengguna' => 'guru_cbt_dummy',
            'peran' => 'guru',
        ], [
            'uuid' => Str::uuid(),
            'nama_lengkap' => 'Guru CBT Dummy',
            'kata_sandi' => Hash::make('guru123'),
            'terhubung_dengan' => [],
            'aktif' => true,
            'force_password_change' => $paksaGantiSandi,
            'tenant_id' => $tenantId,
            'tenant_owned' => $tenantId !== null,
        ]);

        $siswa = Siswa::where('nis', 'CBTDUMMY01')->firstOrCreate([
            'nis' => 'CBTDUMMY01',
        ], [
            'uuid' => Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nisn' => 'E2ECBTDUMY0001',
            'nama_peserta_didik' => 'Siswa CBT Dummy',
            'kelas' => '6A',
            'jenis_kelamin' => 'L',
            'aktif' => true,
            'tenant_id' => $tenantId,
        ]);

        User::where('nama_pengguna', 'CBTDUMMY01')->firstOrCreate([
            'nama_pengguna' => 'CBTDUMMY01',
            'peran' => 'siswa',
        ], [
            'uuid' => Str::uuid(),
            'nama_lengkap' => 'Siswa CBT Dummy',
            'kata_sandi' => Hash::make('password'),
            'terhubung_dengan' => [$siswa->id],
            'aktif' => true,
            'force_password_change' => $paksaGantiSandi,
            'tenant_id' => $tenantId,
            'tenant_owned' => $tenantId !== null,
        ]);

        $bank = CbtQuestionBank::where('guru_id', $guru->id)->where('title', 'Uji Coba CBT')->firstOrCreate([
            'guru_id' => $guru->id,
            'title' => 'Uji Coba CBT',
        ], [
            'subject' => 'Matematika',
        ]);

        foreach ($this->dummyQuestions() as $question) {
            $bank->questions()->firstOrCreate([
                'prompt' => $question['prompt'],
            ], [
                'options' => $question['options'],
                'correct_answer' => $question['correct_answer'],
            ]);
        }

        if (! CbtTestSession::where('bank_id', $bank->id)->exists()) {
            $session = CbtTestSession::create([
                'guru_id' => $guru->id,
                'bank_id' => $bank->id,
                'title' => 'Uji Coba CBT',
                'kelas' => '6A',
                'opens_at' => now()->subMinute(),
                'closes_at' => now()->addHours(2),
                'duration_minutes' => 60,
            ]);

            app(CbtExamService::class)->publish($session);
            app(CbtExamService::class)->open($session);
        }

        echo 'Seeder dummy CBT: bank soal, soal uji coba, dan sesi uji coba berhasil dipersiapkan.';
    }

    /**
     * @return array<int, array{prompt: string, options: array<string, string>, correct_answer: string}>
     */
    private function dummyQuestions(): array
    {
        return [
            [
                'prompt' => 'Berapakah hasil penjumlahan 8 + 6?',
                'options' => ['A' => '14', 'B' => '15', 'C' => '16', 'D' => '17'],
                'correct_answer' => 'A',
            ],
            [
                'prompt' => 'Berapakah hasil pengurangan 10 - 4?',
                'options' => ['A' => '6', 'B' => '7', 'C' => '8', 'D' => '9'],
                'correct_answer' => 'A',
            ],
            [
                'prompt' => 'Berapakah hasil perkalian 6 x 7?',
                'options' => ['A' => '42', 'B' => '40', 'C' => '44', 'D' => '45'],
                'correct_answer' => 'A',
            ],
            [
                'prompt' => 'Berapakah hasil penjumlahan 9 + 9?',
                'options' => ['A' => '17', 'B' => '18', 'C' => '19', 'D' => '20'],
                'correct_answer' => 'B',
            ],
            [
                'prompt' => 'Berapakah hasil perkalian 5 x 5?',
                'options' => ['A' => '23', 'B' => '25', 'C' => '27', 'D' => '30'],
                'correct_answer' => 'B',
            ],
        ];
    }
}
