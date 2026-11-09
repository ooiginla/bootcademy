<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCompleteProgrammeEnrollmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();

        return [
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:200'],
            'enrollment_ids.*' => [
                'integer', 'distinct',
                Rule::exists('enrollments', 'id')->where(fn (Builder $query) => $query->where('academy_id', $academyId)),
            ],
        ];
    }
}
