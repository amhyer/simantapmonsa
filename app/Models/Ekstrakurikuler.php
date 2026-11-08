<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Traits\HasSchoolOwnership;

class Ekstrakurikuler extends Model
{
    use HasSchoolOwnership;

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
