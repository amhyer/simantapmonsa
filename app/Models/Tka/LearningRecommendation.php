<?php

namespace App\Models\Tka;

use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LearningRecommendation extends Model
{
    use BelongsToTenant;

    protected $table = 'tka_learning_recommendations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'siswa_id',
        'semester_id',
        'dibuat_oleh',
        'zona_kemampuan',
        'skor_kognitif',
        'rata_rata_pembanding',
        'indikator_lemah',
        'indikator_kuat',
        'paket_tindak_lanjut',
        'status_tindak_lanjut',
        'catatan_guru',
    ];

    protected $casts = [
        'skor_kognitif' => 'float',
        'rata_rata_pembanding' => 'float',
        'indikator_lemah' => 'array',
        'indikator_kuat' => 'array',
        'paket_tindak_lanjut' => 'array',
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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $key = $field ?? $this->getRouteKeyName();
        if ($key === 'uuid' && ! Str::isUuid((string) $value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function zonaLabel(): string
    {
        return match ($this->zona_kemampuan) {
            'di_atas_rata_rata' => 'Di Atas Rata-rata',
            'di_bawah_rata_rata' => 'Di Bawah Rata-rata',
            default => 'Rata-rata',
        };
    }
}
