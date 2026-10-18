<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresensiPegawai extends Model
{
    protected $table = 'presensi_pegawai';

    protected $fillable = [
        'user_id',
        'tanggal',
        'status',
        'waktu_masuk',
        'masuk_latitude',
        'masuk_longitude',
        'masuk_akurasi_meter',
        'masuk_lokasi_id',
        'waktu_pulang',
        'pulang_latitude',
        'pulang_longitude',
        'pulang_akurasi_meter',
        'pulang_lokasi_id',
        'metode',
        'diperbarui_oleh',
        'alasan_koreksi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'waktu_masuk' => 'datetime',
            'waktu_pulang' => 'datetime',
            'masuk_latitude' => 'float',
            'masuk_longitude' => 'float',
            'masuk_akurasi_meter' => 'float',
            'pulang_latitude' => 'float',
            'pulang_longitude' => 'float',
            'pulang_akurasi_meter' => 'float',
        ];
    }

    public function pegawai()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'diperbarui_oleh');
    }

    public function lokasiMasuk()
    {
        return $this->belongsTo(PresensiLokasi::class, 'masuk_lokasi_id');
    }

    public function lokasiPulang()
    {
        return $this->belongsTo(PresensiLokasi::class, 'pulang_lokasi_id');
    }
}
