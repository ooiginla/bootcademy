<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Academies\AttendanceAccess;
use App\Models\TrainingSession;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAttendanceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['attendance' => is_array($this->input('attendance')) ? array_values($this->input('attendance')) : []]);
    }

    public function authorize(): bool
    {
        $session = $this->route('trainingSession');

        if ($session instanceof TrainingSession && $session->academy_id !== app(AcademyContext::class)->id()) {
            abort(404);
        }

        return $session instanceof TrainingSession && AttendanceAccess::allowed($session, app(AcademyContext::class));
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();

        return [
            'attendance' => ['required', 'array', 'max:2000'],
            'attendance.*.player_id' => ['required', 'integer', 'distinct', Rule::exists('players', 'id')->withoutTrashed()->where(fn (Builder $query) => $query->where('academy_id', $academyId))],
            'attendance.*.status' => ['nullable', Rule::in(['present', 'absent', 'late', 'excused'])],
            'attendance.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
