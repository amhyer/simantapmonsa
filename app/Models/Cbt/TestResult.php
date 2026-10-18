<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestResult extends Model
{
    protected $table = 'cbt_test_results';
    protected $fillable = [
        'uuid',
        'test_session_id',
        'siswa_id',
        'attempt_number',
        'nilai_akhir',
        'nilai_maksimal',
        'persentase',
        'lulus',
        'jumlah_benar',
        'jumlah_salah',
        'jumlah_tidak_dijawab',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_detik',
        'submitted_by',
        'ip_address',
        'user_agent',
        'integrity_score',
        'integrity_violations'
    ];

    protected $casts = [
        'lulus' => 'boolean',
        'nilai_akhir' => 'float',
        'nilai_maksimal' => 'float',
        'persentase' => 'float',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'integrity_score' => 'float',
        'integrity_violations' => 'array',
    ];

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'test_session_id', 'test_session_id')
                    ->where('siswa_id', $this->siswa_id);
    }

    public function integrityLogs(): HasMany
    {
        return $this->hasMany(IntegrityLog::class, 'test_result_id');
    }
}
