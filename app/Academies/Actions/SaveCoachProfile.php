<?php

namespace App\Academies\Actions;

use App\Enums\CustomFieldType;
use App\Models\Academy;
use App\Models\AcademyCoachProfile;
use App\Models\AcademyCustomField;
use App\Models\AcademyMembership;
use App\Models\CoachCustomFieldValue;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveCoachProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, AcademyMembership $membership, User $actor, array $data, Request $request): AcademyCoachProfile
    {
        abort_unless($membership->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $membership, $actor, $data, $request): AcademyCoachProfile {
            $values = $data['custom_fields'] ?? [];
            abort_unless(is_array($values), 422);
            $profile = AcademyCoachProfile::query()->firstOrNew([
                'academy_id' => $academy->id,
                'membership_id' => $membership->id,
            ]);
            $created = ! $profile->exists;
            $profile->fill(Arr::except($data, ['custom_fields']));
            $profile->save();

            $fields = $academy->customFields()->where('entity_type', 'coach')->where('is_active', true)->get()->keyBy('key');
            foreach ($fields as $key => $field) {
                $value = $values[$key] ?? null;
                if ($value === null || $value === '') {
                    CoachCustomFieldValue::query()->where([
                        'academy_id' => $academy->id,
                        'coach_profile_id' => $profile->id,
                        'custom_field_id' => $field->id,
                    ])->delete();

                    continue;
                }
                CoachCustomFieldValue::query()->updateOrCreate([
                    'academy_id' => $academy->id,
                    'coach_profile_id' => $profile->id,
                    'custom_field_id' => $field->id,
                ], ['value' => $this->normalize($field, $value)]);
            }

            $this->audit->record(
                $created ? 'academy.coach_profile.created' : 'academy.coach_profile.updated',
                $actor,
                $academy,
                $profile,
                ['membership_id' => $membership->id, 'changed_fields' => array_keys($profile->getChanges()), 'custom_field_keys' => array_keys($values)],
                $request,
            );

            return $profile;
        });
    }

    private function normalize(AcademyCustomField $field, mixed $value): mixed
    {
        return match ($field->type) {
            CustomFieldType::Number => str_contains((string) $value, '.') ? (float) $value : (int) $value,
            CustomFieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }
}
