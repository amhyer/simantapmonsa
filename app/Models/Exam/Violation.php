<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Violation extends Model
{
    protected $table = 'exam_violations';

    protected $fillable = ['attempt_id', 'tipe', 'sumber_tabel', 'sumber_id'];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class, 'attempt_id');
    }
}
