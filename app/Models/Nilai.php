<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Nilai extends Model
{
    use SoftDeletes;
    protected $table = 'nilai';
    protected $fillable = [
        'uuid',
        'guru_id',
        'siswa_id',
        'nama_guru',
        'nama_siswa',
        'tanggal',
        'jenis',
        'judul_penilaian',
        'mata_pelajaran',
        'nilai',
        'kuis_id',
        'rekaman',
    ];

    protected function casts(): array
    {
        return [
            'rekaman' => 'array',
            'tanggal' => 'date',
            'nilai' => 'decimal:2',
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
