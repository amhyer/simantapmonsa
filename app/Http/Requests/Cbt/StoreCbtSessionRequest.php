<?php

namespace App\Http\Requests\Cbt;

use App\Models\Siswa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCbtSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route role middleware controls who can create sessions.
    }

    public function rules(): array
    {
        $teacherId = auth()->id();
        $classes = Siswa::query()
            ->where('guru_id', $teacherId)
            ->where('aktif', true)
            ->whereNull('archived_at')
            ->distinct()
            ->pluck('kelas')
            ->all();

        return [
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('cbt_question_banks', 'id')->where('guru_id', $teacherId),
            ],
            'title' => ['required', 'string', 'max:200'],
            'kelas' => ['required', 'string', Rule::in($classes)],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
        ];
    }
}
