<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_view_a_tenant_scoped_review(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $response = $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.reviews.store'), $this->reviewData());

        $review = Review::query()->sole();
        $response->assertRedirect(route('academy.reviews.show', $review));
        $this->assertSame($academy->id, $review->academy_id);
        $this->assertSame('guardians', $review->audience->value);
        $this->assertCount(2, $review->fields);

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/reviews/index')
                ->has('reviews', 1)
                ->where('reviews.0.title', 'End of term experience')
                ->where('reviews.0.responses_count', 0)
            );
    }

    public function test_non_admin_and_other_tenants_cannot_access_review_administration(): void
    {
        [$coach, $ownAcademy] = $this->member(AcademyRole::Coach);
        [$foreignOwner, $foreignAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $review = $this->review($foreignAcademy, $foreignOwner);

        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $ownAcademy->id])
            ->get(route('academy.reviews.index'))
            ->assertForbidden();

        [$owner] = $this->member(AcademyRole::OwnerAdmin, $ownAcademy);
        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $ownAcademy->id])
            ->get(route('academy.reviews.show', $review))
            ->assertNotFound();
    }

    public function test_published_review_accepts_valid_public_responses_and_summarises_them(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $review = $this->review($academy, $owner, 'published');

        $this->get(route('public.reviews.show', $review->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/review')
                ->where('review.title', $review->title)
                ->where('review.academy', $academy->name)
            );

        $this->post(route('public.reviews.store', $review->public_token), [
            'respondent_name' => 'Ada Parent',
            'answers' => [
                'rating' => 5,
                'comment' => 'The coaches communicated clearly.',
            ],
        ])->assertRedirect(route('public.reviews.complete', $review->public_token));

        $this->assertDatabaseHas(ReviewResponse::class, [
            'academy_id' => $academy->id,
            'review_id' => $review->id,
            'respondent_name' => 'Ada Parent',
        ]);

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.reviews.show', $review))
            ->assertInertia(fn (Assert $page) => $page
                ->where('review.responses_count', 1)
                ->where('summary.0.answered', 1)
                ->where('summary.0.distribution.0.label', '5')
                ->where('summary.0.distribution.0.count', 1)
            );
    }

    public function test_public_response_validation_enforces_required_fields_and_allowed_options(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $review = $this->review($academy, $owner, 'published', [
            [
                'id' => 'choice', 'label' => 'Choose one', 'description' => null,
                'type' => 'select', 'required' => true, 'options' => ['Good', 'Excellent'], 'max_rating' => 5,
            ],
        ]);

        $this->post(route('public.reviews.store', $review->public_token), [
            'respondent_name' => 'Test Parent',
            'answers' => ['choice' => 'Invalid'],
        ])->assertSessionHasErrors('answers.choice');

        $this->assertDatabaseCount('review_responses', 0);
    }

    public function test_checkbox_questions_accept_multiple_configured_options(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $review = $this->review($academy, $owner, 'published', [
            [
                'id' => 'flavours', 'label' => 'Which ice cream flavours do you like?', 'description' => null,
                'type' => 'checkbox', 'required' => true,
                'options' => ['Vanilla', 'Chocolate', 'Strawberry'], 'max_rating' => 5,
            ],
        ]);

        $this->post(route('public.reviews.store', $review->public_token), [
            'respondent_name' => 'Test Player',
            'answers' => ['flavours' => ['Vanilla', 'Strawberry']],
        ])->assertRedirect(route('public.reviews.complete', $review->public_token));

        $response = ReviewResponse::query()->sole();
        $this->assertSame(['Vanilla', 'Strawberry'], $response->answers['flavours']);
    }

    public function test_owner_can_delete_a_response_but_other_tenants_and_non_admins_cannot(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $review = $this->review($academy, $owner, 'published');
        $response = $review->responses()->create([
            'academy_id' => $academy->id,
            'respondent_name' => 'Ada Parent',
            'answers' => ['rating' => 5, 'comment' => 'Excellent'],
            'submitted_at' => now(),
        ]);

        [$foreignOwner, $foreignAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $this->actingAs($foreignOwner)
            ->withSession(['active_academy_id' => $foreignAcademy->id])
            ->delete(route('academy.reviews.responses.destroy', [$review, $response]))
            ->assertNotFound();

        [$coach] = $this->member(AcademyRole::Coach, $academy);
        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $academy->id])
            ->delete(route('academy.reviews.responses.destroy', [$review, $response]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->delete(route('academy.reviews.responses.destroy', [$review, $response]))
            ->assertRedirect(route('academy.reviews.show', ['review' => $review, 'tab' => 'responses']))
            ->assertInertiaFlash('toast.message', 'Review response deleted.');

        $this->assertDatabaseMissing('review_responses', ['id' => $response->id]);
        $this->assertDatabaseHas('audit_logs', [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.review_response.deleted',
            'auditable_id' => $response->id,
        ]);
    }

    public function test_draft_is_private_closed_review_rejects_submissions_and_fields_lock_after_response(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $draft = $this->review($academy, $owner, 'draft');
        $this->get(route('public.reviews.show', $draft->public_token))->assertNotFound();

        $review = $this->review($academy, $owner, 'closed');
        $this->get(route('public.reviews.show', $review->public_token))->assertOk();
        $this->post(route('public.reviews.store', $review->public_token), [
            'respondent_name' => 'Blocked',
            'answers' => ['rating' => 5, 'comment' => 'No'],
        ])->assertForbidden();

        $review->responses()->create([
            'academy_id' => $academy->id,
            'respondent_name' => 'Existing respondent',
            'answers' => ['rating' => 4, 'comment' => 'Existing answer'],
            'submitted_at' => now(),
        ]);
        $data = $this->reviewData();
        $data['status'] = 'closed';
        $data['fields'][0]['label'] = 'Changed question';

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.reviews.update', $review), $data)
            ->assertSessionHasErrors('fields');

        $this->assertSame('How would you rate the academy?', $review->fresh()->fields[0]['label']);
    }

    /** @return array{User, Academy} */
    private function member(AcademyRole $role, ?Academy $academy = null): array
    {
        $user = User::factory()->create();
        $academy ??= Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }

    /** @param list<array<string, mixed>>|null $fields */
    private function review(Academy $academy, User $owner, string $status = 'draft', ?array $fields = null): Review
    {
        return Review::query()->create([
            'academy_id' => $academy->id,
            'title' => 'End of term experience',
            'description' => 'Tell us about the term.',
            'audience' => 'guardians',
            'status' => $status,
            'collect_respondent_name' => true,
            'fields' => $fields ?? $this->reviewData()['fields'],
            'public_token' => str_repeat((string) (($academy->id % 9) + 1), 40).bin2hex(random_bytes(4)),
            'created_by_user_id' => $owner->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function reviewData(): array
    {
        return [
            'title' => 'End of term experience',
            'description' => 'Tell us about the term.',
            'audience' => 'guardians',
            'status' => 'draft',
            'collect_respondent_name' => true,
            'fields' => [
                [
                    'id' => 'rating', 'label' => 'How would you rate the academy?', 'description' => null,
                    'type' => 'rating', 'required' => true, 'options' => [], 'max_rating' => 5,
                ],
                [
                    'id' => 'comment', 'label' => 'What should we improve?', 'description' => null,
                    'type' => 'long_text', 'required' => false, 'options' => [], 'max_rating' => 5,
                ],
            ],
        ];
    }
}
