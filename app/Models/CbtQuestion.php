<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtQuestion extends Model
{
    protected $table = 'cbt_questions';
    protected $fillable = ['id', 'bank_id', 'prompt', 'options', 'correct_answer'];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Illuminate\Support\Carbon::instance($date)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    protected $hidden = ['correct_answer'];
    public function bank() { return $this->belongsTo(CbtQuestionBank::class, 'bank_id'); }
}
