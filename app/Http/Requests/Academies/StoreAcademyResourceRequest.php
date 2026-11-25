<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreAcademyResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(AcademyContext::class);

        return $this->user()?->can('manage', $context->academy()) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'resource' => [
                'required',
                File::types([
                    'jpg', 'jpeg', 'png', 'gif', 'webp',
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv',
                    'ppt', 'pptx', 'txt',
                ])->max(20 * 1024),
            ],
        ];
    }
}
