<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DapodikImportLog extends Model
{
    protected $table = 'dapodik_import_logs';
    protected $fillable = [
        'user_id', 'sekolah_id', 'nama_sekolah',
        'total_siswa', 'berhasil', 'gagal', 'diperbarui',
        'errors', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
