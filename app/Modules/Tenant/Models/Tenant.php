<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $table = 'tenants';

    protected $fillable = [
        'uuid',
        'npsn',
        'nama_sekolah',
        'subdomain',
        'aktif',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
