<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KalenderPendidikan extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_kalender_pendidikan';

    public const NAMA_BULAN = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'tahun_ajaran',
        'semester',
        'bulan',
        'tahun',
        'jumlah_minggu',
        'minggu_tidak_efektif',
        'hari_efektif',
        'minggu_libur',
        'keterangan',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'bulan' => 'integer',
            'tahun' => 'integer',
            'jumlah_minggu' => 'integer',
            'minggu_tidak_efektif' => 'integer',
            'hari_efektif' => 'integer',
            'minggu_libur' => 'array',
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

    public function mingguEfektif(): int
    {
        return max(0, (int) $this->jumlah_minggu - (int) $this->minggu_tidak_efektif);
    }

    public function namaBulan(): string
    {
        return self::NAMA_BULAN[$this->bulan] ?? (string) $this->bulan;
    }

    /**
     * @return array<int, int>
     */
    public static function bulanUntukSemester(int $semester): array
    {
        return $semester === 2 ? [1, 2, 3, 4, 5, 6] : [7, 8, 9, 10, 11, 12];
    }
}
