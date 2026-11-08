<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MenuUsageReport extends Command
{
    protected $signature = 'menu:usage-report {--days=30 : Rentang laporan (maksimal 60 hari)}';

    protected $description = 'Tampilkan agregat kunjungan halaman per peran tanpa identitas pengguna';

    public function handle(): int
    {
        $days = max(1, min(60, (int) $this->option('days')));
        $from = now()->subDays($days - 1)->toDateString();

        $rows = DB::table('menu_usage_daily')
            ->select('scope_key', 'role', 'route_name')
            ->selectRaw('SUM(visits) as visits')
            ->whereDate('visit_date', '>=', $from)
            ->groupBy('scope_key', 'role', 'route_name')
            ->orderByDesc('visits')
            ->orderBy('scope_key')
            ->orderBy('role')
            ->get()
            ->map(fn ($row) => [
                $row->scope_key,
                $row->role,
                $row->route_name,
                (int) $row->visits,
            ])
            ->all();

        $this->info("Penggunaan halaman {$days} hari terakhir (mulai {$from}); tanpa ID pengguna/IP/session.");
        if ($rows === []) {
            $this->comment('Belum ada data. Kunjungan halaman HTML sukses akan mulai dihitung setelah migrasi dan middleware aktif.');

            return self::SUCCESS;
        }

        $this->table(['Cakupan', 'Peran', 'Named route', 'Kunjungan'], $rows);

        return self::SUCCESS;
    }
}
