<?php

namespace App\Http\Requests\Academies;

use App\Support\AcademyLocaleOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => strtoupper(trim((string) $this->input('country_code'))),
            'currency' => strtoupper(trim((string) $this->input('currency'))),
        ]);
    }

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
            'country_code' => ['required', 'string', 'size:2', Rule::in(AcademyLocaleOptions::countryCodes())],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', Rule::in(AcademyLocaleOptions::currencyCodes())],
        ];
    }
}
