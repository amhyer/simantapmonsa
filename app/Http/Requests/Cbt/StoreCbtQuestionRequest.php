<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StoreCbtQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bank = $this->route('bank');
        $question = $this->route('question');

        if ($bank !== null) {
            return (bool) $this->user()?->can('update', $bank);
        }

        return $question !== null && (bool) $this->user()?->can('update', $question);
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:10000'],
            'options' => ['required', 'array:A,B,C,D', 'size:4'],
            'options.A' => ['required', 'string', 'max:2000'],
            'options.B' => ['required', 'string', 'max:2000'],
            'options.C' => ['required', 'string', 'max:2000'],
            'options.D' => ['required', 'string', 'max:2000'],
            'correct_answer' => ['required', 'in:A,B,C,D'],
        ];
    }
}
