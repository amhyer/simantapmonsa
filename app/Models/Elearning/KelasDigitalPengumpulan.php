<?php

namespace App\Models\Elearning;

use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KelasDigitalPengumpulan extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'kelas_digital_pengumpulan';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'tugas_id',
        'siswa_id',
        'jawaban_teks',
        'file_path',
        'file_nama_asli',
        'file_mime',
        'file_ukuran',
        'status',
        'dikumpulkan_pada',
        'nilai',
        'catatan_guru',
        'dinilai_oleh',
        'dinilai_pada',
        'nilai_id',
    ];

    protected function casts(): array
    {
        return [
            'file_ukuran' => 'integer',
            'dikumpulkan_pada' => 'datetime',
            'nilai' => 'decimal:2',
            'dinilai_pada' => 'datetime',
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

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(KelasDigitalTugas::class, 'tugas_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }

    public function nilaiRecord(): BelongsTo
    {
        return $this->belongsTo(Nilai::class, 'nilai_id');
    }
}
