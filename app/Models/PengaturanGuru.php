<?php

namespace App\Models;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PengaturanGuru extends Model
{
    protected $table = 'pengaturan_guru';

    protected $fillable = [
        'guru_id',
        'nama_guru',
        'mata_pelajaran',
        'kelas',
        'kkm',
        'semester',
        'tahun_pelajaran',
        'fase',
        'pembaruan',
        'pengaturan',
        'sistem',
    ];

    protected function casts(): array
    {
        return [
            'pengaturan' => 'array',
            'sistem' => 'array',
            'pembaruan' => 'datetime',
        ];
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    /**
     * Batasi pengaturan guru hanya ke guru milik sekolah (tenant) aktif.
     * Di mode sekolah tunggal, User::scopeForSchool() menyaring guru non-tenant.
     */
    public function scopeForSchool(Builder $query): Builder
    {
        return $query->whereHas('guru', fn (Builder $guru) => $guru->forSchool());
    }

    protected static function booted(): void
    {
        // Bust cache getKKM() (termasuk cache per-tenant) setiap pengaturan berubah.
        $flush = function (PengaturanGuru $pengaturan): void {
            $tenantId = TenantContext::check()
                ? TenantContext::id()
                : ($pengaturan->guru_id ? User::whereKey($pengaturan->guru_id)->value('tenant_id') : null);

            if (function_exists('forgetKKMCache')) {
                forgetKKMCache($pengaturan->guru_id ? (int) $pengaturan->guru_id : null, $tenantId !== null ? (int) $tenantId : null);
            } else {
                if ($pengaturan->guru_id) {
                    Cache::forget('kkm-guru-'.$pengaturan->guru_id);
                }
                Cache::forget('kkm-global');
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
