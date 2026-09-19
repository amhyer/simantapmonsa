<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KegiatanKokurikuler extends Model
{
    protected $table = 'kegiatan_kokurikuler';

    protected $fillable = [
        'tema_id', 'nama', 'kelas', 'koordinator',
        'tanggal_mulai', 'tanggal_selesai', 'deskripsi', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function tema()
    {
        return $this->belongsTo(TemaKokurikuler::class, 'tema_id');
    }

    public function kelompok()
    {
        return $this->hasMany(KelompokKokurikuler::class, 'kegiatan_id');
    }
}
