<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\Branch;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBranchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $nullable = ['code', 'address_line', 'city', 'state_region', 'country_code', 'contact_email', 'contact_phone'];

        $this->merge(collect($nullable)->mapWithKeys(function (string $key): array {
            $value = trim((string) $this->input($key));

            return [$key => $value === '' ? null : $value];
        })->all());

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code') ? mb_strtoupper((string) $this->input('code')) : null,
            'country_code' => $this->input('country_code')
                ? mb_strtoupper((string) $this->input('country_code'))
                : null,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $branch = $this->route('branch');

        if ($branch instanceof Branch) {
            abort_unless($branch->academy_id === $context->id(), 404);
        }

        return $this->user()?->can('manage', $context->academy()) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $context = app(AcademyContext::class);
        $branch = $this->route('branch');
        $branchId = $branch instanceof Branch ? $branch->id : null;
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $context->id());

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('branches')->withoutTrashed()->where($forAcademy)->ignore($branchId)],
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('branches')->withoutTrashed()->where($forAcademy)->ignore($branchId)],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state_region' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'timezone' => ['required', 'timezone'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
