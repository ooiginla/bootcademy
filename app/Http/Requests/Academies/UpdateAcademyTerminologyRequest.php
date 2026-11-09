<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademyTerminologyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $context = app(AcademyContext::class);
        $this->merge([
            'member_label_singular' => trim((string) $this->input('member_label_singular')),
            'member_label_plural' => trim((string) $this->input('member_label_plural')),
            'staff_label_singular' => trim((string) $this->input('staff_label_singular', $context->academy()->staff_label_singular)),
            'staff_label_plural' => trim((string) $this->input('staff_label_plural', $context->academy()->staff_label_plural)),
            'guardian_label_singular' => trim((string) $this->input('guardian_label_singular', $context->academy()->guardian_label_singular)),
            'guardian_label_plural' => trim((string) $this->input('guardian_label_plural', $context->academy()->guardian_label_plural)),
            'collect_guardian_information' => $this->has('collect_guardian_information')
                ? $this->boolean('collect_guardian_information')
                : $context->academy()->collect_guardian_information,
        ]);
    }

    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'member_label_singular' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-\'\/]*$/u'],
            'member_label_plural' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-\'\/]*$/u'],
            'staff_label_singular' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-\'\/]*$/u'],
            'staff_label_plural' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-\'\/]*$/u'],
            'guardian_label_singular' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-\'\/]*$/u'],
            'guardian_label_plural' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-\'\/]*$/u'],
            'collect_guardian_information' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'member_label_singular.regex' => 'Use letters, numbers, spaces, hyphens, apostrophes or slashes only.',
            'member_label_plural.regex' => 'Use letters, numbers, spaces, hyphens, apostrophes or slashes only.',
            'staff_label_singular.regex' => 'Use letters, numbers, spaces, hyphens, apostrophes or slashes only.',
            'staff_label_plural.regex' => 'Use letters, numbers, spaces, hyphens, apostrophes or slashes only.',
            'guardian_label_singular.regex' => 'Use letters, numbers, spaces, hyphens, apostrophes or slashes only.',
            'guardian_label_plural.regex' => 'Use letters, numbers, spaces, hyphens, apostrophes or slashes only.',
        ];
    }
}
