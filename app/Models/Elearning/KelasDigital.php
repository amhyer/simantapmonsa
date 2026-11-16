<?php

namespace App\Models\Elearning;

use App\Models\Rombel;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KelasDigital extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'kelas_digital';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'rombel_id',
        'semester_id',
        'nama_kelas',
        'mata_pelajaran',
        'kelas',
        'kode_kelas',
        'deskripsi',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
            if (! $model->kode_kelas) {
                $model->kode_kelas = strtoupper(Str::random(6));
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

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function modul(): HasMany
    {
        return $this->hasMany(KelasDigitalModul::class, 'kelas_digital_id')->orderBy('urutan')->orderBy('id');
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(KelasDigitalTugas::class, 'kelas_digital_id');
    }
}
