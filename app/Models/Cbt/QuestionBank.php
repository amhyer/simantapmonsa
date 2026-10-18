<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionBank extends Model
{
    use SoftDeletes;

    protected $table = 'cbt_question_banks';
    protected $fillable = [
        'uuid',
        'mata_pelajaran_id',
        'semester_id',
        'guru_id',
        'kode_bank',
        'nama_bank',
        'deskripsi',
        'tingkat_kesulitan',
        'kategori_soal',
        'jumlah_soal',
        'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

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

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'question_bank_id');
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class, 'question_bank_id');
    }
}
