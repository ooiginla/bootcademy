<?php

namespace App\Http\Controllers;

use App\Academies\Actions\SubmitReviewResponse;
use App\Enums\ReviewStatus;
use App\Http\Requests\Public\SubmitReviewResponseRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicReviewController extends Controller
{
    public function show(string $token): Response
    {
        $review = $this->review($token);
        abort_if($review->status === ReviewStatus::Draft, 404);

        return Inertia::render('public/review', [
            'review' => [
                'title' => $review->title,
                'description' => $review->description,
                'audience_label' => $review->audience->label(),
                'status' => $review->status->value,
                'collect_respondent_name' => $review->collect_respondent_name,
                'fields' => $review->fields,
                'academy' => $review->academy->name,
            ],
            'submit_url' => route('public.reviews.store', $token),
        ]);
    }

    public function store(
        SubmitReviewResponseRequest $request,
        SubmitReviewResponse $submitResponse,
        string $token,
    ): RedirectResponse {
        $submitResponse->handle($request->review(), $request->validated());

        return to_route('public.reviews.complete', $token);
    }

    public function complete(string $token): Response
    {
        $review = $this->review($token);
        abort_if($review->status === ReviewStatus::Draft, 404);

        return Inertia::render('public/review-complete', [
            'title' => $review->title,
            'academy' => $review->academy->name,
        ]);
    }

    private function review(string $token): Review
    {
        return Review::query()->with('academy:id,name')->where('public_token', $token)->firstOrFail();
    }
}
