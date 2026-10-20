<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Engine extends Model
{
    protected $table = 'exam_engines';

    protected $fillable = ['kode', 'nama', 'jenis', 'deskripsi', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function banks(): HasMany
    {
        return $this->hasMany(Bank::class, 'engine_id');
    }

    public const CBT = 'cbt';

    public const TRYOUT = 'tryout';
}
