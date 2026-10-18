<?php

namespace App\Models\Tryout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $table = 'tryout_categories';
    protected $fillable = [
        'uuid', 'nama_kategori', 'deskripsi', 'jenis', 'aktif'
    ];

    protected $casts = [
        'aktif' => 'boolean',
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

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'category_id');
    }
}
