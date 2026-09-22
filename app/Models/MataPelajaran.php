<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajaran';
    protected $fillable = ['nama', 'kode', 'jenjang', 'kelompok', 'urutan', 'masuk_transkrip', 'aktif'];
    protected $casts = ['aktif' => 'boolean', 'urutan' => 'integer', 'masuk_transkrip' => 'boolean'];
}
