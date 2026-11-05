<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;

class SaveCustomField
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request, ?AcademyCustomField $field = null): AcademyCustomField
    {
        $field ??= new AcademyCustomField(['academy_id' => $academy->id]);
        abort_unless($field->academy_id === $academy->id, 404);
        $wasExisting = $field->exists;
        $field->fill($data);
        $field->save();

        $this->audit->record(
            $wasExisting ? 'academy.custom_field.updated' : 'academy.custom_field.created',
            $actor,
            $academy,
            $field,
            ['key' => $field->key, 'changed_fields' => array_keys($field->getChanges())],
            $request,
        );

        return $field;
    }
}
