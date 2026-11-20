<?php

use App\Services\CbtExamService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('simantap:backup')->dailyAt('02:00');
Schedule::command('tka:refresh')->everySixHours();
Schedule::command('menu:usage-prune')->dailyAt('03:30')->withoutOverlapping();

// Menilai jawaban tersimpan walau browser siswa ditutup/terputus.
Artisan::command('cbt:finalize-expired', function () {
    $count = app(CbtExamService::class)->finalizeExpired();
    $this->info("CBT: {$count} percobaan kedaluwarsa difinalisasi.");
})->purpose('Finalisasi CBT yang melewati deadline server');

Schedule::command('cbt:finalize-expired')
    ->everyMinute()->withoutOverlapping()
    ->when(fn () => config('simantap.cbt_enabled', false));
