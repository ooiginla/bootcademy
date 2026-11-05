<?php

namespace App\Http\Requests\Academies;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'academy_type' => ['required', 'string', 'max:80'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ];
    }
}
