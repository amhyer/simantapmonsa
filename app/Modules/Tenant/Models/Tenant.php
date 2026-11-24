<?php

namespace App\Modules\Tenant\Models;

use App\Models\User;
use App\Modules\Tenant\Support\TenantExistenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
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

    public function approvalStatus(): string
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        if (! empty($meta['approval_status'])) {
            return (string) $meta['approval_status'];
        }

        return $this->aktif ? 'approved' : 'inactive';
    }

    public function isPendingApproval(): bool
    {
        return $this->approvalStatus() === 'pending';
    }

    public function isRejectedApproval(): bool
    {
        return $this->approvalStatus() === 'rejected';
    }

    public function isEmailVerified(): bool
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        return ! empty($meta['email_verified_at']);
    }

    public function isNpsnVerified(): bool
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        return ! empty($meta['npsn_verified']) || preg_match('/^(?!(\d)\1{7}$)[1-9]\d{7}$/', (string) $this->npsn) === 1;
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (Tenant $tenant): void {
            static::forgetExistsCache();
            Cache::forget("tenant:subdomain:{$tenant->subdomain}");
            if (! empty($tenant->domain_kustom)) {
                Cache::forget("tenant:domain:{$tenant->domain_kustom}");
            }
            Cache::forget("tenant:id:{$tenant->id}");
        });

        static::deleted(function (): void {
            static::forgetExistsCache();
        });
    }

    public static function hasAnyTenant(): bool
    {
        return app(TenantExistenceCache::class)->hasAny();
    }

    public static function forgetExistsCache(): void
    {
        app(TenantExistenceCache::class)->forget();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
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
