<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneMenuUsage extends Command
{
    protected $signature = 'menu:usage-prune';

    protected $description = 'Hapus agregat penggunaan menu yang lebih lama dari 60 hari';

    public function handle(): int
    {
        $cutoff = now()->subDays(59)->toDateString();
        $deleted = DB::table('menu_usage_daily')->whereDate('visit_date', '<', $cutoff)->delete();

        $this->info("Menghapus {$deleted} baris agregat sebelum {$cutoff}.");

        return self::SUCCESS;
    }
}
