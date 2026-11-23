<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TemaKokurikuler extends Model
{
    use BelongsToTenant;

    protected $table = 'tema_kokurikuler';

    protected $fillable = ['tenant_id', 'nama', 'deskripsi', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function kegiatan()
    {
        return $this->hasMany(KegiatanKokurikuler::class, 'tema_id');
    }
}
