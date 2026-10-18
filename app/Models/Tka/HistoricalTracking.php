<?php

namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HistoricalTracking extends Model
{
    protected $table = 'tka_historical_tracking';
    protected $fillable = [
        'uuid', 'siswa_id', 'dimension_id', 'semester_id',
        'skor', 'perubahan_skor', 'trend'
    ];

    protected $casts = [
        'skor' => 'float',
        'perubahan_skor' => 'float',
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

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Semester::class);
    }
}
