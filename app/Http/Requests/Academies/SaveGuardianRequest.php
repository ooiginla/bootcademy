<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveGuardianRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $email = mb_strtolower(trim((string) $this->input('email')));

        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => $email === '' ? null : $email,
            'phone' => trim((string) $this->input('phone')),
            'whatsapp_number' => $this->nullableString('whatsapp_number'),
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $guardian = $this->route('guardian');

        if ($guardian instanceof Guardian) {
            abort_unless($guardian->academy_id === $context->id(), 404);
        }

        return $context->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'max:32'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'signature' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2 * 1024)
                    ->dimensions(Rule::dimensions()->maxWidth(3000)->maxHeight(3000)),
            ],
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
