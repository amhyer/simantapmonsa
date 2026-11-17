<?php

namespace App\Http\Requests\Admin\Cbt;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CbtBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects these routes.
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:100'],
            'guru_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Memakai User::forSchool() agar isolasi tenant dan SoftDeletes otomatis berlaku.
                    $valid = User::forSchool()->where('peran', 'guru')->whereKey((int) $value)->exists();
                    if (! $valid) {
                        $fail('Guru pemilik harus berstatus guru pada sekolah ini.');
                    }
                },
            ],
        ];
    }
}
