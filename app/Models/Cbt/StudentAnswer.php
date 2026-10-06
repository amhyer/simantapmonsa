<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $table = 'cbt_student_answers';
    protected $fillable = [
        'uuid',
        'test_session_id',
        'siswa_id',
        'question_id',
        'jawaban',
        'adalah_benar',
        'poin_diperoleh',
        'waktu_mulai_jawab',
        'waktu_selesai_jawab',
        'durasi_detik',
        'attempt_number',
        'ragu_ragu'
    ];

    protected $casts = [
        'adalah_benar' => 'boolean',
        'poin_diperoleh' => 'float',
        'waktu_mulai_jawab' => 'datetime',
        'waktu_selesai_jawab' => 'datetime',
        'ragu_ragu' => 'boolean',
    ];

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
