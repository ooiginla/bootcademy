<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\AcademyMembership;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademyMemberRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_ids' => is_array($this->input('branch_ids')) ? $this->input('branch_ids') : [],
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $membership = $this->route('membership');

        abort_unless(
            $membership instanceof AcademyMembership
            && $membership->academy_id === $context->id(),
            404,
        );

        return $this->user()?->can('manage', $context->academy()) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'role' => ['required', Rule::enum(AcademyRole::class)->only(AcademyRole::staffRoles())],
            'status' => ['required', Rule::enum(MembershipStatus::class)->only([
                MembershipStatus::Active,
                MembershipStatus::Suspended,
            ])],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->withoutTrashed()->where($forAcademy)],
        ];
    }
}
