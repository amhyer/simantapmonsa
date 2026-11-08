<?php

namespace App\Models;

use App\Modules\Tenant\Traits\HasSchoolOwnership;
use Illuminate\Database\Eloquent\Model;

class PengajuanIzinPresensi extends Model
{
    use HasSchoolOwnership;

    protected $table = 'pengajuan_izin_presensi';

    protected $fillable = ['siswa_id', 'orang_tua_id', 'tanggal', 'jenis', 'alasan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'diperiksa_at' => 'datetime'];
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function orangTua()
    {
        return $this->belongsTo(User::class, 'orang_tua_id')->forSchool();
    }
}
