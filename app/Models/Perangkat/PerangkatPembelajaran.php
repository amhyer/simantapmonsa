<?php

namespace App\Models\Perangkat;

use App\Models\Elearning\KelasDigital;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PerangkatPembelajaran extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'perangkat_pembelajaran';

    public const JENIS_CP = 'cp';

    public const JENIS_TP = 'tp';

    public const JENIS_ATP = 'atp';

    public const JENIS_PROTA = 'prota';

    public const JENIS_PROMES = 'promes';

    public const JENIS_MODUL_AJAR = 'modul_ajar';

    public const JENIS_KKM = 'kkm';

    /** @var array<string, string> */
    public const JENIS_LABELS = [
        self::JENIS_CP => 'CP (Capaian Pembelajaran)',
        self::JENIS_TP => 'TP (Tujuan Pembelajaran)',
        self::JENIS_ATP => 'ATP (Alur Tujuan Pembelajaran)',
        self::JENIS_PROTA => 'Prota (Program Tahunan)',
        self::JENIS_PROMES => 'Promes (Program Semester)',
        self::JENIS_MODUL_AJAR => 'Modul Ajar / RPP',
        self::JENIS_KKM => 'KKM / KKTP',
    ];

    /** @var array<string, string> */
    public const JENIS_RINGKAS = [
        self::JENIS_CP => 'CP',
        self::JENIS_TP => 'TP',
        self::JENIS_ATP => 'ATP',
        self::JENIS_PROTA => 'Prota',
        self::JENIS_PROMES => 'Promes',
        self::JENIS_MODUL_AJAR => 'Modul Ajar',
        self::JENIS_KKM => 'KKM/KKTP',
    ];

    /** @var array<int, string> */
    public const FASE_VALID = ['A', 'B', 'C', 'D', 'E', 'F'];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'rombel_id',
        'semester_id',
        'kelas_digital_id',
        'jenis',
        'judul',
        'mata_pelajaran',
        'fase',
        'kelas',
        'tahun_ajaran',
        'semester',
        'alokasi_waktu',
        'ringkasan',
        'konten',
        'lampiran_path',
        'lampiran_nama',
        'lampiran_mime',
        'lampiran_ukuran',
        'status',
        'dibagikan_ke_walas',
    ];

    protected function casts(): array
    {
        return [
            'konten' => 'array',
            'lampiran_ukuran' => 'integer',
            'dibagikan_ke_walas' => 'boolean',
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

    public function jenisLabel(): string
    {
        return self::JENIS_LABELS[$this->jenis] ?? strtoupper((string) $this->jenis);
    }

    public function jenisRingkas(): string
    {
        return self::JENIS_RINGKAS[$this->jenis] ?? strtoupper((string) $this->jenis);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function semesterRecord(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function kelasDigital(): BelongsTo
    {
        return $this->belongsTo(KelasDigital::class, 'kelas_digital_id');
    }

    public function jurnal(): HasMany
    {
        return $this->hasMany(JurnalMengajar::class, 'perangkat_id')->orderByDesc('tanggal');
    }
}
