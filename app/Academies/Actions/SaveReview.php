<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Review;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveReview
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request, ?Review $review = null): Review
    {
        $fields = $this->normalizeFields((array) $data['fields']);

        if ($review?->responses()->exists() && $review->fields !== $fields) {
            throw ValidationException::withMessages([
                'fields' => 'The form fields are locked because this review already has responses.',
            ]);
        }

        return DB::transaction(function () use ($academy, $actor, $data, $fields, $request, $review): Review {
            $before = $review?->only(['title', 'description', 'audience', 'status', 'collect_respondent_name', 'fields']);
            $attributes = [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'audience' => $data['audience'],
                'status' => $data['status'],
                'collect_respondent_name' => (bool) $data['collect_respondent_name'],
                'fields' => $fields,
            ];

            if ($review === null) {
                $review = $academy->reviews()->create([
                    ...$attributes,
                    'public_token' => Str::random(48),
                    'created_by_user_id' => $actor->id,
                ]);
            } else {
                $review->update($attributes);
            }

            $this->audit->record(
                $before === null ? 'review.created' : 'review.updated',
                $actor,
                $academy,
                $review,
                ['before' => $before, 'after' => $attributes],
                $request,
            );

            return $review;
        });
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return list<array<string, mixed>>
     */
    private function normalizeFields(array $fields): array
    {
        return array_values(array_map(function (mixed $field): array {
            $field = is_array($field) ? $field : [];

            return [
                'id' => (string) ($field['id'] ?? ''),
                'label' => trim((string) ($field['label'] ?? '')),
                'description' => trim((string) ($field['description'] ?? '')) ?: null,
                'type' => (string) ($field['type'] ?? 'short_text'),
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_values(array_filter(array_map(
                    fn (mixed $option): string => trim((string) $option),
                    is_array($field['options'] ?? null) ? $field['options'] : [],
                ), fn (string $option): bool => $option !== '')),
                'max_rating' => (int) ($field['max_rating'] ?? 5),
            ];
        }, $fields));
    }
}
