<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $authPasswordName = 'kata_sandi';
    protected $table = 'users';
    protected $fillable = [
        'uuid',
        'nama_lengkap',
        'nama_pengguna',
        'kata_sandi',
        'peran',
        'terhubung_dengan',
        'kelas_mata_pelajaran',
        'aktif',
        'terakhir_masuk',
        'force_password_change',
        'archived_at',
        'dapodik_id',
    ];

    protected $hidden = [
        'kata_sandi',
        'remember_token',
        'terhubung_dengan',
    ];

    protected function casts(): array
    {
        return [
            'kata_sandi' => 'hashed',
            'terhubung_dengan' => 'array',
            'aktif' => 'boolean',
            'terakhir_masuk' => 'datetime',
            'force_password_change' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) $model->uuid = Str::uuid();
        });
    }

    public function getAuthPassword()
    {
        return $this->kata_sandi;
    }

    public function siswa()
    {
        return $this->hasMany(Siswa::class, 'guru_id');
    }

    public function materi()
    {
        return $this->hasMany(Materi::class, 'guru_id');
    }

    public function kuis()
    {
        return $this->hasMany(Kuis::class, 'guru_id');
    }

    public function pengaturanGuru()
    {
        return $this->hasOne(PengaturanGuru::class, 'guru_id');
    }

    public function ringkasanGuru()
    {
        return $this->hasOne(RingkasanGuru::class, 'guru_id');
    }
}
