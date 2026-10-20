<?php

namespace App\Models\Exam;

use App\Models\Rombel;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Session extends Model
{
    use SoftDeletes;

    protected $table = 'exam_sessions';

    protected $fillable = [
        'bank_id', 'guru_id', 'semester_id', 'rombel_id', 'kode', 'nama', 'kelas',
        'mulai_at', 'selesai_at', 'durasi_menit', 'status', 'soal_snapshot',
        'peserta_ids', 'instruksi', 'sumber_tabel', 'sumber_id', 'meta',
    ];

    protected $casts = [
        'mulai_at' => 'datetime',
        'selesai_at' => 'datetime',
        'durasi_menit' => 'integer',
        'soal_snapshot' => 'array',
        'peserta_ids' => 'array',
        'meta' => 'array',
    ];

    protected $hidden = ['soal_snapshot', 'peserta_ids'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class, 'session_id');
    }
}
