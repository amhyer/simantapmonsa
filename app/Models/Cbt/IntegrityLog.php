<?php

namespace App\Models\Cbt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrityLog extends Model
{
    protected $table = 'cbt_integrity_logs';
    protected $fillable = [
        'test_result_id',
        'siswa_id',
        'jenis_pelanggaran',
        'timestamp',
        'deskripsi',
        'data_tambahan'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'data_tambahan' => 'array',
    ];

    public function testResult(): BelongsTo
    {
        return $this->belongsTo(TestResult::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }
}
