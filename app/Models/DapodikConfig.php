<?php

namespace App\Models;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Traits\HasSchoolOwnership;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class DapodikConfig extends Model
{
    use HasSchoolOwnership;

    protected $table = 'dapodik_configs';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'tenant_owned',
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
        'tenant_owned' => 'boolean',
        'auto_sync_last_run_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public static function getInstance(): static
    {
        $id = TenantContext::check() ? 'tenant-'.TenantContext::id() : 'singleton';

        return static::firstOrCreate(
            ['id' => $id],
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
        return Attribute::get(function (): ?string {
            try {
                $value = $this->token;
            } catch (\Throwable) {
                return null;
            }

            if (empty($value)) {
                return null;
            }

            if (mb_strlen($value) <= 8) {
                return '****';
            }

            return mb_substr($value, 0, 4).'****'.mb_substr($value, -4);
        });
    }
}
