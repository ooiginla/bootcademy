<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Concerns\PasswordValidationRules;
use App\Enums\AcademyRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAcademyMemberRequest extends FormRequest
{
    use PasswordValidationRules;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'branch_ids' => is_array($this->input('branch_ids')) ? $this->input('branch_ids') : [],
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage', app(AcademyContext::class)->academy()) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(AcademyRole::class)->only(AcademyRole::staffRoles())],
            'password' => $this->passwordRules(),
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where($forAcademy)],
        ];
    }
}
