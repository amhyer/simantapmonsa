<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Kuis extends Model
{
    use SoftDeletes;
    protected $table = 'kuis';
    protected $fillable = [
        'uuid',
        'guru_id',
        'nama_guru',
        'tanggal',
        'judul',
        'mata_pelajaran',
        'kelas',
        'mode',
        'sumber',
        'tautan_soal',
        'materi_id',
        'kkm',
        'batas_waktu',
        'aktif',
        'jumlah_soal',
        'stimulus',
        'jenis',
        'petunjuk',
        'cara_nilai',
        'soal',
        'rekaman',
    ];

    protected function casts(): array
    {
        return [
            'soal' => 'array',
            'rekaman' => 'array',
            'aktif' => 'boolean',
            'tanggal' => 'date',
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

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }

    public function hasilKuis()
    {
        return $this->hasMany(HasilKuis::class, 'kuis_id');
    }
}
