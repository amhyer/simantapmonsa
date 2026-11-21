<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProsemItem extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_prosem';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'tahun_ajaran',
        'tingkat',
        'nama_mapel',
        'semester',
        'prota_id',
        'tp_id',
        'bab',
        'materi',
        'bulan',
        'minggu_ke',
        'alokasi_jp',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'semester' => 'integer',
            'bulan' => 'integer',
            'minggu_ke' => 'integer',
            'alokasi_jp' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) === 'uuid' && ! Str::isUuid((string) $value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function prota(): BelongsTo
    {
        return $this->belongsTo(ProtaItem::class, 'prota_id');
    }

    public function tp(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaranPm::class, 'tp_id');
    }
}
