<?php

namespace App\Models\Tka;

use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdaptiveSimulation extends Model
{
    use BelongsToTenant;

    protected $table = 'tka_adaptive_simulations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'siswa_id',
        'paket_kode',
        'paket_judul',
        'mode_simulasi',
        'tingkat_awal',
        'tingkat_akhir',
        'target_soal',
        'total_soal',
        'benar',
        'skor_akhir',
        'level_capaian',
        'durasi_detik',
        'jejak_adaptif',
        'skor_indikator',
        'status',
        'started_at',
        'submitted_at',
        'dibaca_ortu_at',
        'dibaca_oleh_ortu_id',
        'catatan_ortu',
    ];

    protected $casts = [
        'target_soal' => 'integer',
        'total_soal' => 'integer',
        'benar' => 'integer',
        'skor_akhir' => 'float',
        'durasi_detik' => 'integer',
        'jejak_adaptif' => 'array',
        'skor_indikator' => 'array',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'dibaca_ortu_at' => 'datetime',
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

    public function pembacaOrtu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibaca_oleh_ortu_id');
    }

    public function durasiLabel(): string
    {
        $detik = max(0, (int) $this->durasi_detik);
        $menit = intdiv($detik, 60);
        $sisa = $detik % 60;

        if ($menit === 0) {
            return "{$sisa} detik";
        }

        return "{$menit} mnt {$sisa} dtk";
    }
}
