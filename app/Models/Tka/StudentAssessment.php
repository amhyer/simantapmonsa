<?php

namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StudentAssessment extends Model
{
    protected $table = 'tka_student_assessments';
    protected $fillable = [
        'uuid', 'siswa_id', 'semester_id', 'guru_id',
        'tanggal_penilaian', 'total_score', 'max_score',
        'persentase', 'status_kesiapan', 'skor_per_dimensi'
    ];

    protected $casts = [
        'total_score' => 'float',
        'max_score' => 'float',
        'persentase' => 'float',
        'skor_per_dimensi' => 'array',
        'tanggal_penilaian' => 'date',
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

    public function guru(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'guru_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class, 'assessment_id');
    }

    public function parentReport(): HasMany
    {
        return $this->hasMany(ParentReport::class, 'assessment_id');
    }
}
