<?php

namespace App\Models\Elearning;

use App\Models\Kuis;
use App\Models\Materi;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KelasDigitalPertemuan extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'kelas_digital_pertemuan';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'modul_id',
        'materi_id',
        'kuis_id',
        'judul',
        'deskripsi',
        'tanggal',
        'urutan',
        'dipublikasikan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'urutan' => 'integer',
            'dipublikasikan' => 'boolean',
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

    public function modul(): BelongsTo
    {
        return $this->belongsTo(KelasDigitalModul::class, 'modul_id');
    }

    public function materi(): BelongsTo
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }

    public function kuis(): BelongsTo
    {
        return $this->belongsTo(Kuis::class, 'kuis_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KelasDigitalMateriItem::class, 'pertemuan_id')->orderBy('urutan')->orderBy('id');
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(KelasDigitalTugas::class, 'pertemuan_id')->orderBy('id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(KelasDigitalProgress::class, 'pertemuan_id');
    }
}
