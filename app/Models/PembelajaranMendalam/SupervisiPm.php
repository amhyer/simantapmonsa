<?php

namespace App\Models\PembelajaranMendalam;

use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupervisiPm extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_supervisi';

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DIREVIEW = 'direview';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_REVISI = 'revisi';

    public const STATUS_LABELS = [
        self::STATUS_MENUNGGU => 'Menunggu Supervisi',
        self::STATUS_DIREVIEW => 'Sedang Direview',
        self::STATUS_DISETUJUI => 'Disetujui Kepsek',
        self::STATUS_REVISI => 'Perlu Revisi',
    ];

    public const STATUS_BADGES = [
        self::STATUS_MENUNGGU => 'badge-warning',
        self::STATUS_DIREVIEW => 'badge-info',
        self::STATUS_DISETUJUI => 'badge-success',
        self::STATUS_REVISI => 'badge-danger',
    ];

    public const PENGAWAS_MENUNGGU = 'menunggu';

    public const PENGAWAS_TERVERIFIKASI = 'terverifikasi';

    public const PENGAWAS_PEMBINAAN = 'pembinaan';

    public const PENGAWAS_LABELS = [
        self::PENGAWAS_MENUNGGU => 'Menunggu Verifikasi Pengawas',
        self::PENGAWAS_TERVERIFIKASI => 'Terverifikasi Pengawas',
        self::PENGAWAS_PEMBINAAN => 'Catatan Pembinaan Pengawas',
    ];

    public const PENGAWAS_BADGES = [
        self::PENGAWAS_MENUNGGU => 'badge-warning',
        self::PENGAWAS_TERVERIFIKASI => 'badge-success',
        self::PENGAWAS_PEMBINAAN => 'badge-info',
    ];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'kepsek_id',
        'pengawas_id',
        'tahun_ajaran',
        'semester',
        'status',
        'skor_kelengkapan',
        'catatan_kepsek',
        'disetujui_at',
        'status_pengawas',
        'catatan_pengawas',
        'rekomendasi_rtl',
        'diverifikasi_pengawas_at',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'skor_kelengkapan' => 'integer',
            'disetujui_at' => 'datetime',
            'diverifikasi_pengawas_at' => 'datetime',
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

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function kepsek(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepsek_id');
    }

    public function pengawas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengawas_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'badge-info';
    }

    public function statusPengawasLabel(): string
    {
        $st = $this->status_pengawas ?: self::PENGAWAS_MENUNGGU;

        return self::PENGAWAS_LABELS[$st] ?? ucfirst((string) $st);
    }

    public function statusPengawasBadge(): string
    {
        $st = $this->status_pengawas ?: self::PENGAWAS_MENUNGGU;

        return self::PENGAWAS_BADGES[$st] ?? 'badge-warning';
    }
}
