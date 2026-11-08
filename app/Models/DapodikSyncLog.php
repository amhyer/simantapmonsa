<?php

namespace App\Models;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DapodikSyncLog extends Model
{
    protected $table = 'dapodik_sync_logs';
    protected $hidden = ['errors', 'summary', 'user_agent'];

    protected $fillable = [
        'uuid', 'tenant_id', 'user_id', 'sekolah_npsn', 'nama_sekolah', 'tipe',
        'tahun_ajaran', 'semester', 'total_data', 'berhasil', 'gagal',
        'diperbarui', 'dilewati', 'errors', 'summary', 'ip_address',
        'user_agent', 'started_at', 'finished_at', 'duration_seconds',
    ];

    protected $casts = [
        'errors' => 'array',
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $query->where('dapodik_sync_logs.tenant_id', $tenantId);
            } elseif (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
                // Tanpa identitas sekolah yang tepercaya, jangan tampilkan log mana pun.
                $query->whereRaw('1 = 0');
            } else {
                // Mode sekolah tunggal hanya membaca log lama/tanpa tenant.
                $query->whereNull('dapodik_sync_logs.tenant_id')->where('dapodik_sync_logs.tenant_owned', false);
            }
        });
        static::creating(function ($model) {
            if (!$model->uuid) $model->uuid = Str::uuid();
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
