<?php

namespace App\Models\Elearning;

use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KelasDigitalTugas extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'kelas_digital_tugas';

    public const TIPE_PENGUMPULAN = ['teks', 'file', 'keduanya'];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'pertemuan_id',
        'kelas_digital_id',
        'guru_id',
        'judul',
        'instruksi',
        'tipe_pengumpulan',
        'batas_waktu',
        'nilai_maksimal',
        'lampiran_path',
        'lampiran_nama',
        'lampiran_mime',
        'lampiran_ukuran',
        'sinkron_ke_nilai',
    ];

    protected function casts(): array
    {
        return [
            'batas_waktu' => 'datetime',
            'nilai_maksimal' => 'integer',
            'lampiran_ukuran' => 'integer',
            'sinkron_ke_nilai' => 'boolean',
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

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(KelasDigitalPertemuan::class, 'pertemuan_id');
    }

    public function kelasDigital(): BelongsTo
    {
        return $this->belongsTo(KelasDigital::class, 'kelas_digital_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function pengumpulan(): HasMany
    {
        return $this->hasMany(KelasDigitalPengumpulan::class, 'tugas_id');
    }
}
