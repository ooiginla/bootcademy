<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyCoachProfile;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleFee;
use App\Models\AcademyExpense;
use App\Models\AcademyInvitation;
use App\Models\AcademyMembership;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Product;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SoftDeleteAcademyRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_soft_deletes_a_branch_and_can_reuse_its_name_and_code(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $branch = Branch::factory()->for($academy)->create(['name' => 'Central Campus', 'code' => 'CENTRAL']);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.branches.destroy', $branch))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Branch deleted.');

        $this->assertSoftDeleted($branch);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.branch.deleted',
            'auditable_id' => $branch->id,
        ]);

        $this->signedIn($owner, $academy)
            ->get(route('academy.branches.index'))
            ->assertInertia(fn (Assert $page) => $page->has('branches', 0));

        $this->signedIn($owner, $academy)
            ->post(route('academy.branches.store'), [
                'name' => 'Central Campus', 'code' => 'CENTRAL', 'country_code' => 'ng',
                'timezone' => 'Africa/Lagos', 'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Branch::query()->where('academy_id', $academy->id)->where('name', 'Central Campus')->count());
        $this->assertSame(2, Branch::withTrashed()->where('academy_id', $academy->id)->where('name', 'Central Campus')->count());
    }

    public function test_branch_is_kept_until_programmes_offered_there_are_deleted(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $branch = Branch::factory()->for($academy)->create(['name' => 'Oniwaya']);
        $programme = Programme::factory()->for($academy)->create(['name' => 'Under 11']);
        $programme->branches()->attach($branch->id, ['academy_id' => $academy->id]);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.branches.destroy', $branch))
            ->assertSessionHasErrors(['delete' => 'Branch "Oniwaya" still has 1 programme offered here. Delete or move those first.']);
        $this->assertNotSoftDeleted($branch);
        $this->assertDatabaseMissing(AuditLog::class, ['action' => 'academy.branch.deleted']);

        $this->signedIn($owner, $academy)->delete(route('academy.programmes.destroy', $programme))->assertRedirect(route('academy.programmes.index'));
        $this->assertSoftDeleted($programme);

        $this->signedIn($owner, $academy)->delete(route('academy.branches.destroy', $branch))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($branch);
    }

    public function test_deleted_records_are_rejected_by_validation(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $branch = Branch::factory()->for($academy)->create();
        $branch->delete();

        $this->signedIn($owner, $academy)
            ->post(route('academy.programmes.store'), [
                'name' => 'Weekend Juniors', 'is_active' => true, 'branch_ids' => [$branch->id],
            ])
            ->assertSessionHasErrors('branch_ids.0');
    }

    public function test_programme_with_enrolled_students_is_kept(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->for($academy)->create(['name' => 'Under 17']);
        $player = Player::factory()->for($academy)->create();
        Enrollment::factory()->create(['player_id' => $player->id, 'programme_id' => $programme->id]);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.programmes.destroy', $programme))
            ->assertSessionHasErrors(['delete' => 'Programme "Under 17" has 1 enrolled student that must be kept, so it cannot be deleted.']);

        $this->assertNotSoftDeleted($programme);
    }

    public function test_finance_chain_deletes_payment_plans_then_programme_fee_then_cycle(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$cycle, $offering, $standard] = $this->offering($academy);
        $custom = $this->plan($offering, 'Two instalments');

        $this->signedIn($owner, $academy)
            ->delete(route('academy.cycles.destroy', $cycle))
            ->assertSessionHasErrors(['delete' => 'Cycle "2028 Academy Year" still has 1 programme fee. Delete or move those first.']);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.cycles.offerings.destroy', [$cycle, $offering]))
            ->assertSessionHasErrors(['delete' => 'Programme fee "Annual tuition" still has 1 payment plan. Delete or move those first.']);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.cycles.offerings.payment-plans.destroy', [$cycle, $offering, $standard]))
            ->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted($standard);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.cycles.offerings.payment-plans.destroy', [$cycle, $offering, $custom]))
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted($custom);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.cycles.offerings.destroy', [$cycle, $offering]))
            ->assertRedirect(route('academy.cycles.show', $cycle));
        $this->assertSoftDeleted($offering);
        $this->assertSoftDeleted($standard);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.cycles.destroy', $cycle))
            ->assertRedirect(route('academy.cycles.index'));
        $this->assertSoftDeleted($cycle);
    }

    public function test_cycle_fee_blocks_its_cycle_and_invoiced_cycle_fees_are_kept(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $cycle = $this->cycle($academy);
        $fee = $this->cycleFee($cycle, 'Kit fee');
        $invoiced = $this->cycleFee($cycle, 'Insurance');
        $this->invoice($academy, $cycle, ['academy_cycle_fee_id' => $invoiced->id]);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.cycles.fees.destroy', [$cycle, $invoiced]))
            ->assertSessionHasErrors(['delete' => 'Cycle fee "Insurance" has 1 invoice that must be kept, so it cannot be deleted.']);

        $this->signedIn($owner, $academy)->delete(route('academy.cycles.fees.destroy', [$cycle, $fee]))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($fee);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.cycles.destroy', $cycle))
            ->assertSessionHasErrors(['delete' => 'Cycle "2028 Academy Year" still has 1 cycle fee. Delete or move those first. It also has 1 invoice that must be kept, so it cannot be deleted.']);
    }

    public function test_session_with_attendance_is_kept_and_an_unused_session_is_deleted(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $session = $this->trainingSession($academy);
        $marked = $this->trainingSession($academy);
        DB::table('attendance_records')->insert([
            'academy_id' => $academy->id, 'training_session_id' => $marked->id,
            'player_id' => Player::factory()->for($academy)->create()->id, 'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->signedIn($owner, $academy)->delete(route('academy.sessions.destroy', $session))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($session);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.sessions.destroy', $marked))
            ->assertSessionHasErrors(['delete' => 'Session "Saturday training" has 1 attendance record that must be kept, so it cannot be deleted.']);
    }

    public function test_finance_officer_deletes_expenses_and_coaches_cannot(): void
    {
        [$finance, $academy] = $this->member(AcademyRole::FinanceOfficer);
        $expense = $this->expense($academy);
        $coach = $this->memberOf($academy, AcademyRole::Coach);

        $this->signedIn($coach, $academy)->delete(route('academy.expenses.destroy', $expense))->assertForbidden();
        $this->assertNotSoftDeleted($expense);

        $this->signedIn($finance, $academy)->delete(route('academy.expenses.destroy', $expense))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($expense);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'academy.expense.deleted', 'auditable_id' => $expense->id]);
    }

    public function test_player_with_finance_history_is_kept_and_a_clean_player_is_deleted(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $clean = Player::factory()->for($academy)->create(['internal_identifier' => 'KC-001']);
        $billed = Player::factory()->for($academy)->create(['first_name' => 'Tobi', 'last_name' => 'Ade']);
        $this->invoice($academy, $this->cycle($academy), ['player_id' => $billed->id]);

        $this->signedIn($owner, $academy)->delete(route('academy.players.destroy', $clean))->assertRedirect(route('academy.players.index'));
        $this->assertSoftDeleted($clean);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.players.destroy', $billed))
            ->assertSessionHasErrors(['delete' => 'Student "Tobi Ade" has 1 invoice that must be kept, so it cannot be deleted.']);

        // A deleted player's identifier can be given to a new player.
        Player::factory()->for($academy)->create(['internal_identifier' => 'KC-001']);
        $this->assertSame(1, Player::query()->where('internal_identifier', 'KC-001')->count());
    }

    public function test_application_with_payments_is_kept_and_an_unpaid_one_is_deleted(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $application = $this->application($academy, 'APP-1');
        $invoiced = $this->application($academy, 'APP-2');
        $this->invoice($academy, $this->cycle($academy), ['application_id' => $invoiced->id]);

        $this->signedIn($owner, $academy)->delete(route('academy.applications.destroy', $application))->assertRedirect(route('academy.applications.index'));
        $this->assertSoftDeleted($application);

        $this->signedIn($owner, $academy)->delete(route('academy.applications.destroy', $invoiced))->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted($invoiced);
    }

    public function test_removed_coach_loses_access_and_reinvite_restores_the_membership(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $coachUser = User::factory()->create(['email' => 'coach@example.test']);
        $membership = AcademyMembership::factory()->for($coachUser)->for($academy)->asRole(AcademyRole::Coach)->create();
        $profile = AcademyCoachProfile::query()->create([
            'academy_id' => $academy->id, 'membership_id' => $membership->id, 'first_name' => 'Ada', 'last_name' => 'Coach',
        ]);
        $session = $this->trainingSession($academy, ['coach_membership_id' => $membership->id]);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.coaches.destroy', $membership))
            ->assertSessionHasErrors(['delete' => 'Coach "'.$coachUser->name.'" still has 1 assigned session. Delete or move those first.']);

        $session->delete();
        $this->signedIn($owner, $academy)->delete(route('academy.coaches.destroy', $membership))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($membership);
        $this->assertSoftDeleted($profile);
        $this->assertSame(MembershipStatus::Suspended, $membership->fresh()?->status ?? AcademyMembership::withTrashed()->find($membership->id)?->status);
        $this->actingAs($coachUser)->withSession(['active_academy_id' => $academy->id])->get(route('academy.branches.index'))->assertRedirect();

        $token = Str::random(64);
        AcademyInvitation::factory()->for($academy)->create([
            'email' => $coachUser->email, 'token_hash' => hash('sha256', $token),
            'invited_by_user_id' => $owner->id, 'role' => AcademyRole::Coach,
        ]);
        $this->actingAs($coachUser)->post(route('academy.invites.accept', $token))->assertRedirect(route('dashboard'));

        $this->assertNotSoftDeleted($membership);
        $this->assertNotSoftDeleted($profile);
        $this->assertSame(MembershipStatus::Active, $membership->fresh()?->status);
    }

    public function test_product_with_stock_or_orders_is_kept(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $product = $this->product($academy, 'Training bib');
        $stocked = $this->product($academy, 'Home jersey');
        $variant = $stocked->variants()->create(['academy_id' => $academy->id, 'name' => 'Medium']);
        DB::table('inventory_stocks')->insert([
            'academy_id' => $academy->id, 'product_variant_id' => $variant->id,
            'branch_id' => Branch::factory()->for($academy)->create()->id, 'on_hand' => 4, 'reserved' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->signedIn($owner, $academy)->delete(route('academy.store.products.destroy', $product))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($product);

        $this->signedIn($owner, $academy)
            ->delete(route('academy.store.products.destroy', $stocked))
            ->assertSessionHasErrors(['delete' => 'Product "Home jersey" still has 1 stock location with units on hand. Delete or move those first.']);
    }

    public function test_records_of_another_academy_cannot_be_deleted(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [, $other] = $this->member(AcademyRole::OwnerAdmin);
        [$cycle, $offering] = $this->offering($other);
        $coachMembership = AcademyMembership::factory()->for(User::factory())->for($other)->asRole(AcademyRole::Coach)->create();

        $targets = [
            route('academy.branches.destroy', Branch::factory()->for($other)->create()),
            route('academy.programmes.destroy', Programme::factory()->for($other)->create()),
            route('academy.sessions.destroy', $this->trainingSession($other)),
            route('academy.cycles.destroy', $cycle),
            route('academy.cycles.fees.destroy', [$cycle, $this->cycleFee($cycle, 'Kit')]),
            route('academy.cycles.offerings.destroy', [$cycle, $offering]),
            route('academy.cycles.offerings.payment-plans.destroy', [$cycle, $offering, $this->plan($offering, 'Split')]),
            route('academy.expenses.destroy', $this->expense($other)),
            route('academy.players.destroy', Player::factory()->for($other)->create()),
            route('academy.applications.destroy', $this->application($other, 'OTHER-1')),
            route('academy.coaches.destroy', $coachMembership),
            route('academy.store.products.destroy', $this->product($other, 'Ball')),
        ];

        foreach ($targets as $url) {
            $this->signedIn($owner, $academy)->delete($url)->assertNotFound();
        }

        $this->assertSame(0, AuditLog::query()->where('action', 'like', '%.deleted')->count());
    }

    public function test_staff_without_management_rights_cannot_delete(): void
    {
        [$coach, $academy] = $this->member(AcademyRole::Coach);
        $branch = Branch::factory()->for($academy)->create();
        $player = Player::factory()->for($academy)->create();
        $cycle = $this->cycle($academy);

        $this->signedIn($coach, $academy)->delete(route('academy.branches.destroy', $branch))->assertForbidden();
        $this->signedIn($coach, $academy)->delete(route('academy.players.destroy', $player))->assertForbidden();
        $this->signedIn($coach, $academy)->delete(route('academy.cycles.destroy', $cycle))->assertForbidden();

        $this->assertNotSoftDeleted($branch);
        $this->assertNotSoftDeleted($player);
        $this->assertNotSoftDeleted($cycle);
    }

    private function signedIn(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }

    private function cycle(Academy $academy): AcademyCycle
    {
        return AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2028 Academy Year', 'type' => 'fixed',
            'starts_on' => '2028-01-01', 'ends_on' => '2028-12-31', 'status' => 'active',
        ]);
    }

    /** @return array{AcademyCycle, ProgrammeCycle, ProgrammeCyclePaymentPlan} */
    private function offering(Academy $academy): array
    {
        $cycle = $this->cycle($academy);
        $offering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => Programme::factory()->for($academy)->create()->id, 'fee_name' => 'Annual tuition',
            'currency' => 'NGN', 'application_fee_minor' => 0,
            'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);
        $standard = $this->plan($offering, 'Standard', ['is_standard' => true, 'is_default' => true]);

        return [$cycle, $offering, $standard];
    }

    /** @param array<string, mixed> $attributes */
    private function plan(ProgrammeCycle $offering, string $name, array $attributes = []): ProgrammeCyclePaymentPlan
    {
        return $offering->paymentPlans()->create([
            'academy_id' => $offering->academy_id, 'term_scope_id' => 0, 'cohort_scope_id' => 0,
            'name' => $name, 'currency' => 'NGN', 'total_minor' => 15000000,
            'is_default' => false, 'is_active' => true, ...$attributes,
        ]);
    }

    private function cycleFee(AcademyCycle $cycle, string $name): AcademyCycleFee
    {
        return AcademyCycleFee::query()->create([
            'academy_id' => $cycle->academy_id, 'academy_cycle_id' => $cycle->id, 'term_scope_id' => 0, 'cohort_scope_id' => 0,
            'name' => $name, 'currency' => 'NGN', 'amount_minor' => 500000, 'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function invoice(Academy $academy, AcademyCycle $cycle, array $attributes): void
    {
        DB::table('academy_invoices')->insert([
            'academy_id' => $academy->id, 'number' => 'INV-'.Str::upper(Str::random(8)), 'academy_cycle_id' => $cycle->id,
            'purpose' => 'custom', 'currency' => 'NGN', 'total_minor' => 500000, 'issued_at' => now(),
            'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function trainingSession(Academy $academy, array $attributes = []): TrainingSession
    {
        return TrainingSession::query()->create([
            'academy_id' => $academy->id,
            'branch_id' => Branch::factory()->for($academy)->create()->id,
            'programme_id' => Programme::factory()->for($academy)->create()->id,
            'title' => 'Saturday training', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2),
            ...$attributes,
        ]);
    }

    private function expense(Academy $academy): AcademyExpense
    {
        return AcademyExpense::query()->create([
            'academy_id' => $academy->id, 'category' => 'Equipment', 'description' => 'Cones',
            'currency' => 'NGN', 'amount_minor' => 250000, 'incurred_on' => today(),
        ]);
    }

    private function application(Academy $academy, string $reference): Application
    {
        return Application::query()->create([
            'academy_id' => $academy->id, 'reference' => $reference, 'first_name' => 'Kemi', 'last_name' => 'Bello',
        ]);
    }

    private function product(Academy $academy, string $name): Product
    {
        return Product::query()->create([
            'academy_id' => $academy->id, 'name' => $name, 'slug' => Str::slug($name), 'currency' => 'NGN', 'base_price_minor' => 500000,
        ]);
    }

    /** @return array{User, Academy} */
    private function member(AcademyRole $role): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }

    private function memberOf(Academy $academy, AcademyRole $role): User
    {
        $user = User::factory()->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return $user;
    }
}
