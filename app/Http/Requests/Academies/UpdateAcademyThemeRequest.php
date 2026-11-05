<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademyThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $academy = app(AcademyContext::class)->academy();

        return $this->user()?->can('manage', $academy) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
