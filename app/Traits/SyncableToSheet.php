<?php

namespace App\Traits;

use App\Models\SekolahSettings;
use App\Services\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

trait SyncableToSheet
{
    protected function syncToGoogleSheet(string $type, $model): bool
    {
        try {
            $settings = SekolahSettings::first();
            $googleSheet = $settings->pengaturan['google_sheet'] ?? null;

            if (!$googleSheet || empty($googleSheet['terhubung']) || empty($googleSheet['spreadsheet_id'])) {
                return false;
            }

            $spreadsheetId = $googleSheet['spreadsheet_id'];
            $tabName = config("google.sheet_tabs.{$type}");

            if (!$tabName) {
                Log::warning("Unknown sync type: {$type}");
                return false;
            }

            $service = app(GoogleSheetsService::class);
            $service->getOrCreateTab($spreadsheetId, $tabName);
            $service->ensureHeaders($spreadsheetId, $tabName, config("google.headers.{$type}"));
            $rowData = $service->buildRow($type, $model);

            return $service->syncRow($spreadsheetId, $tabName, $rowData);
        } catch (\Exception $e) {
            Log::error("Google Sheets sync failed for {$type}: " . $e->getMessage());
            return false;
        }
    }
}
