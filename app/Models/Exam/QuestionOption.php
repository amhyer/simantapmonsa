<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $table = 'exam_question_options';

    protected $fillable = ['question_id', 'huruf', 'isi_opsi', 'adalah_benar', 'urutan'];

    protected $casts = [
        'adalah_benar' => 'boolean',
        'urutan' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
