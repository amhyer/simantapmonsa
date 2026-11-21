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

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'kepsek_id',
        'tahun_ajaran',
        'semester',
        'status',
        'skor_kelengkapan',
        'catatan_kepsek',
        'disetujui_at',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'skor_kelengkapan' => 'integer',
            'disetujui_at' => 'datetime',
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

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'badge-info';
    }
}
