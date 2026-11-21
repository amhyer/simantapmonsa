<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KktpItem extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_kktp_item';

    public const PENDEKATAN_LABELS = [
        'deskripsi' => 'Deskripsi Kriteria',
        'rubrik' => 'Rubrik Penilaian',
        'interval' => 'Interval Nilai',
    ];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'tp_id',
        'pendekatan',
        'kriteria',
        'rubrik',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function tp(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaranPm::class, 'tp_id');
    }
}
