<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrangTuaSiswa extends Model
{
    protected $table = 'orang_tua_siswa';
    protected $fillable = [
        'siswa_id', 'tipe', 'nama', 'nik', 'tahun_lahir',
        'pendidikan', 'pekerjaan', 'penghasilan', 'no_telepon',
        'email', 'alamat', 'status_hidup',
    ];

    protected $casts = [
        'tahun_lahir' => 'integer',
        'status_hidup' => 'boolean',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
