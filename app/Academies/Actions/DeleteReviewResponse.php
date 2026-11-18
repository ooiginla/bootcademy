<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeleteReviewResponse
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        Academy $academy,
        Review $review,
        ReviewResponse $response,
        User $actor,
        Request $request,
    ): void {
        abort_unless(
            $review->academy_id === $academy->id
            && $response->academy_id === $academy->id
            && $response->review_id === $review->id,
            404,
        );

        DB::transaction(function () use ($academy, $review, $response, $actor, $request): void {
            $this->audit->record('academy.review_response.deleted', $actor, $academy, $response, [
                'review_id' => $review->id,
                'submitted_at' => $response->submitted_at->toIso8601String(),
            ], $request);

            $response->delete();
        });
    }
}
