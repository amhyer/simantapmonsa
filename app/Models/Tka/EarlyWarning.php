<?php

namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EarlyWarning extends Model
{
    protected $table = 'tka_early_warnings';
    protected $fillable = [
        'uuid', 'siswa_id', 'semester_id', 'dimension_id',
        'tingkat_peringatan', 'alasan_peringatan', 'rekomendasi_tindakan',
        'status', 'ditangani_oleh', 'tanggal_selesai'
    ];

    protected $casts = [
        'tanggal_selesai' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Semester::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'ditangani_oleh');
    }
}
