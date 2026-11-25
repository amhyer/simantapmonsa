<?php

namespace App\Models\PembelajaranMendalam;

use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use App\Support\SchoolLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CapaianPembelajaran extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'pm_capaian_pembelajaran';

    public const FASE_LABELS = SchoolLevel::FASE_LABELS;

    /**
     * @return array<string, string>
     */
    public static function faseLabelsFor(?int $tenantId = null): array
    {
        return SchoolLevel::faseLabelsForJenjang(SchoolLevel::current($tenantId));
    }

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'mata_pelajaran_id',
        'nama_mapel',
        'fase',
        'elemen',
        'deskripsi',
        'urutan',
        'sumber',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
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

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function tujuanPembelajaran(): HasMany
    {
        return $this->hasMany(TujuanPembelajaranPm::class, 'cp_id')->orderBy('urutan');
    }
}
