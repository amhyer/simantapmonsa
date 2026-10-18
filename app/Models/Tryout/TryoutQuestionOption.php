<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TryoutQuestionOption extends Model
{
    protected $table = 'tryout_question_options';
    protected $fillable = [
        'question_id', 'huruf', 'isi_opsi', 'adalah_benar', 'urutan'
    ];

    protected $casts = [
        'adalah_benar' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(TryoutQuestion::class);
    }
}
