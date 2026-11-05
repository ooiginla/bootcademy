<?php

namespace App\Academies\Actions;

use App\Enums\AcademyCycleStatus;
use App\Enums\AcademyCycleType;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveAcademyCycle
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request, ?AcademyCycle $cycle = null): AcademyCycle
    {
        return DB::transaction(function () use ($academy, $actor, $data, $request, $cycle): AcademyCycle {
            Academy::query()->whereKey($academy->id)->lockForUpdate()->firstOrFail();
            $cycle ??= new AcademyCycle(['academy_id' => $academy->id]);
            abort_unless($cycle->academy_id === $academy->id, 404);
            $wasExisting = $cycle->exists;
            if ($data['status'] === AcademyCycleStatus::Active->value) {
                $academy->cycles()->where('status', AcademyCycleStatus::Active->value)
                    ->when($cycle->exists, fn ($query) => $query->where('id', '!=', $cycle->id))
                    ->update(['status' => AcademyCycleStatus::Closed->value, 'updated_at' => now()]);
            }
            if ($data['type'] === AcademyCycleType::Fixed->value) {
                $data['duration_months'] = null;
                $data['duration_value'] = null;
                $data['duration_unit'] = null;
            } else {
                $data['starts_on'] = null;
                $data['ends_on'] = null;
                $data['duration_months'] = null;
            }
            $cycle->fill(Arr::only($data, ['name', 'type', 'starts_on', 'ends_on', 'duration_months', 'duration_value', 'duration_unit', 'status']));
            $cycle->save();
            $this->audit->record(
                $wasExisting ? 'academy.cycle.updated' : 'academy.cycle.created',
                $actor, $academy, $cycle,
                ['changed_fields' => array_keys($cycle->getChanges())], $request,
            );

            return $cycle;
        });
    }
}
