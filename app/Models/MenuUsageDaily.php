<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuUsageDaily extends Model
{
    protected $table = 'menu_usage_daily';

    protected $fillable = [
        'visit_date',
        'scope_key',
        'tenant_id',
        'role',
        'route_name',
        'visits',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'tenant_id' => 'integer',
            'visits' => 'integer',
        ];
    }
}
