<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ptk extends Model
{
    protected $table = 'ptk';
    protected $fillable = [
        'dapodik_id', 'semester_id', 'nama', 'nip', 'jenis_ptk', 'jabatan',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function isGuru(): bool
    {
        return in_array(strtolower($this->jenis_ptk), ['guru', 'guru bk', 'gurubk']);
    }

    public function isKepsek(): bool
    {
        return str_contains(strtolower($this->jenis_ptk), 'kepala sekolah');
    }
}
