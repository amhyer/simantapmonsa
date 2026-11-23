<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class KelompokKokurikuler extends Model
{
    use BelongsToTenant;

    protected $table = 'kelompok_kokurikuler';

    protected $fillable = ['tenant_id', 'kegiatan_id', 'nama', 'keterangan'];

    public function kegiatan()
    {
        return $this->belongsTo(KegiatanKokurikuler::class, 'kegiatan_id');
    }

    public function anggota()
    {
        return $this->belongsToMany(Siswa::class, 'anggota_kelompok', 'kelompok_kokurikuler_id', 'siswa_id');
    }
}
