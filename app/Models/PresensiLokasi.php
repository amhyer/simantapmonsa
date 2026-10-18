<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresensiLokasi extends Model
{
    protected $table = 'presensi_lokasi';

    protected $fillable = [
        'nama',
        'latitude',
        'longitude',
        'radius_meter',
        'akurasi_maksimal_meter',
        'aktif',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meter' => 'integer',
            'akurasi_maksimal_meter' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
