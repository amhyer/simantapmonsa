<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KokurikulerPm extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'pm_kokurikuler';

    public const BENTUK_LABELS = [
        'kolaboratif_lintas_disiplin' => 'Pembelajaran Kolaboratif Lintas Disiplin Ilmu',
        'tujuh_kebiasaan' => 'Gerakan 7 Kebiasaan Anak Indonesia Hebat',
        'lainnya' => 'Cara Lainnya',
    ];

    public const DIMENSI_PROFIL_LULUSAN = [
        'Keimanan & Ketakwaan terhadap Tuhan YME',
        'Kewargaan',
        'Penalaran Kritis',
        'Kreativitas',
        'Kolaborasi',
        'Kemandirian',
        'Kesehatan',
        'Komunikasi',
    ];

    public const MODEL_PEMBELAJARAN = [
        'Inkuiri',
        'Problem Based Learning (PBL)',
        'Project Based Learning (PjBL)',
        'Discovery Learning',
        'Pembelajaran Kooperatif',
        'Pembelajaran Berdiferensiasi',
        'Pembelajaran Kontekstual',
        'Pembelajaran Berbasis Permainan',
        'Lainnya',
    ];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'tahun_ajaran',
        'tingkat',
        'tema_judul',
        'bentuk_kegiatan',
        'dimensi_profil',
        'tanggal_mulai',
        'tanggal_selesai',
        'tujuan',
        'rangkaian_kegiatan',
        'mitra',
        'refleksi_hasil',
        'sumber',
    ];

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'dimensi_profil' => 'array',
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

    public function bentukLabel(): string
    {
        return self::BENTUK_LABELS[$this->bentuk_kegiatan] ?? $this->bentuk_kegiatan;
    }
}
