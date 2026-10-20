<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $table = 'exam_results';

    protected $fillable = [
        'attempt_id', 'nilai_total', 'nilai_maksimal', 'persentase', 'peringkat', 'lulus',
        'detail_per_section', 'analisis_kelemahan', 'rekomendasi', 'sumber_tabel', 'sumber_id',
    ];

    protected $casts = [
        'nilai_total' => 'float',
        'nilai_maksimal' => 'float',
        'persentase' => 'float',
        'peringkat' => 'integer',
        'lulus' => 'boolean',
        'detail_per_section' => 'array',
        'analisis_kelemahan' => 'array',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class, 'attempt_id');
    }
}
