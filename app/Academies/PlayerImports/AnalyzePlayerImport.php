<?php

namespace App\Academies\PlayerImports;

use App\Enums\CustomFieldType;
use App\Enums\EnrollmentStatus;
use App\Enums\PlayerStatus;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\Guardian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnalyzePlayerImport
{
    public const MAX_ROWS = 500;

    /** @var list<string> */
    private const STANDARD_HEADERS = [
        'internal_identifier', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender',
        'phone', 'whatsapp_number', 'home_address', 'city', 'state_region', 'country', 'status',
        'guardian_first_name', 'guardian_last_name', 'guardian_email', 'guardian_phone',
        'guardian_whatsapp_number', 'guardian_relationship', 'programme', 'branch',
        'enrollment_status', 'enrollment_starts_on', 'enrollment_ends_on',
    ];

    public function handle(Academy $academy, string $path): PlayerImportAnalysis
    {
        $fields = $academy->customFields()->where('entity_type', 'player')->where('is_active', true)->get();
        $allowedHeaders = [...self::STANDARD_HEADERS, ...$fields->map(fn (AcademyCustomField $field): string => "custom_{$field->key}")->all()];
        [$headers, $rawRows, $fileErrors] = $this->readCsv($path, $allowedHeaders);
        if ($fileErrors !== []) {
            return new PlayerImportAnalysis($headers, [], $fileErrors);
        }

        $programmes = $academy->programmes()->where('is_active', true)->get()->keyBy(fn ($programme): string => mb_strtolower($programme->name));
        $branches = $academy->branches()->where('is_active', true)->get()->keyBy(fn ($branch): string => mb_strtolower($branch->name));
        $seenKeys = [];
        $rows = [];

        foreach ($rawRows as $rawRow) {
            $data = $this->normalizeRow($rawRow['values'], $fields);
            $errors = $this->validateRow($data, $fields);
            $this->resolveRelations($data, $errors, $programmes, $branches, $academy);
            $this->detectDuplicates($data, $errors, $seenKeys, $academy);
            $rows[] = [
                'line' => $rawRow['line'],
                'label' => trim("{$data['first_name']} {$data['last_name']}") ?: "Row {$rawRow['line']}",
                'data' => $data,
                'errors' => array_values(array_unique($errors)),
            ];
        }

        if ($rows === []) {
            $fileErrors[] = 'The CSV file does not contain any data rows.';
        }

        return new PlayerImportAnalysis($headers, $rows, $fileErrors);
    }

    /** @return list<string> */
    public function templateHeaders(Academy $academy): array
    {
        return array_values([
            ...self::STANDARD_HEADERS,
            ...$academy->customFields()->where('entity_type', 'player')->where('is_active', true)
                ->orderBy('sort_order')->pluck('key')->map(fn (string $key): string => "custom_{$key}")->all(),
        ]);
    }

    /** @param list<string> $allowedHeaders
     * @return array{list<string>, list<array{line: int, values: array<string, string>}>, list<string>}
     */
    private function readCsv(string $path, array $allowedHeaders): array
    {
        $stream = Storage::disk('local')->readStream($path);
        if (! is_resource($stream)) {
            return [[], [], ['The uploaded CSV file could not be read.']];
        }

        try {
            $first = fgetcsv($stream, null, ',', '"', '');
            if (! is_array($first)) {
                return [[], [], ['The CSV file is empty.']];
            }
            $headers = array_map(function (mixed $header, int $index): string {
                $value = trim((string) $header);
                if ($index === 0) {
                    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
                }

                return Str::snake($value);
            }, $first, array_keys($first));
            $errors = [];
            foreach (array_diff(['first_name', 'last_name'], $headers) as $missing) {
                $errors[] = "Missing required column: {$missing}.";
            }
            foreach (array_diff($headers, $allowedHeaders) as $unknown) {
                $errors[] = "Unknown column: {$unknown}.";
            }
            if (count(array_unique($headers)) !== count($headers)) {
                $errors[] = 'The CSV contains duplicate column names.';
            }
            if (count($headers) > 60) {
                $errors[] = 'The CSV contains too many columns.';
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null] || collect($values)->every(fn (mixed $value): bool => trim((string) $value) === '')) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    $errors[] = "Line {$line} has ".count($values).' values but the header has '.count($headers).'.';

                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    $errors[] = 'A single import is limited to '.self::MAX_ROWS.' data rows.';
                    break;
                }
                /** @var array<string, string> $combined */
                $combined = array_combine($headers, array_map(fn (mixed $value): string => trim((string) $value), $values));
                $rows[] = ['line' => $line, 'values' => $combined];
            }

            return [$headers, $rows, array_values(array_unique($errors))];
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string, string> $row
     * @param  Collection<int, AcademyCustomField>  $fields
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, Collection $fields): array
    {
        $value = fn (string $key): ?string => ($row[$key] ?? '') === '' ? null : trim($row[$key]);
        $custom = [];
        foreach ($fields as $field) {
            $raw = $value("custom_{$field->key}");
            if ($field->type === CustomFieldType::Boolean && $raw !== null) {
                $raw = match (mb_strtolower($raw)) {
                    '1', 'yes', 'true' => '1',
                    '0', 'no', 'false' => '0',
                    default => $raw,
                };
            }
            $custom[$field->key] = $raw;
        }

        return [
            'internal_identifier' => $value('internal_identifier') === null ? null : mb_strtoupper((string) $value('internal_identifier')),
            'first_name' => $value('first_name') ?? '', 'middle_name' => $value('middle_name'),
            'last_name' => $value('last_name') ?? '', 'date_of_birth' => $value('date_of_birth'),
            'gender' => $value('gender'), 'phone' => $value('phone'), 'whatsapp_number' => $value('whatsapp_number'),
            'home_address' => $value('home_address'), 'city' => $value('city'), 'state_region' => $value('state_region'),
            'country' => $value('country'), 'status' => mb_strtolower($value('status') ?? PlayerStatus::Active->value),
            'guardian_first_name' => $value('guardian_first_name'), 'guardian_last_name' => $value('guardian_last_name'),
            'guardian_email' => $value('guardian_email'), 'guardian_phone' => $value('guardian_phone'),
            'guardian_whatsapp_number' => $value('guardian_whatsapp_number'),
            'guardian_relationship' => $value('guardian_relationship') ?? 'Guardian',
            'programme' => $value('programme'), 'branch' => $value('branch'),
            'enrollment_status' => mb_strtolower($value('enrollment_status') ?? EnrollmentStatus::Active->value),
            'enrollment_starts_on' => $value('enrollment_starts_on'), 'enrollment_ends_on' => $value('enrollment_ends_on'),
            'custom_fields' => $custom, 'guardian_id' => null, 'programme_id' => null, 'branch_id' => null,
        ];
    }

    /** @param array<string, mixed> $data
     * @param  Collection<int, AcademyCustomField>  $fields
     * @return list<string>
     */
    private function validateRow(array $data, Collection $fields): array
    {
        $rules = [
            'internal_identifier' => ['nullable', 'string', 'max:64'], 'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])], 'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'], 'home_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'], 'state_region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', Rule::in(config('countries'))], 'status' => ['required', Rule::enum(PlayerStatus::class)],
            'guardian_first_name' => ['nullable', 'string', 'max:100'],
            'guardian_last_name' => ['required_with:guardian_first_name', 'nullable', 'string', 'max:100'],
            'guardian_email' => ['nullable', 'email:rfc', 'max:254'],
            'guardian_phone' => ['required_with:guardian_first_name', 'nullable', 'string', 'max:32'],
            'guardian_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'guardian_relationship' => ['nullable', 'string', 'max:80'],
            'enrollment_status' => ['required', Rule::enum(EnrollmentStatus::class)],
            'enrollment_starts_on' => ['required_with:programme', 'nullable', 'date_format:Y-m-d'],
            'enrollment_ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:enrollment_starts_on'],
        ];
        foreach ($fields as $field) {
            $rules["custom_fields.{$field->key}"] = [
                $field->is_required ? 'required' : 'nullable',
                ...match ($field->type) {
                    CustomFieldType::Text => ['string', 'max:500'], CustomFieldType::Textarea => ['string', 'max:5000'],
                    CustomFieldType::Number => ['numeric'], CustomFieldType::Date => ['date_format:Y-m-d'],
                    CustomFieldType::Boolean => ['boolean'], CustomFieldType::Select => [Rule::in($field->options ?? [])],
                },
            ];
        }

        $validator = Validator::make($data, $rules);

        return array_values($validator->errors()->all());
    }

    /** @param array<string, mixed> $data
     * @param  list<string>  $errors
     * @param  Collection<string, mixed>  $programmes
     * @param  Collection<string, mixed>  $branches
     */
    private function resolveRelations(array &$data, array &$errors, Collection $programmes, Collection $branches, Academy $academy): void
    {
        if ($data['programme'] !== null) {
            $programme = $programmes->get(mb_strtolower((string) $data['programme']));
            if ($programme === null) {
                $errors[] = "Programme '{$data['programme']}' was not found or is inactive.";
            } else {
                $data['programme_id'] = $programme->id;
            }
        } elseif ($data['branch'] !== null || $data['enrollment_starts_on'] !== null || $data['enrollment_ends_on'] !== null) {
            $errors[] = 'Programme is required when enrollment information is supplied.';
        }
        if ($data['branch'] !== null) {
            $branch = $branches->get(mb_strtolower((string) $data['branch']));
            if ($branch === null) {
                $errors[] = "Branch '{$data['branch']}' was not found or is inactive.";
            } else {
                $data['branch_id'] = $branch->id;
            }
        }

        if ($data['guardian_first_name'] !== null && ($data['guardian_phone'] !== null || $data['guardian_email'] !== null)) {
            $matches = Guardian::query()->where('academy_id', $academy->id)
                ->where(function ($query) use ($data): void {
                    if ($data['guardian_phone'] !== null) {
                        $query->orWhere('phone', $data['guardian_phone']);
                    }
                    if ($data['guardian_email'] !== null) {
                        $query->orWhere('email', $data['guardian_email']);
                    }
                })->limit(2)->get();
            if ($matches->count() > 1) {
                $errors[] = 'Guardian phone/email matches more than one existing guardian.';
            } elseif ($matches->count() === 1) {
                $data['guardian_id'] = $matches->first()->id;
            }
        }
    }

    /** @param array<string, mixed> $data
     * @param  list<string>  $errors
     * @param  array<string, true>  $seenKeys
     */
    private function detectDuplicates(array $data, array &$errors, array &$seenKeys, Academy $academy): void
    {
        $identifier = $data['internal_identifier'];
        if ($identifier !== null && $academy->players()->where('internal_identifier', $identifier)->exists()) {
            $errors[] = "Internal ID '{$identifier}' already belongs to an existing profile.";
        }
        $duplicate = $academy->players()->where('first_name', $data['first_name'])->where('last_name', $data['last_name']);
        if ($data['date_of_birth'] !== null) {
            $duplicate->whereDate('date_of_birth', $data['date_of_birth']);
        } elseif ($data['guardian_phone'] !== null) {
            $duplicate->whereHas('guardians', fn ($query) => $query->where('phone', $data['guardian_phone']));
        } else {
            $duplicate->whereRaw('1 = 0');
        }
        if ($duplicate->exists()) {
            $errors[] = 'A matching profile already exists in this academy.';
        }

        $key = $identifier !== null ? "id:{$identifier}" : null;
        if ($key === null && $data['date_of_birth'] !== null) {
            $key = 'bio:'.mb_strtolower("{$data['first_name']}|{$data['last_name']}|{$data['date_of_birth']}");
        } elseif ($key === null && $data['guardian_phone'] !== null) {
            $key = 'guardian:'.mb_strtolower("{$data['first_name']}|{$data['last_name']}|{$data['guardian_phone']}");
        }
        if ($key !== null && isset($seenKeys[$key])) {
            $errors[] = 'This row duplicates another row in the CSV file.';
        } elseif ($key !== null) {
            $seenKeys[$key] = true;
        }
    }
}
