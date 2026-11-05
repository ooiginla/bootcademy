<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\Programme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateProgrammeImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $programme = $this->route('programme');

        if ($programme instanceof Programme) {
            abort_unless($programme->academy_id === $context->id(), 404);
        }

        return $programme instanceof Programme
            && $this->user()?->can('manage', $context->academy()) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024)
                    ->dimensions(Rule::dimensions()->maxWidth(4096)->maxHeight(4096)),
            ],
        ];
    }
}
