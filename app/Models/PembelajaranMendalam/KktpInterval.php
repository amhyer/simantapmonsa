<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KktpInterval extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_kktp_interval';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'mata_pelajaran_id',
        'nama_mapel',
        'tingkat',
        'nilai_kktp',
        'batas_bawah',
        'batas_atas',
        'ket_baru_berkembang',
        'ket_layak',
        'ket_cakap',
        'ket_mahir',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'nilai_kktp' => 'integer',
            'batas_bawah' => 'integer',
            'batas_atas' => 'integer',
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

    /**
     * Bangun 4 rentang predikat Pembelajaran Mendalam.
     *
     * @return array<int, array{interval: string, predikat: string, warna: string, keterangan: string}>
     */
    public function rentangPredikat(): array
    {
        $bawah = max(1, min(95, (int) $this->batas_bawah));
        $kktp = max($bawah + 1, min(98, (int) $this->nilai_kktp));
        $atas = max($kktp + 1, min(99, (int) $this->batas_atas));

        return [
            [
                'interval' => '0 – '.($bawah - 1),
                'predikat' => 'Baru Berkembang',
                'warna' => 'badge-danger',
                'keterangan' => $this->ket_baru_berkembang ?: 'Belum mencapai ketuntasan, remedial di seluruh bagian',
            ],
            [
                'interval' => $bawah.' – '.($kktp - 1),
                'predikat' => 'Layak',
                'warna' => 'badge-warning',
                'keterangan' => $this->ket_layak ?: 'Belum mencapai ketuntasan, remedial di bagian yang diperlukan',
            ],
            [
                'interval' => $kktp.' – '.($atas - 1),
                'predikat' => 'Cakap',
                'warna' => 'badge-info',
                'keterangan' => $this->ket_cakap ?: 'Sudah mencapai ketuntasan, tidak perlu remedial',
            ],
            [
                'interval' => $atas.' – 100',
                'predikat' => 'Mahir',
                'warna' => 'badge-success',
                'keterangan' => $this->ket_mahir ?: 'Sudah mencapai ketuntasan, perlu pengayaan atau tantangan lebih',
            ],
        ];
    }
}
