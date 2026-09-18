<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Kebiasaan extends Model
{
    use SoftDeletes;
    protected $table = 'kebiasaan';

    protected $fillable = [
        'uuid',
        'siswa_id',
        'tanggal',
        'bangun_pagi',
        'beribadah',
        'berolahraga',
        'makan_sehat',
        'gemar_belajar',
        'bermasyarakat',
        'tidur_cepat',
        'catatan_orang_tua',
        'diisi_oleh',
        'waktu_simpan',
        'rekaman',
    ];

    protected function casts(): array
    {
        return [
            'rekaman' => 'array',
            'waktu_simpan' => 'datetime',
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

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
