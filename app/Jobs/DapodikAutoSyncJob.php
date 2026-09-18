<?php

namespace App\Jobs;

use App\Models\DapodikConfig;
use App\Services\Dapodik\DapodikSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DapodikAutoSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $config = DapodikConfig::getInstance();

        if (!$config->auto_sync_enabled) {
            return;
        }

        $service = new DapodikSyncService();

        try {
            $result = $service->commit();

            $config->update([
                'auto_sync_last_run_at' => now(),
                'auto_sync_run_status' => 'success',
                'auto_sync_run_error' => null,
                'last_sync_at' => now(),
                'last_sync_by' => 'auto-sync',
            ]);
        } catch (\Exception $e) {
            $config->update([
                'auto_sync_last_run_at' => now(),
                'auto_sync_run_status' => 'failed',
                'auto_sync_run_error' => $e->getMessage(),
            ]);
        }
    }
}
