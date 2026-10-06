<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use SoftDeletes;

    protected $table = 'cbt_questions';
    protected $fillable = [
        'uuid',
        'question_bank_id',
        'urutan',
        'tipe_soal',
        'pertanyaan',
        'media_type',
        'media_url',
        'poin',
        'waktu_jawab_detik',
        'pembahasan',
        'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'poin' => 'float',
    ];

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id');
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'question_id');
    }
}
