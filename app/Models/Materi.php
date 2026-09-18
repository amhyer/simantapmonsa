<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Materi extends Model
{
    use SoftDeletes;
    protected $table = 'materi';
    protected $fillable = [
        'uuid',
        'guru_id',
        'nama_guru',
        'tanggal',
        'judul',
        'mata_pelajaran',
        'kelas',
        'tujuan_pembelajaran',
        'materi_pokok',
        'kegiatan',
        'tautan_sumber',
        'pm',
        'dimensi',
        'pengalaman',
        'sematkan',
        'rekaman',
    ];

    protected function casts(): array
    {
        return [
            'pm' => 'array',
            'dimensi' => 'array',
            'pengalaman' => 'array',
            'rekaman' => 'array',
            'sematkan' => 'boolean',
            'tanggal' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) $model->uuid = Str::uuid();
        });
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function kuis()
    {
        return $this->hasMany(Kuis::class, 'materi_id');
    }
}
