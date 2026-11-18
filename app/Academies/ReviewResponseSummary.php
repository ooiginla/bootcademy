<?php

namespace App\Academies;

use App\Models\Review;

class ReviewResponseSummary
{
    /** @return list<array<string, mixed>> */
    public function for(Review $review): array
    {
        $responses = $review->responses()->oldest('submitted_at')->get(['answers']);
        $total = $responses->count();

        return array_map(function (array $field) use ($responses, $total): array {
            $values = $responses
                ->map(fn ($response): mixed => $response->answers[$field['id']] ?? null)
                ->filter(fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [])
                ->values();

            $summary = [
                'field_id' => $field['id'],
                'label' => $field['label'],
                'type' => $field['type'],
                'answered' => $values->count(),
                'skipped' => max(0, $total - $values->count()),
            ];

            if (in_array($field['type'], ['select', 'radio', 'multi_select', 'rating', 'checkbox'], true)) {
                $counts = [];
                foreach ($values as $value) {
                    foreach (is_array($value) ? $value : [$value] as $item) {
                        $label = is_bool($item) ? ($item ? 'Yes' : 'No') : (string) $item;
                        $counts[$label] = ($counts[$label] ?? 0) + 1;
                    }
                }

                arsort($counts);
                $summary['distribution'] = array_map(
                    fn (string $label, int $count): array => [
                        'label' => $label,
                        'count' => $count,
                        'percentage' => $values->count() === 0 ? 0 : round(($count / $values->count()) * 100, 1),
                    ],
                    array_keys($counts),
                    array_values($counts),
                );
            } else {
                $summary['recent_answers'] = $values->reverse()->take(5)->values()->all();
            }

            return $summary;
        }, $review->fields);
    }
}
