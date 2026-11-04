<?php

namespace App\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CbtQuestionBank extends Model
{
    use BelongsToTenant;

    protected $table = 'cbt_question_banks';
    protected $fillable = ['id', 'tenant_id', 'guru_id', 'title', 'subject'];

    protected function casts(): array
    {
        return [];
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Illuminate\Support\Carbon::instance($date)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function guru() { return $this->belongsTo(User::class, 'guru_id'); }
    public function questions() { return $this->hasMany(CbtQuestion::class, 'bank_id'); }
    public function sessions() { return $this->hasMany(CbtTestSession::class, 'bank_id'); }
}
