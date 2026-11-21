<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BukuPaketSibi extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_buku_paket';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'guru_id',
        'mata_pelajaran_id',
        'nama_mapel',
        'tingkat',
        'judul',
        'penulis',
        'tautan_sibi',
        'daftar_isi',
    ];

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
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

    /**
     * @return array<int, string>
     */
    public function babList(): array
    {
        if (! $this->daftar_isi) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $this->daftar_isi) ?: []),
            fn (string $line) => $line !== ''
        ));
    }
}
