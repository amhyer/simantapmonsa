<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SiswaBelajar extends Model
{
    use BelongsToTenant;

    protected $table = 'siswa_belajar';

    protected $fillable = [
        'tenant_id',
        'siswa_id',
        'mata_pelajaran',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
