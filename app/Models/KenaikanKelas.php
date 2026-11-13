<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class KenaikanKelas extends Model
{
    use BelongsToTenant;

    protected $table = 'kenaikan_kelas';

    protected $fillable = [
        'tenant_id',
        'siswa_id',
        'tahun_ajaran',
        'semester',
        'naik_kelas',
        'catatan',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'naik_kelas' => 'boolean',
        ];
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
