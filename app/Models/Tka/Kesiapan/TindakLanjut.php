<?php

namespace App\Models\Tka\Kesiapan;

use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TindakLanjut extends Model
{
    use BelongsToTenant;

    public const STATUS = ['disarankan', 'ditinjau', 'berjalan', 'selesai', 'ditolak'];

    protected $table = 'tka_kesiapan_tindak_lanjut';

    protected $fillable = [
        'uuid', 'tenant_id', 'siswa_id', 'tahun_ajaran', 'semester', 'indikator_kode', 'rekomendasi',
        'bukti_ref', 'status', 'catatan_guru', 'disunting_guru', 'dibuat_oleh', 'diubah_oleh',
    ];

    protected $casts = ['bukti_ref' => 'array', 'disunting_guru' => 'boolean'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $m): void {
            $m->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (($field ?? 'uuid') === 'uuid' && ! Str::isUuid((string) $value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function revisi(): HasMany
    {
        return $this->hasMany(TindakLanjutRevisi::class, 'tindak_lanjut_id')->latest('id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
