<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Session extends Model
{
    use SoftDeletes;

    protected $table = 'tryout_sessions';
    protected $fillable = [
        'uuid', 'kode_sesi', 'nama_sesi', 'package_id',
        'semester_id', 'guru_id', 'kelas', 'rombel_id',
        'tanggal_mulai', 'tanggal_selesai', 'durasi_menit', 'instruksi', 'status'
    ];

    protected $casts = [
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
            if (!$model->kode_sesi) {
                $model->kode_sesi = 'TRY-' . strtoupper(Str::random(8));
            }
        });
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Semester::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'guru_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Rombel::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class, 'tryout_session_id');
    }

    /**
     * Peserta = percobaan yang benar-benar dibuat siswa pada sesi ini.
     * (Relasi ini dipakai `showSession()`/view; tanpa ini `count() on null` -> 500.)
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Result::class, 'tryout_session_id');
    }

    public function isOngoing(): bool
    {
        return $this->status === 'ongoing' &&
               now()->between($this->tanggal_mulai, $this->tanggal_selesai);
    }

    /**
     * Siswa boleh memulai selama sesi berjalan dan ia berada pada kelas/rombel
     * sasaran. Percobaan yang belum selesai dilanjutkan (resume); percobaan baru
     * setelah selesai menjadi attempt berikutnya (tidak menimpa).
     */
    public function canStudentTake($siswaId): bool
    {
        if (!$this->isOngoing()) return false;

        $siswa = \App\Models\Siswa::find($siswaId);
        if (!$siswa) return false;

        if ($this->kelas && $siswa->kelas !== $this->kelas) return false;
        if ($this->rombel_id && (int) $siswa->rombel_id !== (int) $this->rombel_id) return false;

        return true;
    }
}
