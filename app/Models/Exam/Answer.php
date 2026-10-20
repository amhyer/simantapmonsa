<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $table = 'exam_answers';

    protected $fillable = ['attempt_id', 'question_id', 'jawaban', 'adalah_benar', 'poin_diperoleh', 'dijawab_at'];

    protected $casts = [
        'adalah_benar' => 'boolean',
        'poin_diperoleh' => 'float',
        'dijawab_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
