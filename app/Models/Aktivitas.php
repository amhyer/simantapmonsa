<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Aktivitas extends Model
{
    protected $table = 'aktivitas';
    protected $hidden = ['data_lama', 'data_baru'];

    protected $fillable = [
        'uuid',
        'guru_id',
        'nama_guru',
        'jenis',
        'judul',
        'deskripsi',
        'tabel_terkait',
        'record_id',
        'data_lama',
        'data_baru',
    ];

    protected function casts(): array
    {
        return [
            'data_lama' => 'array',
            'data_baru' => 'array',
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
}
