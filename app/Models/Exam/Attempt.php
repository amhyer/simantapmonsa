<?php

namespace App\Models\Exam;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attempt extends Model
{
    public const STATUS_BERJALAN = 'berjalan';

    public const STATUS_DIKUMPULKAN = 'dikumpulkan';

    public const STATUS_DINILAI = 'dinilai';

    protected $table = 'exam_attempts';

    protected $fillable = [
        'session_id', 'siswa_id', 'user_id', 'attempt_number', 'mulai_at', 'deadline_at',
        'selesai_at', 'status', 'nilai_total', 'nilai_maksimal', 'sumber_tabel', 'sumber_id', 'meta',
    ];

    protected $casts = [
        'mulai_at' => 'datetime',
        'deadline_at' => 'datetime',
        'selesai_at' => 'datetime',
        'attempt_number' => 'integer',
        'nilai_total' => 'float',
        'nilai_maksimal' => 'float',
        'meta' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class, 'attempt_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class, 'attempt_id');
    }
}
