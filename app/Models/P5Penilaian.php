<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class P5Penilaian extends Model
{
    use BelongsToTenant;

    protected $table = 'p5_penilaian';

    protected $fillable = [
        'tenant_id',
        'siswa_id',
        'tema',
        'dimensi',
        'predikat',
        'catatan',
        'semester',
        'tahun_ajaran',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
