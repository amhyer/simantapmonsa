<?php

namespace App\Http\Requests\Tryout;

use Illuminate\Foundation\Http\FormRequest;

class StoreTryoutAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $result = $this->route('result');

        return $result !== null && (bool) $this->user()?->can('answer', $result);
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'exists:tryout_subjects,id'],
            'question_id' => ['required', 'exists:tryout_questions,id'],
            'jawaban' => ['required', 'string'],
        ];
    }
}
