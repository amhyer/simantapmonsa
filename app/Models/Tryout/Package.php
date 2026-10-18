<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Package extends Model
{
    use SoftDeletes;

    protected $table = 'tryout_packages';
    protected $fillable = [
        'uuid', 'category_id', 'kode_paket', 'nama_paket',
        'deskripsi', 'jenjang', 'tahun_paket', 'jumlah_mapel',
        'durasi_total_menit', 'passing_grade', 'standar_nilai', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'passing_grade' => 'float',
        'standar_nilai' => 'array',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'package_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'package_id');
    }
}
