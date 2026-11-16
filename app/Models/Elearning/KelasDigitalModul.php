<?php

namespace App\Models\Elearning;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KelasDigitalModul extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'kelas_digital_modul';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'kelas_digital_id',
        'judul',
        'deskripsi',
        'urutan',
        'tujuan_pembelajaran',
        'pm',
        'dimensi',
        'pengalaman',
        'dipublikasikan',
    ];

    protected function casts(): array
    {
        return [
            'pm' => 'array',
            'dimensi' => 'array',
            'pengalaman' => 'array',
            'dipublikasikan' => 'boolean',
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

    public function kelasDigital(): BelongsTo
    {
        return $this->belongsTo(KelasDigital::class, 'kelas_digital_id');
    }

    public function pertemuan(): HasMany
    {
        return $this->hasMany(KelasDigitalPertemuan::class, 'modul_id')->orderBy('urutan')->orderBy('id');
    }
}
