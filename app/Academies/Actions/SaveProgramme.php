<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Programme;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveProgramme
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RichTextSanitizer $sanitizer,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(
        Academy $academy,
        User $actor,
        array $data,
        Request $request,
        ?Programme $programme = null,
    ): Programme {
        return DB::transaction(function () use ($academy, $actor, $data, $request, $programme): Programme {
            $attributes = Arr::except($data, ['branch_ids']);
            foreach ([
                'outcomes_html', 'skills_gained_html', 'curriculum_html', 'eligibility_html',
                'audience_html', 'demand_html', 'application_process_html',
            ] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $attributes[$field] = $this->sanitizer->clean($attributes[$field]);
                }
            }
            $attributes['faqs'] = collect($attributes['faqs'] ?? [])->map(fn (array $faq): array => [
                'question' => $faq['question'],
                'answer_html' => $this->sanitizer->clean($faq['answer_html']),
            ])->all();
            $attributes['testimonials'] = collect($attributes['testimonials'] ?? [])->map(fn (array $testimonial): array => [
                'quote_html' => $this->sanitizer->clean($testimonial['quote_html']),
                'name' => $testimonial['name'],
                'role' => $testimonial['role'] ?? null,
            ])->all();
            $rawBranchIds = $data['branch_ids'] ?? [];
            abort_unless(is_array($rawBranchIds), 422);
            $branchIds = array_map(
                fn (mixed $id): int => (int) $id,
                array_values($rawBranchIds),
            );
            $before = $programme ? [
                ...$programme->only(array_keys($attributes)),
                'branch_ids' => $programme->branches()->pluck('branches.id')->all(),
            ] : null;

            $programme ??= new Programme(['academy_id' => $academy->id]);
            abort_unless($programme->academy_id === $academy->id, 404);
            $programme->fill($attributes);
            $programme->save();

            $programme->branches()->sync(collect($branchIds)->mapWithKeys(
                fn (int $branchId): array => [$branchId => ['academy_id' => $academy->id]],
            )->all());

            $after = [
                ...$programme->only(array_keys($attributes)),
                'branch_ids' => $branchIds,
            ];
            $this->audit->record(
                $before ? 'academy.programme.updated' : 'academy.programme.created',
                $actor,
                $academy,
                $programme,
                $before ? ['before' => $before, 'after' => $after] : ['branch_ids' => $branchIds],
                $request,
            );

            return $programme;
        });
    }
}
