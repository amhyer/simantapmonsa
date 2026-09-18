<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RingkasanGuru extends Model
{
    protected $table = 'ringkasan_guru';
    protected $fillable = [
        'guru_id',
        'nama_guru',
        'mata_pelajaran',
        'kelas',
        'kkm',
        'semester',
        'tahun_pelajaran',
        'jumlah_siswa',
        'rata_rata',
        'tuntas',
        'belum_tuntas',
        'ketuntasan_persen',
        'kehadiran_persen',
        'rata_tugas',
        'rata_ulangan_harian',
        'rata_praktik',
        'rata_pts',
        'rata_pas',
        'predikat_a',
        'predikat_b',
        'predikat_c',
        'predikat_d',
        'belum_dinilai',
        'hadir',
        'sakit',
        'izin',
        'alpa',
        'dimensi',
        'kaih',
        'kegiatan',
        'pembaruan',
    ];

    protected function casts(): array
    {
        return [
            'dimensi' => 'array',
            'kaih' => 'array',
            'kegiatan' => 'array',
            'pembaruan' => 'datetime',
        ];
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}
