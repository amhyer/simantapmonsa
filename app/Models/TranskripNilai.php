<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TranskripNilai extends Model
{
    use SoftDeletes;

    protected $table = 'transkrip_nilai';

    protected $fillable = [
        'uuid',
        'siswa_id',
        'mata_pelajaran_id',
        'nama_mapel',
        'nilai_akhir',
        'predikat',
    ];

    protected function casts(): array
    {
        return [
            'nilai_akhir' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function mapel()
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public static function tentukanPredikat(float $nilai): string
    {
        $kkm = getKKM();
        if ($nilai >= 90) {
            return 'A';
        }
        if ($nilai >= 80) {
            return 'B';
        }
        if ($nilai >= $kkm) {
            return 'C';
        }

        return 'D';
    }
}
