<?php

namespace App\Academies\Actions;

use App\Models\Review;
use App\Models\ReviewResponse;
use Illuminate\Support\Facades\DB;

class SubmitReviewResponse
{
    /** @param array<string, mixed> $data */
    public function handle(Review $review, array $data): ReviewResponse
    {
        return DB::transaction(fn (): ReviewResponse => $review->responses()->create([
            'academy_id' => $review->academy_id,
            'respondent_name' => $review->collect_respondent_name
                ? ($data['respondent_name'] ?? null)
                : null,
            'answers' => $data['answers'],
            'submitted_at' => now(),
        ]));
    }
}
