<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HariLibur extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_hari_libur';

    public const JENIS_LABELS = [
        'libur_nasional' => 'Libur Nasional / Keagamaan',
        'libur_semester' => 'Libur Semester / Kenaikan Kelas',
        'kegiatan_sekolah' => 'Kegiatan Sekolah / Ujian',
        'cuti_bersama' => 'Cuti Bersama',
    ];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'tahun_ajaran',
        'tanggal_mulai',
        'tanggal_selesai',
        'nama',
        'jenis',
        'keterangan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
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
}
