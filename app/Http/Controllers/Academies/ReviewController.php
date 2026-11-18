<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\DeleteReviewResponse;
use App\Academies\Actions\SaveReview;
use App\Academies\ReviewResponseSummary;
use App\Enums\ReviewAudience;
use App\Enums\ReviewFieldType;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveReviewRequest;
use App\Models\Academy;
use App\Models\Review;
use App\Models\ReviewResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        $academy = $context->academy();
        Gate::authorize('manage', $academy);

        return Inertia::render('academies/reviews/index', [
            'reviews' => $academy->reviews()
                ->withCount('responses')
                ->withMax('responses', 'submitted_at')
                ->latest()
                ->get()
                ->map(fn (Review $review): array => [
                    'id' => $review->id,
                    'title' => $review->title,
                    'audience' => $review->audience->value,
                    'audience_label' => $review->audience->label(),
                    'status' => $review->status->value,
                    'status_label' => $review->status->label(),
                    'field_count' => count($review->fields),
                    'responses_count' => $review->responses_count,
                    'last_response_at' => $review->responses_max_submitted_at,
                    'updated_at' => $review->updated_at?->toIso8601String(),
                ]),
        ]);
    }

    public function create(AcademyContext $context): Response
    {
        Gate::authorize('manage', $context->academy());

        return Inertia::render('academies/reviews/form', $this->formOptions());
    }

    public function store(
        SaveReviewRequest $request,
        AcademyContext $context,
        SaveReview $saveReview,
    ): RedirectResponse {
        $review = $saveReview->handle($context->academy(), $request->user(), $request->validated(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review created.']);

        return to_route('academy.reviews.show', $review);
    }

    public function show(
        Request $request,
        AcademyContext $context,
        int $review,
        ReviewResponseSummary $summary,
    ): Response {
        $academy = $context->academy();
        Gate::authorize('manage', $academy);
        $reviewModel = $this->review($academy, $review);
        $tab = $request->string('tab')->toString() === 'responses' ? 'responses' : 'summary';

        return Inertia::render('academies/reviews/show', [
            'review' => $this->reviewPayload($reviewModel),
            'active_tab' => $tab,
            'summary' => $summary->for($reviewModel),
            'responses' => $reviewModel->responses()
                ->latest('submitted_at')
                ->limit(250)
                ->get()
                ->map(fn (ReviewResponse $response): array => [
                    'id' => $response->id,
                    'respondent_name' => $response->respondent_name,
                    'answered_count' => collect($response->answers)
                        ->filter(fn (mixed $answer): bool => $answer !== null && $answer !== '' && $answer !== [])
                        ->count(),
                    'submitted_at' => $response->submitted_at->toIso8601String(),
                ]),
            'public_url' => route('public.reviews.show', $reviewModel->public_token),
        ]);
    }

    public function edit(AcademyContext $context, int $review): Response
    {
        $academy = $context->academy();
        Gate::authorize('manage', $academy);
        $reviewModel = $this->review($academy, $review);

        return Inertia::render('academies/reviews/form', [
            ...$this->formOptions(),
            'review' => $this->reviewPayload($reviewModel),
            'fields_locked' => $reviewModel->responses()->exists(),
        ]);
    }

    public function update(
        SaveReviewRequest $request,
        AcademyContext $context,
        SaveReview $saveReview,
        int $review,
    ): RedirectResponse {
        $academy = $context->academy();
        $reviewModel = $this->review($academy, $review);
        $saveReview->handle($academy, $request->user(), $request->validated(), $request, $reviewModel);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review updated.']);

        return to_route('academy.reviews.show', $reviewModel);
    }

    public function response(AcademyContext $context, int $review, int $response): Response
    {
        $academy = $context->academy();
        Gate::authorize('manage', $academy);
        $reviewModel = $this->review($academy, $review);
        $reviewResponse = $reviewModel->responses()->where('academy_id', $academy->id)->findOrFail($response);

        return Inertia::render('academies/reviews/response', [
            'review' => $this->reviewPayload($reviewModel),
            'response' => [
                'id' => $reviewResponse->id,
                'respondent_name' => $reviewResponse->respondent_name,
                'submitted_at' => $reviewResponse->submitted_at->toIso8601String(),
                'answers' => $reviewResponse->answers,
            ],
        ]);
    }

    public function destroyResponse(
        Request $request,
        AcademyContext $context,
        DeleteReviewResponse $deleteResponse,
        int $review,
        int $response,
    ): RedirectResponse {
        $academy = $context->academy();
        Gate::authorize('manage', $academy);
        $reviewModel = $this->review($academy, $review);
        $reviewResponse = $reviewModel->responses()->where('academy_id', $academy->id)->findOrFail($response);
        $deleteResponse->handle($academy, $reviewModel, $reviewResponse, $request->user(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review response deleted.']);

        return to_route('academy.reviews.show', ['review' => $reviewModel, 'tab' => 'responses']);
    }

    private function review(Academy $academy, int $id): Review
    {
        return $academy->reviews()->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'review' => null,
            'fields_locked' => false,
            'audiences' => array_map(fn (ReviewAudience $audience): array => [
                'value' => $audience->value,
                'label' => $audience->label(),
            ], ReviewAudience::cases()),
            'statuses' => array_map(fn (ReviewStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], ReviewStatus::cases()),
            'field_types' => array_map(fn (ReviewFieldType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'needs_options' => $type->needsOptions(),
            ], ReviewFieldType::cases()),
        ];
    }

    /** @return array<string, mixed> */
    private function reviewPayload(Review $review): array
    {
        return [
            'id' => $review->id,
            'title' => $review->title,
            'description' => $review->description,
            'audience' => $review->audience->value,
            'audience_label' => $review->audience->label(),
            'status' => $review->status->value,
            'status_label' => $review->status->label(),
            'collect_respondent_name' => $review->collect_respondent_name,
            'fields' => $review->fields,
            'responses_count' => $review->responses()->count(),
            'created_at' => $review->created_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }
}
