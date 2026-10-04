<?php

namespace App\Models;

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

    protected static function booted(): void
    {
        // Bust cache getKKM() setiap pengaturan berubah.
        $flush = function (PengaturanGuru $pengaturan) {
            if ($pengaturan->guru_id) {
                Cache::forget('kkm-guru-' . $pengaturan->guru_id);
            }
            Cache::forget('kkm-global');
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
