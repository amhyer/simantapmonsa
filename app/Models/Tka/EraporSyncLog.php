<?php

namespace App\Models\Tka;

use App\Models\Nilai;
use App\Models\NilaiErapot;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EraporSyncLog extends Model
{
    use BelongsToTenant;

    protected $table = 'tka_erapor_sync_logs';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'siswa_id',
        'guru_id',
        'semester_id',
        'assessment_id',
        'nilai_id',
        'nilai_erapor_id',
        'mata_pelajaran',
        'komponen_target',
        'skor_tka_konversi',
        'nilai_akhir_erapor',
        'ringkasan_sumber',
        'synced_at',
    ];

    protected $casts = [
        'skor_tka_konversi' => 'float',
        'nilai_akhir_erapor' => 'float',
        'ringkasan_sumber' => 'array',
        'synced_at' => 'datetime',
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

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function nilai(): BelongsTo
    {
        return $this->belongsTo(Nilai::class, 'nilai_id');
    }

    public function nilaiErapor(): BelongsTo
    {
        return $this->belongsTo(NilaiErapot::class, 'nilai_erapor_id');
    }
}
