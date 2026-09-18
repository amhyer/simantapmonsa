<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DapodikConfig extends Model
{
    protected $table = 'dapodik_configs';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'npsn',
        'token',
        'host',
        'port',
        'protocol',
        'allow_insecure_in_production',
        'archive_unlisted',
        'auto_sync_enabled',
        'auto_sync_interval_hours',
        'auto_sync_last_run_at',
        'auto_sync_run_status',
        'auto_sync_run_error',
        'last_sync_at',
        'last_sync_by',
        'cf_access',
    ];

    protected $hidden = [
        'token',
    ];

    protected $appends = ['masked_token'];

    protected $casts = [
        'token' => 'encrypted',
        'cf_access' => 'array',
        'auto_sync_enabled' => 'boolean',
        'allow_insecure_in_production' => 'boolean',
        'archive_unlisted' => 'boolean',
        'auto_sync_last_run_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public static function getInstance(): static
    {
        return static::firstOrCreate(
            ['id' => 'singleton'],
            [
                'npsn' => null,
                'host' => 'localhost',
                'port' => 5774,
                'protocol' => 'http',
            ]
        );
    }

    protected function maskedToken(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            if (empty($value)) {
                return null;
            }

            if (mb_strlen($value) <= 8) {
                return '****';
            }

            return mb_substr($value, 0, 4) . '****' . mb_substr($value, -4);
        });
    }
}
