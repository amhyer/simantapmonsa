<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TanggalRapor extends Model
{
    protected $table = 'tanggal_rapor';

    protected $fillable = [
        'tahun_ajaran', 'semester', 'tanggal', 'tempat', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
}
