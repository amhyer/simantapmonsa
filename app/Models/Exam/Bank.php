<?php

namespace App\Models\Exam;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use SoftDeletes;

    protected $table = 'exam_banks';

    protected $fillable = [
        'engine_id', 'guru_id', 'kode', 'nama', 'mata_pelajaran',
        'sumber_tabel', 'sumber_id', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function engine(): BelongsTo
    {
        return $this->belongsTo(Engine::class, 'engine_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'bank_id')->orderBy('urutan');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'bank_id')->orderBy('urutan');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'bank_id');
    }
}
