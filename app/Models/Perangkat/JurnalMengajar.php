<?php

namespace App\Models\Perangkat;

use App\Models\Elearning\KelasDigital;
use App\Models\Rombel;
use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class JurnalMengajar extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'jurnal_mengajar';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'rombel_id',
        'perangkat_id',
        'tp_id',
        'kelas_digital_id',
        'tanggal',
        'jam_ke',
        'kelas',
        'mata_pelajaran',
        'pertemuan_ke',
        'topik',
        'praktik_pedagogis',
        'prinsip_pm',
        'pengalaman_belajar',
        'kegiatan',
        'asesmen',
        'jumlah_hadir',
        'jumlah_tidak_hadir',
        'catatan',
        'tindak_lanjut',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'pertemuan_ke' => 'integer',
            'jumlah_hadir' => 'integer',
            'jumlah_tidak_hadir' => 'integer',
            'prinsip_pm' => 'array',
            'pengalaman_belajar' => 'array',
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

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function perangkat(): BelongsTo
    {
        return $this->belongsTo(PerangkatPembelajaran::class, 'perangkat_id');
    }

    public function kelasDigital(): BelongsTo
    {
        return $this->belongsTo(KelasDigital::class, 'kelas_digital_id');
    }
}
