<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtTestSession extends Model
{
    protected $table = 'cbt_test_sessions';
    protected $fillable = ['id', 'guru_id', 'bank_id', 'title', 'kelas', 'opens_at', 'closes_at', 'duration_minutes', 'status', 'question_snapshot', 'participant_ids'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'duration_minutes' => 'integer', 'question_snapshot' => 'array', 'participant_ids' => 'array'];
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Illuminate\Support\Carbon::instance($date)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    protected $hidden = ['question_snapshot', 'participant_ids'];
    public function bank() { return $this->belongsTo(CbtQuestionBank::class, 'bank_id'); }
    public function results() { return $this->hasMany(CbtResult::class, 'session_id'); }
}
