<?php

namespace App\Models;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DapodikImportLog extends Model
{
    protected $table = 'dapodik_import_logs';

    protected $fillable = [
        'tenant_id', 'tenant_owned', 'user_id', 'sekolah_id', 'nama_sekolah',
        'total_siswa', 'berhasil', 'gagal', 'diperbarui',
        'errors', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'tenant_owned' => 'boolean',
        'errors' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $query->where('dapodik_import_logs.tenant_id', $tenantId);
            } elseif (config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()) {
                // Tanpa identitas sekolah yang tepercaya, jangan tampilkan log mana pun.
                $query->whereRaw('1 = 0');
            } else {
                // Mode sekolah tunggal hanya membaca log lama/tanpa tenant.
                $query->whereNull('dapodik_import_logs.tenant_id')->where('dapodik_import_logs.tenant_owned', false);
            }
        });

        static::creating(function ($model) {
            if ($model->tenant_id === null && TenantContext::check()) {
                $model->tenant_id = TenantContext::id();
            }
            if ($model->tenant_id !== null) {
                $model->tenant_owned = true;
            }
        });
    }

    public function getTotalDataAttribute(): int
    {
        return (int) ($this->attributes['total_data'] ?? $this->total_siswa ?? 0);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->forSchool();
    }
}
