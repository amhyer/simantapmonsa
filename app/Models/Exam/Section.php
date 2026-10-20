<?php

namespace App\Models\Exam;

use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $table = 'exam_sections';

    protected $fillable = [
        'bank_id', 'nama', 'mata_pelajaran_id', 'urutan', 'bobot',
        'durasi_menit', 'passing_grade', 'sumber_tabel', 'sumber_id', 'meta',
    ];

    protected $casts = [
        'bobot' => 'float',
        'passing_grade' => 'float',
        'durasi_menit' => 'integer',
        'urutan' => 'integer',
        'meta' => 'array',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'section_id')->orderBy('urutan');
    }
}
