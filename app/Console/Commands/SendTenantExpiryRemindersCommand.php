<?php

namespace App\Console\Commands;

use App\Services\SuperAdmin\TenantSubscriptionService;
use Illuminate\Console\Command;

class SendTenantExpiryRemindersCommand extends Command
{
    protected $signature = 'tenants:send-expiry-reminders {--days=14,7,3,1 : Daftar H-N hari sebelum tanggal kedaluwarsa}';

    protected $description = 'Kirim email pengingat jatuh tempo langganan secara idempoten ke operator sekolah aktif';

    public function handle(TenantSubscriptionService $subscriptions): int
    {
        $rawDays = (string) $this->option('days');
        $targetDays = array_values(array_filter(
            array_map('intval', preg_split('/[\s,]+/', trim($rawDays)) ?: []),
            fn (int $d): bool => $d >= 0
        ));

        if ($targetDays === []) {
            $targetDays = [14, 7, 3, 1];
        }

        $stats = $subscriptions->sendExpiryReminders($targetDays);
        $this->info(sprintf(
            'Pengingat langganan selesai: diperiksa=%d, terkirim=%d, dilewati=%d.',
            $stats['checked'],
            $stats['sent'],
            $stats['skipped']
        ));

        return self::SUCCESS;
    }
}
