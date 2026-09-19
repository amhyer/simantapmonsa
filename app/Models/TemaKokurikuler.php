<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemaKokurikuler extends Model
{
    protected $table = 'tema_kokurikuler';

    protected $fillable = ['nama', 'deskripsi', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function kegiatan()
    {
        return $this->hasMany(KegiatanKokurikuler::class, 'tema_id');
    }
}
