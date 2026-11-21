<?php

namespace App\Models\PembelajaranMendalam;

use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TujuanPembelajaranPm extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'pm_tujuan_pembelajaran';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'cp_id',
        'mata_pelajaran_id',
        'nama_mapel',
        'fase',
        'elemen',
        'kode_tp',
        'deskripsi',
        'urutan',
        'kelas_target',
        'semester_target',
        'materi_pokok',
        'alokasi_jp',
        'dimensi_profil',
        'sumber',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'kelas_target' => 'integer',
            'semester_target' => 'integer',
            'alokasi_jp' => 'integer',
            'dimensi_profil' => 'array',
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

    public function cp(): BelongsTo
    {
        return $this->belongsTo(CapaianPembelajaran::class, 'cp_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function kktpItem(): HasOne
    {
        return $this->hasOne(KktpItem::class, 'tp_id');
    }

    /**
     * Daftar tingkat kelas yang valid untuk sebuah Fase SD.
     *
     * @return array<int, int>
     */
    public static function kelasUntukFase(string $fase): array
    {
        return match (strtoupper($fase)) {
            'A' => [1, 2],
            'B' => [3, 4],
            'C' => [5, 6],
            default => [1, 2, 3, 4, 5, 6],
        };
    }

    public static function faseUntukKelas(int $tingkat): string
    {
        return match (true) {
            $tingkat <= 2 => 'A',
            $tingkat <= 4 => 'B',
            default => 'C',
        };
    }
}
