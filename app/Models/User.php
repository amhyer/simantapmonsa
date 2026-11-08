<?php

namespace App\Models;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'email',
        'kata_sandi',
        'peran',
        'terhubung_dengan',
        'kelas_mata_pelajaran',
        'aktif',
        'terakhir_masuk',
        'force_password_change',
        'archived_at',
        'dapodik_id',
        'tenant_id',
        'nip',
        'nik',
        'foto',
        'qr_card_version',
        'qr_previous_token_hash',
        'printed_at',
        'printed_version',
    ];

    protected $hidden = [
        'kata_sandi',
        'remember_token',
        'terhubung_dengan',
        'qr_token_hash',
        'qr_previous_token_hash',
        'qr_generation_nonce',
    ];

    protected function casts(): array
    {
        return [
            'kata_sandi' => 'hashed',
            'terhubung_dengan' => 'array',
            'aktif' => 'boolean',
            'terakhir_masuk' => 'datetime',
            'force_password_change' => 'boolean',
            'qr_card_version' => 'integer',
            'printed_version' => 'integer',
            'printed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::saving(function ($model) {
            if ($model->tenant_id !== null) $model->tenant_owned = true;
        });
        static::creating(function ($model) {
            if (!$model->uuid) $model->uuid = Str::uuid();
            if (empty($model->tenant_id) && TenantContext::check()) {
                $model->tenant_id = TenantContext::id();
            }
            if ($model->tenant_id !== null) $model->tenant_owned = true;
        });
    }

    public function scopeForSchool(Builder $query): Builder
    {
        if (TenantContext::check()) {
            return $query->where('users.tenant_id', TenantContext::id());
        }
        if (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
            return $query->whereRaw('1 = 0');
        }
        return $query->whereNull('users.tenant_id')->where('users.tenant_owned', false);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
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

    public function presensiPegawai()
    {
        return $this->hasMany(PresensiPegawai::class, 'user_id');
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
