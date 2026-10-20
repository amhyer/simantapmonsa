<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Result extends Model
{
    protected $table = 'tryout_results';
    protected $fillable = [
        'uuid', 'tryout_session_id', 'siswa_id', 'attempt_number',
        'nilai_total', 'nilai_maksimal', 'persentase', 'peringkat',
        'lulus', 'prediksi_peminatan', 'detail_nilai_mapel',
        'analisis_kelemahan', 'rekomendasi_pembelajaran',
        'waktu_mulai', 'waktu_selesai', 'durasi_total_detik'
    ];

    protected $casts = [
        'lulus' => 'boolean',
        'nilai_total' => 'float',
        'nilai_maksimal' => 'float',
        'persentase' => 'float',
        'prediksi_peminatan' => 'array',
        'detail_nilai_mapel' => 'array',
        'analisis_kelemahan' => 'array',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'tryout_session_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }

    /**
     * Jawaban milik percobaan ini (sesi + siswa + attempt), bukan seluruh
     * jawaban siswa pada sesi tersebut.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'tryout_session_id')
            ->where('siswa_id', $this->siswa_id)
            ->where('attempt_number', $this->attempt_number);
    }

    // ---------------------------------------------------- nama lama (tampilan)
    // Halaman admin (`admin.tryout.results.index`) memakai nama kolom lama:
    // skor_total, jumlah_benar/salah/kosong, waktu_pengerjaan_detik. Sebelumnya
    // atribut itu tidak ada sehingga tabel & kartu statistik selalu tampil 0.0.
    // Disajikan sebagai accessor agar skema baru tetap rapi.
    //
    // `skor_total` sengaja memetakan **persentase**, bukan poin mentah: kolom
    // Skor berdampingan dengan badge Lulus/Tidak Lulus yang ambangnya 60, dan
    // kartu Rata-rata/Tertinggi/Terendah Skor memakai angka yang sama.

    public function getSkorTotalAttribute(): float
    {
        return (float) $this->persentase;
    }

    public function getJumlahBenarAttribute(): int
    {
        return $this->hitungJawaban('benar');
    }

    public function getJumlahSalahAttribute(): int
    {
        return $this->hitungJawaban('salah');
    }

    public function getJumlahKosongAttribute(): int
    {
        return $this->hitungJawaban('kosong');
    }

    public function getWaktuPengerjaanDetikAttribute(): ?int
    {
        return $this->durasi_total_detik !== null ? (int) $this->durasi_total_detik : null;
    }

    private function hitungJawaban(string $jenis): int
    {
        $answers = $this->answers()->get(['adalah_benar']);

        return match ($jenis) {
            'benar' => $answers->where('adalah_benar', true)->count(),
            'salah' => $answers->where('adalah_benar', false)->count(),
            default => $answers->whereNull('adalah_benar')->count(),
        };
    }
}
