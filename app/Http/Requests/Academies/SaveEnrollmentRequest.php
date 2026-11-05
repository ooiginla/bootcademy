<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Player;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEnrollmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['branch_id' => $this->input('branch_id') ?: null]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $player = $this->route('player');
        $enrollment = $this->route('enrollment');

        abort_unless($player instanceof Player && $player->academy_id === $context->id(), 404);
        if ($enrollment instanceof Enrollment) {
            abort_unless($enrollment->academy_id === $context->id() && $enrollment->player_id === $player->id, 404);
        }

        return $context->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'programme_id' => ['required', 'integer', Rule::exists('programmes', 'id')->where($forAcademy)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where($forAcademy)],
            'status' => ['required', Rule::enum(EnrollmentStatus::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
