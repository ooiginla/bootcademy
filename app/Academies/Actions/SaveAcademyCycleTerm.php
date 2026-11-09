<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleTerm;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAcademyCycleTerm
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, AcademyCycle $cycle, User $actor, array $data, Request $request, ?AcademyCycleTerm $term = null): AcademyCycleTerm
    {
        return DB::transaction(function () use ($academy, $cycle, $actor, $data, $request, $term): AcademyCycleTerm {
            abort_unless($cycle->academy_id === $academy->id, 404);
            if (! $cycle->supportsTerms()) {
                throw ValidationException::withMessages([
                    'name' => 'Rolling cycles do not use terms.',
                ]);
            }
            $term ??= new AcademyCycleTerm(['academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id]);
            abort_unless($term->academy_id === $academy->id && $term->academy_cycle_id === $cycle->id, 404);
            $wasExisting = $term->exists;
            $hasAnotherDefault = $cycle->terms()
                ->where('id', '<>', (int) $term->id)
                ->where('is_default', true)
                ->exists();
            $isDefault = (bool) ($data['is_default'] ?? false) || ! $hasAnotherDefault;
            if ($isDefault) {
                $cycle->terms()->where('id', '<>', (int) $term->id)->update(['is_default' => false]);
            }
            $term->fill([
                'name' => trim((string) $data['name']),
                'starts_on' => $data['starts_on'] ?: null,
                'ends_on' => $data['ends_on'] ?: null,
                'sort_order' => (int) $data['sort_order'],
                'is_default' => $isDefault,
                'is_active' => (bool) $data['is_active'],
            ])->save();
            $this->audit->record(
                $wasExisting ? 'academy.cycle_term.updated' : 'academy.cycle_term.created',
                $actor,
                $academy,
                $term,
                ['cycle_id' => $cycle->id],
                $request,
            );

            return $term;
        });
    }
}
