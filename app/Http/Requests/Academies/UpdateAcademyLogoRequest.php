<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateAcademyLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $academy = app(AcademyContext::class)->academy();

        return $this->user()?->can('manage', $academy) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(2 * 1024)
                    ->dimensions(Rule::dimensions()->maxWidth(2048)->maxHeight(2048)),
            ],
        ];
    }
}
