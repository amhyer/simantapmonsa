<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtResult extends Model
{
    protected $table = 'cbt_results';
    protected $fillable = ['id', 'session_id', 'siswa_id', 'user_id', 'started_at', 'deadline_at', 'submitted_at', 'answers', 'correct_count', 'score'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'deadline_at' => 'datetime', 'submitted_at' => 'datetime', 'answers' => 'array', 'score' => 'float'];
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Illuminate\Support\Carbon::instance($date)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function session() { return $this->belongsTo(CbtTestSession::class, 'session_id'); }
    public function siswa() { return $this->belongsTo(Siswa::class, 'siswa_id'); }
    public function violations() { return $this->hasMany(CbtViolation::class, 'result_id'); }
}
