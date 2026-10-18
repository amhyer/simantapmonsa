<?php

namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AssessmentResponse extends Model
{
    protected $table = 'tka_assessment_responses';
    protected $fillable = [
        'uuid', 'assessment_id', 'assessment_item_id',
        'skor', 'catatan_guru'
    ];

    protected $casts = [
        'skor' => 'float',
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

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class);
    }

    public function assessmentItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentItem::class);
    }
}
