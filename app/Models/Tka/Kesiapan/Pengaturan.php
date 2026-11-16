<?php

namespace App\Models\Tka\Kesiapan;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    use BelongsToTenant;

    protected $table = 'tka_kesiapan_pengaturan';

    protected $fillable = ['tenant_id', 'aturan', 'diubah_oleh'];

    protected $casts = ['aturan' => 'array'];
}
