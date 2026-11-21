<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_ai_usage_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'fitur',
        'model',
        'status',
        'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
}
