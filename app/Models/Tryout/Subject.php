<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Subject extends Model
{
    protected $table = 'tryout_subjects';
    protected $fillable = [
        'uuid', 'package_id', 'mata_pelajaran_id', 'urutan',
        'bobot', 'jumlah_soal', 'durasi_menit', 'passing_grade_mapel'
    ];

    protected $casts = [
        'bobot' => 'float',
        'passing_grade_mapel' => 'float',
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

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MataPelajaran::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TryoutQuestion::class, 'tryout_subject_id');
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'tryout_subject_id');
    }
}
