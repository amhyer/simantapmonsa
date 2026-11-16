<?php

namespace App\Models\Tka\Kesiapan;

use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audit extends Model
{
    use BelongsToTenant;

    protected $table = 'tka_kesiapan_audit';

    protected $fillable = ['tenant_id', 'user_id', 'aksi', 'sebelum', 'sesudah', 'alasan'];

    protected $casts = ['sebelum' => 'array', 'sesudah' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
