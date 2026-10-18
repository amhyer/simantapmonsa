<?php

namespace App\Models\Tka;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ParentReport extends Model
{
    protected $table = 'tka_parent_reports';
    protected $fillable = [
        'uuid', 'assessment_id', 'siswa_id', 'ortu_user_id',
        'dibaca_oleh_ortu', 'tanggal_dibaca', 'rekomendasi_rumah',
        'aktivitas_suggested'
    ];

    protected $casts = [
        'dibaca_oleh_ortu' => 'boolean',
        'tanggal_dibaca' => 'datetime',
        'aktivitas_suggested' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Siswa::class);
    }

    public function ortuUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'ortu_user_id');
    }
}
