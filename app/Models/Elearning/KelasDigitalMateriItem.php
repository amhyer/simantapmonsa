<?php

namespace App\Models\Elearning;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KelasDigitalMateriItem extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'kelas_digital_materi_item';

    public const TIPE_VALID = ['teks', 'video', 'file', 'link'];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'pertemuan_id',
        'tipe',
        'judul',
        'konten',
        'tautan',
        'file_path',
        'file_nama_asli',
        'file_mime',
        'file_ukuran',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'file_ukuran' => 'integer',
            'urutan' => 'integer',
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

    /**
     * Ekstrak URL embed YouTube yang aman (hanya skema https dan ID video valid)
     * agar view dapat menampilkan player tanpa risiko injeksi skrip.
     */
    public function youtubeEmbedUrl(): ?string
    {
        if ($this->tipe !== 'video' || empty($this->tautan)) {
            return null;
        }

        $url = trim((string) $this->tautan);
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        return null;
    }
}
