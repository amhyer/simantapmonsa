<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $table = 'exam_questions';

    protected $fillable = [
        'bank_id', 'section_id', 'urutan', 'tipe', 'pertanyaan', 'media_type', 'media_url',
        'poin', 'tingkat_kesulitan', 'pembahasan', 'kunci_jawaban', 'aktif',
        'sumber_tabel', 'sumber_id', 'meta',
    ];

    protected $casts = [
        'poin' => 'float',
        'urutan' => 'integer',
        'aktif' => 'boolean',
        'meta' => 'array',
    ];

    // Kunci tidak pernah ikut serialisasi ke klien.
    protected $hidden = ['kunci_jawaban'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id')->orderBy('urutan');
    }
}
