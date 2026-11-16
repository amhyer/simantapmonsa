<?php

namespace App\Models\Tka\Kesiapan;

use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TindakLanjutRevisi extends Model
{
    use BelongsToTenant;

    protected $table = 'tka_kesiapan_tindak_lanjut_revisi';

    protected $fillable = [
        'tenant_id', 'tindak_lanjut_id', 'user_id', 'status_sebelum', 'status_sesudah',
        'rekomendasi_sebelum', 'rekomendasi_sesudah', 'catatan', 'alasan',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
