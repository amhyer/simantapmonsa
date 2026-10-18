<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtViolation extends Model
{
    protected $table = 'cbt_violations';
    protected $fillable = ['id', 'result_id', 'type'];

    protected function casts(): array
    {
        return [];
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Illuminate\Support\Carbon::instance($date)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function result() { return $this->belongsTo(CbtResult::class, 'result_id'); }
}
