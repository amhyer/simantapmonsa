<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ekstrakurikuler extends Model
{
    protected $table = 'ekstrakurikuler';

    protected $fillable = [
        'nama', 'pembina_id', 'deskripsi', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function pembina()
    {
        return $this->belongsTo(User::class, 'pembina_id');
    }
}
