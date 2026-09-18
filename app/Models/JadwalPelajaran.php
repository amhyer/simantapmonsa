<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPelajaran extends Model
{
    protected $table = 'jadwal_pelajaran';
    protected $fillable = [
        'guru_id', 'dapodik_id', 'semester_id', 'rombel_id',
        'kelas', 'mata_pelajaran', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan',
    ];

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class);
    }
}
