<?php

namespace App\Models;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PresensiMesinSyncLog extends Model
{
    protected $table = 'presensi_mesin_sync_logs';
    protected $hidden = ['user_agent'];

    protected $fillable = [
        'uuid', 'tenant_id', 'user_id', 'source', 'status',
        'jumlah_scan', 'dibuat', 'diperbarui', 'tetap', 'dilindungi',
        'nik_tidak_dikenal', 'contoh_nik_tidak_dikenal', 'pesan_error',
        'ip_address', 'started_at', 'finished_at', 'duration_seconds',
    ];

    protected $casts = [
        'contoh_nik_tidak_dikenal' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $query->where('presensi_mesin_sync_logs.tenant_id', $tenantId);
            } elseif (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
                // Tanpa identitas sekolah yang tepercaya, jangan tampilkan log mana pun.
                $query->whereRaw('1 = 0');
            } else {
                // Mode sekolah tunggal hanya membaca log lama/tanpa tenant.
                $query->whereNull('presensi_mesin_sync_logs.tenant_id')->where('presensi_mesin_sync_logs.tenant_owned', false);
            }
        });
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
            if (TenantContext::check()) {
                $model->tenant_id = TenantContext::id();
            }
            if ($model->tenant_id !== null) {
                $model->tenant_owned = true;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->forSchool();
    }
}
