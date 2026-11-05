<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Academies\AcademyWebsiteDefaults;
use App\Models\AcademyWebsite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademyWebsiteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => mb_strtolower(trim((string) $this->input('subdomain'))),
            'is_published' => $this->boolean('is_published'),
        ]);
    }

    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $context = app(AcademyContext::class);
        $websiteId = AcademyWebsite::query()->where('academy_id', $context->id())->value('id');
        $definitions = AcademyWebsiteDefaults::definitions();
        $rules = [
            'subdomain' => [
                'required', 'string', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
                Rule::notIn(['www', 'app', 'api', 'admin', 'platform', 'mail', 'support', 'status']),
                Rule::unique('academy_websites', 'subdomain')->ignore($websiteId),
            ],
            'theme_key' => ['required', Rule::in(['launch'])],
            'is_published' => ['required', 'boolean'],
            'sections' => ['required', 'array:'.implode(',', array_keys($definitions))],
        ];

        foreach ($definitions as $key => $section) {
            $fieldKeys = ['enabled', 'title', ...array_keys($section['fields'])];
            $rules["sections.$key"] = ['required', 'array:'.implode(',', $fieldKeys)];
            $rules["sections.$key.enabled"] = ['required', 'boolean'];
            $rules["sections.$key.title"] = ['required', 'string', 'max:160'];
            foreach ($section['fields'] as $fieldKey => $field) {
                $fieldRules = ['nullable', 'string', 'max:20000'];
                if ($field['type'] === 'select') {
                    $fieldRules[] = Rule::in(array_keys($field['options']));
                }
                $rules["sections.$key.$fieldKey"] = $fieldRules;
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'subdomain.regex' => 'Use only lowercase letters, numbers and hyphens. The subdomain cannot start or end with a hyphen.',
            'subdomain.not_in' => 'That subdomain is reserved by Bootcademy.',
        ];
    }
}
