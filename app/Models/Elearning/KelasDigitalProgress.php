<?php

namespace App\Models\Elearning;

use App\Models\Siswa;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KelasDigitalProgress extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'kelas_digital_progress';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'pertemuan_id',
        'siswa_id',
        'selesai',
        'selesai_pada',
    ];

    protected function casts(): array
    {
        return [
            'selesai' => 'boolean',
            'selesai_pada' => 'datetime',
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

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(KelasDigitalPertemuan::class, 'pertemuan_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
