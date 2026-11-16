<?php

namespace App\Models\Tka\Kesiapan;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Pemetaan extends Model
{
    use BelongsToTenant;

    protected $table = 'tka_kesiapan_pemetaan';

    protected $fillable = ['tenant_id', 'label_sumber', 'indikator_kode', 'aktif', 'diubah_oleh'];

    protected $casts = ['aktif' => 'boolean'];
}
