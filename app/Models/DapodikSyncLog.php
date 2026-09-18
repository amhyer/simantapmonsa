<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DapodikSyncLog extends Model
{
    protected $table = 'dapodik_sync_logs';
    protected $hidden = ['errors', 'summary', 'user_agent'];

    protected $fillable = [
        'uuid', 'user_id', 'sekolah_npsn', 'nama_sekolah', 'tipe',
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
        static::creating(function ($model) {
            if (!$model->uuid) $model->uuid = Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
