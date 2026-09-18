<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Dimensi extends Model
{
    use SoftDeletes;
    protected $table = 'dimensi';
    protected $fillable = [
        'uuid',
        'guru_id',
        'siswa_id',
        'nama_guru',
        'nama_siswa',
        'no_dimensi',
        'dimensi',
        'skor',
        'predikat',
        'catatan',
        'rekaman',
    ];

    protected function casts(): array
    {
        return [
            'rekaman' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) $model->uuid = Str::uuid();
        });
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
