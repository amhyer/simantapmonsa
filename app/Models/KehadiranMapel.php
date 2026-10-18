<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KehadiranMapel extends Model
{
    protected $table = 'kehadiran_mapel';

    protected $fillable = [
        'uuid',
        'jadwal_pelajaran_id',
        'siswa_id',
        'guru_id',
        'tanggal',
        'mata_pelajaran',
        'kelas',
        'status',
        'waktu_hadir',
        'metode',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'waktu_hadir' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function jadwalPelajaran()
    {
        return $this->belongsTo(JadwalPelajaran::class, 'jadwal_pelajaran_id');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}
