<?php

use Illuminate\Support\Facades\Cache;
use App\Models\PengaturanGuru;

if (!function_exists('setting')) {
    function setting(string|array|null $key = null, mixed $default = null): mixed
    {
        $path = storage_path('app/settings.json');

        $settings = Cache::remember('app_settings', 300, function () use ($path) {
            return file_exists($path) ? json_decode(file_get_contents($path), true) ?? [] : [];
        });

        if (is_array($key)) {
            $settings = array_merge($settings, $key);
            if (auth()->check() && in_array(auth()->user()->peran, ['admin', 'guru'])) {
                file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                Cache::forget('app_settings');
            }
            return true;
        }

        if ($key === null) {
            return $settings;
        }

        return data_get($settings, $key, $default);
    }
}

if (!function_exists('getKKM')) {
    function getKKM(?int $guruId = null): int
    {
        if ($guruId) {
            $pengaturan = PengaturanGuru::where('guru_id', $guruId)->first();
            if ($pengaturan && $pengaturan->kkm) {
                return (int) $pengaturan->kkm;
            }
        }

        $latest = PengaturanGuru::orderByDesc('id')->first();
        return $latest && $latest->kkm ? (int) $latest->kkm : 70;
    }
}
