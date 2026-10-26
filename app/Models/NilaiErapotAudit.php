<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiErapotAudit extends Model
{
    protected $table = 'nilai_erapor_audit_logs';

    protected $fillable = [
        'nilai_erapor_id',
        'siswa_id',
        'guru_id',
        'aksi',
        'status_lama',
        'status_baru',
        'snapshot',
        'prev_hash',
        'hash',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function nilaiErapot(): BelongsTo
    {
        return $this->belongsTo(NilaiErapot::class, 'nilai_erapor_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}
