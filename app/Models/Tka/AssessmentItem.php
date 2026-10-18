<?php

namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssessmentItem extends Model
{
    protected $table = 'tka_assessment_items';
    protected $fillable = [
        'uuid', 'dimension_id', 'urutan', 'indikator',
        'pertanyaan_penilaian', 'tipe_jawaban', 'bobot_item'
    ];

    protected $casts = [
        'bobot_item' => 'float',
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

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class, 'assessment_item_id');
    }
}
