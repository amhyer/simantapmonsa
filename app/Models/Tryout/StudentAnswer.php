<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentAnswer extends Model
{
    protected $table = 'tryout_student_answers';
    protected $fillable = [
        'uuid', 'tryout_session_id', 'tryout_subject_id',
        'siswa_id', 'question_id', 'jawaban', 'adalah_benar',
        'poin_diperoleh', 'waktu_mulai_jawab', 'waktu_selesai_jawab',
        'durasi_detik', 'attempt_number'
    ];

    protected $casts = [
        'adalah_benar' => 'boolean',
        'poin_diperoleh' => 'float',
        'waktu_mulai_jawab' => 'datetime',
        'waktu_selesai_jawab' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'tryout_session_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'tryout_subject_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TryoutQuestion::class);
    }
}
