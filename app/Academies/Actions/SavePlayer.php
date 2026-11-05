<?php

namespace App\Academies\Actions;

use App\Enums\CustomFieldType;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\Player;
use App\Models\PlayerCustomFieldValue;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SavePlayer
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SaveGuardian $saveGuardian,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request, ?Player $player = null): Player
    {
        return DB::transaction(function () use ($academy, $actor, $data, $request, $player): Player {
            $rawGuardianIds = $data['guardian_ids'] ?? [];
            abort_unless(is_array($rawGuardianIds), 422);
            $guardianIds = array_map(fn (mixed $id): int => (int) $id, array_values($rawGuardianIds));
            $relationships = $data['guardian_relationships'] ?? [];
            abort_unless(is_array($relationships), 422);
            $customValues = $data['custom_fields'] ?? [];
            abort_unless(is_array($customValues), 422);
            $newGuardianData = $data['new_guardian'] ?? [];
            abort_unless(is_array($newGuardianData), 422);

            if (($newGuardianData['first_name'] ?? '') !== '') {
                $newRelationship = trim((string) ($newGuardianData['relationship'] ?? 'Parent'));
                $newGuardian = $this->saveGuardian->handle(
                    $academy,
                    $actor,
                    Arr::except($newGuardianData, ['relationship']),
                    $request,
                );
                $guardianIds[] = $newGuardian->id;
                $relationships[$newGuardian->id] = $newRelationship === '' ? 'Parent' : $newRelationship;
            }

            $player ??= new Player(['academy_id' => $academy->id]);
            abort_unless($player->academy_id === $academy->id, 404);
            $wasExisting = $player->exists;
            $player->fill(Arr::except($data, ['guardian_ids', 'guardian_relationships', 'custom_fields', 'new_guardian']));
            $player->save();
            $changedFields = array_keys($player->getChanges());

            $player->guardians()->sync(collect($guardianIds)->mapWithKeys(
                fn (int $guardianId, int $index): array => [$guardianId => [
                    'academy_id' => $academy->id,
                    'relationship' => $this->relationshipFor($relationships, $guardianId),
                    'is_primary' => $index === 0,
                    'is_emergency_contact' => $index === 0,
                    'is_authorized_pickup' => false,
                ]],
            )->all());

            $this->syncCustomValues($academy, $player, $customValues);

            $this->audit->record(
                $wasExisting ? 'academy.player.updated' : 'academy.player.created',
                $actor,
                $academy,
                $player,
                [
                    'changed_fields' => $changedFields,
                    'guardian_ids' => $guardianIds,
                    'custom_field_keys' => array_keys($customValues),
                ],
                $request,
            );

            return $player;
        });
    }

    /** @param array<int|string, mixed> $relationships */
    private function relationshipFor(array $relationships, int $guardianId): string
    {
        $relationship = trim((string) ($relationships[$guardianId] ?? 'Guardian'));

        return $relationship === '' ? 'Guardian' : $relationship;
    }

    /** @param array<string, mixed> $values */
    private function syncCustomValues(Academy $academy, Player $player, array $values): void
    {
        $fields = $academy->customFields()
            ->where('entity_type', 'player')
            ->where('is_active', true)
            ->get()
            ->keyBy('key');

        foreach ($fields as $key => $field) {
            $value = $values[$key] ?? null;
            if ($value === null || $value === '') {
                PlayerCustomFieldValue::query()
                    ->where('academy_id', $academy->id)
                    ->where('player_id', $player->id)
                    ->where('custom_field_id', $field->id)
                    ->delete();

                continue;
            }

            PlayerCustomFieldValue::query()->updateOrCreate(
                [
                    'academy_id' => $academy->id,
                    'player_id' => $player->id,
                    'custom_field_id' => $field->id,
                ],
                ['value' => $this->normalizeCustomValue($field, $value)],
            );
        }
    }

    private function normalizeCustomValue(AcademyCustomField $field, mixed $value): mixed
    {
        return match ($field->type) {
            CustomFieldType::Number => str_contains((string) $value, '.') ? (float) $value : (int) $value,
            CustomFieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }
}
