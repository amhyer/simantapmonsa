<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenant\Traits\HasSchoolOwnership;

class Ptk extends Model
{
    use HasSchoolOwnership;

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
        $jenis = strtolower($this->jenis_ptk ?? '');
        $jabatan = strtolower($this->jabatan ?? '');
        return in_array($jenis, ['guru', 'guru bk', 'gurubk', 'guru kelas', 'guru mapel', 'guru pendamping', 'guru gpk'])
            || str_contains($jenis, 'guru')
            || str_contains($jabatan, 'guru');
    }

    public function isGpk(): bool
    {
        $jenis = strtolower($this->jenis_ptk ?? '');
        $jabatan = strtolower($this->jabatan ?? '');
        return str_contains($jenis, 'gpk') || str_contains($jabatan, 'gpk');
    }

    public function isKebersihan(): bool
    {
        $jenis = strtolower($this->jenis_ptk ?? '');
        $jabatan = strtolower($this->jabatan ?? '');
        return str_contains($jenis, 'kebersihan') || str_contains($jabatan, 'kebersihan');
    }

    public function isSecurity(): bool
    {
        $jenis = strtolower($this->jenis_ptk ?? '');
        $jabatan = strtolower($this->jabatan ?? '');
        return str_contains($jenis, 'security')
            || str_contains($jenis, 'satpam')
            || str_contains($jenis, 'keamanan')
            || str_contains($jabatan, 'security')
            || str_contains($jabatan, 'satpam')
            || str_contains($jabatan, 'keamanan');
    }

    public function isKepsek(): bool
    {
        $jenis = strtolower($this->jenis_ptk ?? '');
        $jabatan = strtolower($this->jabatan ?? '');

        return str_contains($jenis, 'kepala sekolah')
            || str_contains($jabatan, 'kepala sekolah')
            || str_contains($jabatan, 'plt')
            || str_contains($jabatan, 'pjs')
            || str_contains($jabatan, 'pelaksana tugas');
    }
}
