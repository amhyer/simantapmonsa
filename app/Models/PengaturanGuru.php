<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
