<?php

namespace App\Http\Requests\Cbt;

use App\Models\Rombel;
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
            ->where('aktif', true)
            ->whereNull('archived_at')
            ->distinct()
            ->pluck('kelas')
            ->filter()
            ->values();

        if ($classes->isEmpty()) {
            $classes = Rombel::query()
                ->orderBy('nama_rombel')
                ->pluck('nama_rombel')
                ->unique()
                ->values();
        }

        return [
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('cbt_question_banks', 'id')->where('guru_id', $teacherId),
            ],
            'title' => ['required', 'string', 'max:200'],
            'kelas' => ['required', 'string', Rule::in($classes->all())],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
        ];
    }
}
