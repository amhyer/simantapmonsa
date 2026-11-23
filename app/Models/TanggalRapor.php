<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TanggalRapor extends Model
{
    use BelongsToTenant;

    protected $table = 'tanggal_rapor';

    protected $fillable = [
        'tenant_id', 'tahun_ajaran', 'semester', 'tanggal', 'tempat', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
}
