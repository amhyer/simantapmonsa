<?php

namespace App\Console\Commands;

use App\Services\TkaStatistikService;
use Illuminate\Console\Command;

class RefreshTkaStatistik extends Command
{
    protected $signature = 'tka:refresh';
    protected $description = 'Refresh TKA statistics cache from tka.kemendikdasmen.go.id';

    public function handle(TkaStatistikService $tkaService): int
    {
        $this->info('Refreshing TKA statistics...');

        $data = $tkaService->refresh();

        if (isset($data['error'])) {
            $this->error('Failed to fetch TKA data: ' . ($data['message'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        $this->info('TKA statistics refreshed successfully.');
        $this->info('Last update: ' . ($data['last_update'] ?? 'unknown'));

        return Command::SUCCESS;
    }
}
