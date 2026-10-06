<?php

namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Dimension extends Model
{
    use SoftDeletes;

    protected $table = 'tka_dimensions_enhanced';
    protected $fillable = [
        'uuid', 'semester_id', 'kode_dimensi', 'nama_dimensi',
        'deskripsi', 'kategori', 'bobot', 'rubrik_penilaian',
        'threshold_siap', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'bobot' => 'float',
        'threshold_siap' => 'float',
        'rubrik_penilaian' => 'array',
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

    public function semester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Semester::class);
    }

    public function assessmentItems(): HasMany
    {
        return $this->hasMany(AssessmentItem::class, 'dimension_id');
    }

    public function historicalTrackings(): HasMany
    {
        return $this->hasMany(HistoricalTracking::class, 'dimension_id');
    }

    public function earlyWarnings(): HasMany
    {
        return $this->hasMany(EarlyWarning::class, 'dimension_id');
    }
}
