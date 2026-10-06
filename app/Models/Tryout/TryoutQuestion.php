<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TryoutQuestion extends Model
{
    use SoftDeletes;

    protected $table = 'tryout_questions';
    protected $fillable = [
        'uuid', 'tryout_subject_id', 'urutan', 'tipe_soal',
        'pertanyaan', 'media_type', 'media_url', 'poin',
        'tingkat_kesulitan', 'pembahasan', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'poin' => 'float',
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'tryout_subject_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TryoutQuestionOption::class, 'question_id');
    }
}
