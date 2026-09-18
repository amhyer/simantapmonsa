<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HasilKuis extends Model
{
    protected $table = 'hasil_kuis';
    protected $fillable = [
        'uuid',
        'guru_id',
        'kuis_id',
        'siswa_id',
        'nama_guru',
        'judul_kuis',
        'nama_siswa',
        'waktu',
        'benar',
        'total',
        'skor',
        'tuntas',
        'durasi',
        'sumber_soal',
        'diisi_oleh',
        'catatan_siswa',
        'jawaban',
        'rekaman',
    ];

    protected function casts(): array
    {
        return [
            'jawaban' => 'array',
            'rekaman' => 'array',
            'tuntas' => 'boolean',
            'waktu' => 'datetime',
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

    public function kuis()
    {
        return $this->belongsTo(Kuis::class, 'kuis_id');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
