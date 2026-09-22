<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ptk extends Model
{
    protected $table = 'ptk';
    protected $fillable = [
        'dapodik_id', 'semester_id', 'nama', 'nip', 'nuptk', 'jk',
        'gelar_depan', 'gelar_belakang', 'jenis_ptk', 'jabatan',
    ];

    public function getNamaGelarAttribute(): string
    {
        $nama = trim(($this->gelar_depan ? $this->gelar_depan . ' ' : '') . ($this->nama ?? ''));
        if ($this->gelar_belakang) {
            $nama .= ', ' . $this->gelar_belakang;
        }
        return $nama;
    }

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
