<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NilaiErapot extends Model
{
    protected $table = 'nilai_erapor';
    protected $fillable = [
        'uuid', 'siswa_id', 'guru_id', 'jadwal_pelajaran_id',
        'mata_pelajaran', 'kelas',
        'semester', 'tahun_ajaran', 'fase',
        'nilai_formatif', 'nilai_sumatif', 'nilai_sumatif_akhir',
        'nilai_akhir', 'predikat', 'deskripsi_capaian',
        'capaian_tertinggi', 'capaian_terendah',
        'terkirim_erapor', 'erapor_synced_at',
    ];

    protected $casts = [
        'terkirim_erapor' => 'boolean',
        'erapor_synced_at' => 'datetime',
        'nilai_formatif' => 'decimal:2',
        'nilai_sumatif' => 'decimal:2',
        'nilai_sumatif_akhir' => 'decimal:2',
        'nilai_akhir' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) $model->uuid = Str::uuid();
        });
    }

    public function siswa() { return $this->belongsTo(Siswa::class); }
    public function guru() { return $this->belongsTo(User::class, 'guru_id'); }
    public function auditLogs() { return $this->hasMany(NilaiErapotAudit::class, 'nilai_erapor_id')->orderBy('id'); }

    /**
     * Pembelajaran resmi (Dapodik) yang menjadi sumber nilai ini.
     * Nullable: nilai lama/belum terpetakan mengembalikan null (bukan error).
     */
    public function jadwalPelajaran() { return $this->belongsTo(JadwalPelajaran::class); }

    public function hitungNilaiAkhir(float $bobotFormatif = 0.30, float $bobotSumatif = 0.40, float $bobotSumatifAkhir = 0.30): float
    {
        $nilai = ($this->nilai_formatif * $bobotFormatif)
               + ($this->nilai_sumatif * $bobotSumatif)
               + ($this->nilai_sumatif_akhir * $bobotSumatifAkhir);
        return round($nilai, 2);
    }

    /**
     * Predikat e-Rapor — DIDELEGASIKAN ke App\Services\NilaiService agar
     * ambangnya sama dengan dashboard/laporan/input nilai (satu sumber
     * kebenaran). Sebelumnya di sini dipakai ambang mati 90/80/KKM sehingga
     * nilai yang sama bisa berpredikat berbeda antar halaman.
     */
    public function generatePredikat(?int $kkm = null): string
    {
        return app(\App\Services\NilaiService::class)
            ->getPredikat((float) $this->nilai_akhir, $kkm ?? getKKM())['huruf'];
    }

    public function generateDeskripsi(): string
    {
        // PERBAIKAN: bila `predikat` belum terisi (mis. baris baru atau import
        // yang menghitung deskripsi sebelum predikat tersimpan), hitung dari
        // nilai_akhir alih-alih jatuh ke cabang default.
        $predikat = $this->predikat ?: $this->generatePredikat();
        $deskripsi = match($predikat) {
            'A' => "Menunjukkan penguasaan yang sangat baik dalam",
            'B' => "Menunjukkan penguasaan yang baik dalam",
            'C' => "Menunjukkan penguasaan yang cukup dalam",
            'D' => "Perlu bimbingan dalam",
            default => "Menunjukkan penguasaan dalam",
        };
        $capaian = $this->capaian_tertinggi ?? 'seluruh materi';
        $text = "{$deskripsi} {$capaian}.";
        if ($this->capaian_terendah && $predikat !== 'A') {
            $text .= " Perlu peningkatan dalam {$this->capaian_terendah}.";
        }
        return $text;
    }
}
