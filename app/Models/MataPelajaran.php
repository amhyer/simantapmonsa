<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model
{
    use BelongsToTenant;

    protected $table = 'mata_pelajaran';

    protected $fillable = ['tenant_id', 'nama', 'kode', 'jenjang', 'kelompok', 'urutan', 'masuk_transkrip', 'aktif'];

    protected $casts = ['aktif' => 'boolean', 'urutan' => 'integer', 'masuk_transkrip' => 'boolean'];
}
