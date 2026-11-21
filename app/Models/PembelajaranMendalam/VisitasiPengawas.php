<?php

namespace App\Models\PembelajaranMendalam;

use App\Models\User;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VisitasiPengawas extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_visitasi_pengawas';

    public const JENIS_PENDAMPINGAN = [
        'supervisi_akademik' => 'Supervisi Akademik & Observasi Kelas (Pembelajaran Mendalam)',
        'supervisi_manajerial' => 'Supervisi Manajerial & Tata Kelola Satuan Pendidikan',
        'pendampingan_kurikulum' => 'Pendampingan Kurikulum, KKTP & e-Rapor',
        'pemantauan_tka_literasi' => 'Pemantauan Kesiapan TKA, Literasi & 7 Kebiasaan',
    ];

    public const SKALA_RUBRIK = [
        1 => '1 — Belum Tampak (Perlu Pendampingan Intensif)',
        2 => '2 — Mulai Berkembang (Perlu Penguatan)',
        3 => '3 — Terlaksana Baik (Sesuai Standar)',
        4 => '4 — Sangat Baik / Praktik Baik (Inspiratif)',
    ];

    protected $fillable = [
        'uuid',
        'tenant_id',
        'pengawas_id',
        'guru_id',
        'tahun_ajaran',
        'semester',
        'tanggal_visitasi',
        'jenis_pendampingan',
        'skor_berkesadaran',
        'skor_bermakna',
        'skor_menggembirakan',
        'skor_memahami',
        'skor_mengaplikasi',
        'skor_merefleksi',
        'temuan',
        'rekomendasi',
        'tindak_lanjut',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'tanggal_visitasi' => 'date',
            'skor_berkesadaran' => 'integer',
            'skor_bermakna' => 'integer',
            'skor_menggembirakan' => 'integer',
            'skor_memahami' => 'integer',
            'skor_mengaplikasi' => 'integer',
            'skor_merefleksi' => 'integer',
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

    public function pengawas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengawas_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function jenisLabel(): string
    {
        return self::JENIS_PENDAMPINGAN[$this->jenis_pendampingan] ?? $this->jenis_pendampingan;
    }

    public function rataRataSkor(): float
    {
        $total = (int) $this->skor_berkesadaran
            + (int) $this->skor_bermakna
            + (int) $this->skor_menggembirakan
            + (int) $this->skor_memahami
            + (int) $this->skor_mengaplikasi
            + (int) $this->skor_merefleksi;

        return round($total / 6, 2);
    }

    public function persenSkor(): int
    {
        return (int) round(($this->rataRataSkor() / 4) * 100);
    }

    public function predikatObservasi(): string
    {
        $avg = $this->rataRataSkor();
        if ($avg >= 3.5) {
            return 'Sangat Baik (Praktik Baik)';
        }
        if ($avg >= 2.75) {
            return 'Baik (Sesuai Standar)';
        }
        if ($avg >= 2.0) {
            return 'Cukup (Perlu Penguatan)';
        }

        return 'Kurang (Pendampingan Intensif)';
    }
}
