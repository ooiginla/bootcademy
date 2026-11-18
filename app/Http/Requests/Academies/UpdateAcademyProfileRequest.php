<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Support\AcademyLocaleOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademyProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'academy_type' => trim((string) $this->input('academy_type')),
            'country_code' => strtoupper(trim((string) $this->input('country_code'))),
            'currency' => strtoupper(trim((string) $this->input('currency'))),
        ]);
    }

    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', 'regex:/^[^\pC<>]+$/u'],
            'academy_type' => ['required', 'string', 'max:80', 'regex:/^[^\pC<>]+$/u'],
            'country_code' => ['required', 'string', 'size:2', Rule::in(AcademyLocaleOptions::countryCodes())],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', Rule::in(AcademyLocaleOptions::currencyCodes())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'The academy name cannot contain markup or control characters.',
            'academy_type.regex' => 'The academy type cannot contain markup or control characters.',
        ];
    }
}
