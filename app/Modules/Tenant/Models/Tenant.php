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
        'wilayah',
        'subdomain',
        'domain_kustom',
        'paket',
        'kuota_siswa',
        'kuota_storage_mb',
        'tgl_kedaluwarsa',
        'catatan_langganan',
        'aktif',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'meta' => 'array',
            'kuota_siswa' => 'integer',
            'kuota_storage_mb' => 'integer',
            'tgl_kedaluwarsa' => 'date',
        ];
    }

    public function isExpired(): bool
    {
        return ! empty($this->tgl_kedaluwarsa) && $this->tgl_kedaluwarsa->isPast();
    }

    public function sisaHariLangganan(): ?int
    {
        if (empty($this->tgl_kedaluwarsa)) {
            return null; // Unlimited / Tanpa Batas Waktu
        }

        return (int) now()->startOfDay()->diffInDays($this->tgl_kedaluwarsa->startOfDay(), false);
    }

    public function labelPaket(): string
    {
        return match (strtolower($this->paket ?? 'standar')) {
            'pro' => 'Pro (Custom Domain)',
            'enterprise' => 'Enterprise (Dedicated Server/DB)',
            default => 'Standar (Shared Cloud)',
        };
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (Tenant $tenant): void {
            \Illuminate\Support\Facades\Cache::forget("tenant:subdomain:{$tenant->subdomain}");
            if (! empty($tenant->domain_kustom)) {
                \Illuminate\Support\Facades\Cache::forget("tenant:domain:{$tenant->domain_kustom}");
            }
            \Illuminate\Support\Facades\Cache::forget("tenant:id:{$tenant->id}");
        });
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'tenant_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) === 'uuid' && ! Str::isUuid((string) $value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
