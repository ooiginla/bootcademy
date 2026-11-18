<?php

namespace Tests\Feature\Academies;

use App\Academies\Actions\SaveEnrollment;
use App\Enums\AcademyRole;
use App\Enums\EnrollmentStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AcademySubscriptionPeriod;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\Player;
use App\Models\Programme;
use App\Models\User;
use App\Platform\Subscriptions\AddSubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_essential_allows_one_active_branch_and_keeps_existing_ones(): void
    {
        [$owner, $academy] = $this->onEssential();
        $session = ['active_academy_id' => $academy->id];
        $branch = ['timezone' => 'Africa/Lagos', 'is_active' => true];

        $this->actingAs($owner)->withSession($session)->post(route('academy.branches.store'), ['name' => 'Ikeja', ...$branch])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->withSession($session)->post(route('academy.branches.store'), ['name' => 'Yaba', ...$branch])
            ->assertSessionHasErrors(['name' => 'Your Essential plan allows 1 active branch. Upgrade your subscription to add more.']);
        // An inactive branch does not use the allowance, but reactivating it does.
        $this->actingAs($owner)->withSession($session)->post(route('academy.branches.store'), ['name' => 'Yaba', ...$branch, 'is_active' => false])
            ->assertSessionHasNoErrors();
        $yaba = Branch::query()->where('name', 'Yaba')->sole();
        $this->actingAs($owner)->withSession($session)->patch(route('academy.branches.update', $yaba), ['name' => 'Yaba', ...$branch])
            ->assertSessionHasErrors('name');
        $this->assertFalse($yaba->fresh()?->is_active);
    }

    public function test_staff_invitations_stop_at_the_staff_account_limit(): void
    {
        [$owner, $academy] = $this->onEssential();
        AcademyMembership::factory()->for($academy)->asRole(AcademyRole::Coach)->count(4)->create();
        AcademyMembership::factory()->for($academy)->asRole(AcademyRole::Guardian)->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.team.store'), ['email' => 'new.coach@example.test', 'role' => AcademyRole::Coach->value])
            ->assertSessionHasErrors('email');
        $this->assertSame(0, $academy->invitations()->count());
    }

    public function test_new_active_players_stop_at_the_player_limit_but_existing_ones_can_be_enrolled_again(): void
    {
        [$owner, $academy] = $this->onEssential();
        Plan::query()->where('code', 'essential')->update(['active_player_limit' => 1]);
        $programme = Programme::factory()->create(['academy_id' => $academy->id]);
        $second = Programme::factory()->create(['academy_id' => $academy->id]);
        $first = Player::factory()->create(['academy_id' => $academy->id]);
        $newcomer = Player::factory()->create(['academy_id' => $academy->id]);
        Enrollment::factory()->create(['player_id' => $first->id, 'programme_id' => $programme->id]);
        $save = app(SaveEnrollment::class);

        // The same player in a second programme still counts once.
        $save->handle($academy, $first, $owner, ['programme_id' => $second->id, 'status' => EnrollmentStatus::Active->value, 'starts_on' => today()], Request::create('/'));

        try {
            $save->handle($academy, $newcomer, $owner, ['programme_id' => $programme->id, 'status' => EnrollmentStatus::Active->value, 'starts_on' => today()], Request::create('/'));
            $this->fail('The player limit was not enforced.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('allows 1 active players', $exception->errors()['programme_id'][0]);
        }
        $this->assertSame(0, $newcomer->enrollments()->count());
    }

    public function test_api_keys_need_a_plan_with_api_access(): void
    {
        [$owner, $academy] = $this->onEssential();
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.api-keys.store'), ['name' => 'Website'])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, $academy->apiKeys()->count());

        // The Enterprise trial includes API access.
        $trialOwner = User::factory()->create();
        $trialAcademy = Academy::factory()->create();
        AcademyMembership::factory()->for($trialOwner)->for($trialAcademy)->create();
        $this->actingAs($trialOwner)->withSession(['active_academy_id' => $trialAcademy->id])
            ->post(route('academy.api-keys.store'), ['name' => 'Website'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $trialAcademy->apiKeys()->count());
    }

    /** @return array{User, Academy} an owner whose academy has a paid Essential period and no trial */
    private function onEssential(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->create();
        AcademySubscriptionPeriod::query()->where('academy_id', $academy->id)->delete();
        app(AddSubscriptionPeriod::class)->handle(
            $academy,
            Plan::query()->where('code', 'essential')->firstOrFail(),
            'granted',
            null,
            null,
            null,
            Request::create('/'),
            now()->addMonth(),
        );

        return [$owner, $academy];
    }
}
