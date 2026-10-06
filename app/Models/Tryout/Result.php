<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Result extends Model
{
    protected $table = 'tryout_results';
    protected $fillable = [
        'uuid', 'tryout_session_id', 'siswa_id', 'attempt_number',
        'nilai_total', 'nilai_maksimal', 'persentase', 'peringkat',
        'lulus', 'prediksi_peminatan', 'detail_nilai_mapel',
        'analisis_kelemahan', 'rekomendasi_pembelajaran',
        'waktu_mulai', 'waktu_selesai', 'durasi_total_detik'
    ];

    protected $casts = [
        'lulus' => 'boolean',
        'nilai_total' => 'float',
        'nilai_maksimal' => 'float',
        'persentase' => 'float',
        'prediksi_peminatan' => 'array',
        'detail_nilai_mapel' => 'array',
        'analisis_kelemahan' => 'array',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'tryout_session_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }
}
