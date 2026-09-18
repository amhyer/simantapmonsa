<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $table = 'semesters';
    protected $fillable = ['semester_id', 'tahun_ajaran', 'nama_semester'];

    public function siswa()
    {
        return $this->hasMany(Siswa::class);
    }

    public function ptk()
    {
        return $this->hasMany(Ptk::class);
    }

    public function rombel()
    {
        return $this->hasMany(Rombel::class);
    }

    public function jadwalPelajaran()
    {
        return $this->hasMany(JadwalPelajaran::class);
    }

    public function getLabelAttribute(): string
    {
        return "{$this->tahun_ajaran} {$this->nama_semester}";
    }
}
