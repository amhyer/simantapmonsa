<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProtaItem extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_prota';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'tahun_ajaran',
        'tingkat',
        'nama_mapel',
        'semester',
        'tp_id',
        'urutan',
        'materi',
        'alokasi_jp',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'semester' => 'integer',
            'urutan' => 'integer',
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

    public function tp(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaranPm::class, 'tp_id');
    }

    public function prosemItems(): HasMany
    {
        return $this->hasMany(ProsemItem::class, 'prota_id');
    }
}
