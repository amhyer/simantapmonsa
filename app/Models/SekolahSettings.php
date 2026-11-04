<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SekolahSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'sekolah_settings';
    protected $hidden = ['pengaturan'];
    protected $fillable = [
        'tenant_id',
        'guru_id',
        'nama_sekolah',
        'alamat',
        'kecamatan',
        'kota',
        'provinsi',
        'kode_pos',
        'telepon',
        'email',
        'website',
        'kepala_sekolah',
        'npsn',
        'status',
        'jenis_sekolah',
        'jenjang',
        'akreditasi',
        'pengaturan',
    ];

    protected function casts(): array
    {
        return [
            'pengaturan' => 'array',
        ];
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}
