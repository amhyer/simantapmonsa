<?php

namespace App\Console\Commands;

use App\Models\Rombel;
use App\Models\Siswa;
use Illuminate\Console\Command;

class DapodikReconcileRombel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dapodik:reconcile-rombel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghubungkan siswa yang belum memiliki rombel_id ke rombel yang sesuai';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memulai rekonsiliasi siswa ke rombel...');

        $siswaList = Siswa::withoutGlobalScopes()
            ->whereNull('rombel_id')
            ->get();

        $this->info("Ditemukan {$siswaList->count()} siswa tanpa rombel_id.");

        $matchedCount = 0;
        $unmatchedCount = 0;

        foreach ($siswaList as $siswa) {
            $rombelDapodikId = $siswa->rekaman['rombongan_belajar_id'] ?? null;
            $rombelName = $siswa->rekaman['nama_rombel'] ?? $siswa->rekaman['rombel'] ?? $siswa->rekaman['kelas'] ?? $siswa->kelas;

            $rombel = null;
            if ($rombelDapodikId) {
                $rombel = Rombel::withoutGlobalScopes()
                    ->where('dapodik_id', $rombelDapodikId)
                    ->where('semester_id', $siswa->semester_id)
                    ->first();
            }

            if (! $rombel && $rombelName) {
                $rombel = Rombel::withoutGlobalScopes()
                    ->where('nama_rombel', $rombelName)
                    ->where('semester_id', $siswa->semester_id)
                    ->first();

                if (! $rombel) {
                    $rombel = Rombel::withoutGlobalScopes()
                        ->where('nama_rombel', 'like', '%'.$rombelName.'%')
                        ->where('semester_id', $siswa->semester_id)
                        ->first();
                }
            }

            if ($rombel) {
                $siswa->update([
                    'rombel_id' => $rombel->id,
                    'guru_id' => $rombel->guru_id ?? $siswa->guru_id,
                    'kelas' => $rombel->nama_rombel,
                ]);
                $matchedCount++;
            } else {
                $unmatchedCount++;
            }
        }

        $this->info("Rekonsiliasi selesai: {$matchedCount} siswa berhasil dihubungkan ke rombel, {$unmatchedCount} belum cocok.");

        return Command::SUCCESS;
    }
}
