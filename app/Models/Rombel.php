<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Rombel extends Model
{
    use BelongsToTenant;

    protected $table = 'rombel';
    protected $fillable = [
        'tenant_id', 'dapodik_id', 'semester_id', 'nama_rombel', 'tingkat', 'kurikulum', 'jenis_rombel', 'guru_id', 'kunci_input_rapor',
    ];

    protected function casts(): array
    {
        return [
            'kunci_input_rapor' => 'boolean',
        ];
    }

    public static function apakahTerkunciUntukKelas(?string $kelas): bool
    {
        if ($kelas === null || trim($kelas) === '') {
            return false;
        }

        return static::where('nama_rombel', $kelas)
            ->where('kunci_input_rapor', true)
            ->exists();
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function siswa()
    {
        return $this->hasMany(Siswa::class, 'rombel_id');
    }

    public function jadwalPelajaran()
    {
        return $this->hasMany(JadwalPelajaran::class);
    }
}
