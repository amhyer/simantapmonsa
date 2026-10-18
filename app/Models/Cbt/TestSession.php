<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSession extends Model
{
    use SoftDeletes;

    protected $table = 'cbt_test_sessions';
    protected $fillable = [
        'uuid',
        'kode_sesi',
        'nama_sesi',
        'question_bank_id',
        'mata_pelajaran_id',
        'semester_id',
        'guru_id',
        'kelas',
        'rombel_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'durasi_menit',
        'jumlah_soal_tampil',
        'acak_soal',
        'acak_opsi',
        'tampilkan_nilai',
        'boleh_ulang',
        'maksimal_percobaan',
        'passing_grade',
        'status'
    ];

    protected $casts = [
        'acak_soal' => 'boolean',
        'acak_opsi' => 'boolean',
        'tampilkan_nilai' => 'boolean',
        'boleh_ulang' => 'boolean',
        'passing_grade' => 'float',
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MataPelajaran::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Semester::class, 'semester_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'guru_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Rombel::class);
    }

    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'test_session_id');
    }

    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class, 'test_session_id');
    }

    // Helper methods
    public function isOngoing(): bool
    {
        return $this->status === 'ongoing' &&
               now()->between($this->tanggal_mulai, $this->tanggal_selesai);
    }

    public function canStudentTake($siswaId): bool
    {
        if (!$this->isOngoing()) return false;

        $attempts = TestResult::where('test_session_id', $this->id)
                             ->where('siswa_id', $siswaId)
                             ->count();

        if (!$this->boleh_ulang && $attempts > 0) return false;
        if ($this->maksimal_percobaan > 0 && $attempts >= $this->maksimal_percobaan) return false;

        return true;
    }
}
