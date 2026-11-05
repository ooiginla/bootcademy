<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadPlayerImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'csv_file' => ['required', File::types(['csv', 'txt'])->max(2 * 1024)],
        ];
    }
}
