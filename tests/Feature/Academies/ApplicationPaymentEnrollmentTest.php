<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleFee;
use App\Models\AcademyInvoice;
use App\Models\AcademyMembership;
use App\Models\Application;
use App\Models\Cohort;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationPaymentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_compulsory_cycle_fee_is_issued_when_an_application_becomes_a_player(): void
    {
        [$owner, $academy] = $this->member();
        [$cycle, $offering] = $this->offering($academy, 'fixed', 'full_payment', 0, 'one_off');
        $fee = AcademyCycleFee::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'name' => 'Insurance', 'category' => 'insurance', 'currency' => 'NGN',
            'amount_minor' => 500000, 'is_compulsory' => true,
            'applies_to_all_programmes' => true, 'is_active' => true,
            'created_by_user_id' => $owner->id,
        ]);
        $application = $this->submitApplication($owner, $academy, $offering);

        $this->review($owner, $academy, $application, 'accept')->assertRedirect();

        $application->refresh();
        $this->assertNotNull($application->converted_player_id);
        $this->assertDatabaseHas('academy_invoices', [
            'academy_id' => $academy->id, 'application_id' => $application->id,
            'player_id' => $application->converted_player_id, 'academy_cycle_fee_id' => $fee->id,
            'purpose' => 'custom', 'total_minor' => 500000, 'status' => 'issued',
        ]);
    }

    public function test_paid_application_fee_then_acceptance_enrolls_with_programme_balance_still_outstanding(): void
    {
        [$owner, $academy] = $this->member();
        [$cycle, $offering] = $this->offering($academy, 'fixed', 'first_installment', 5000);
        $application = $this->submitApplication($owner, $academy, $offering);
        $applicationInvoice = $application->invoices()->where('purpose', 'application')->firstOrFail();
        $this->assertSame(5000, $applicationInvoice->total_minor);

        $this->review($owner, $academy, $application, 'accept')->assertSessionHasErrors('decision');
        $this->recordPayment($owner, $academy, $applicationInvoice, '50.00', 'APP-FEE-001')
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Verified payment recorded.');
        $this->assertSame('paid', $applicationInvoice->fresh()->status->value);
        $this->assertDatabaseCount('players', 0);

        $this->review($owner, $academy, $application, 'accept')->assertRedirect();
        $application->refresh();
        $this->assertSame('enrolled', $application->status->value);
        $this->assertNotNull($application->converted_player_id);
        $programmeInvoice = $application->invoices()->where('purpose', 'programme')->firstOrFail();
        $this->assertSame('Programme fee', $programmeInvoice->fee_name);
        $this->assertSame(15000, $programmeInvoice->total_minor);
        $this->assertSame(15000, $programmeInvoice->balanceMinor());
        $this->assertSame($application->converted_player_id, $programmeInvoice->player_id);
        $this->assertSame(['Tuition', 'Kit'], $programmeInvoice->items()->orderBy('sort_order')->pluck('description')->all());
        foreach ([['50.00', '90.00'], ['70.00', '90.00']] as [$firstAmount, $finalAmount]) {
            $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
                ->patch(route('academy.invoices.installments.update', $programmeInvoice), [
                    'installments' => [
                        ['name' => 'First', 'amount' => $firstAmount, 'due_on' => '2026-10-20'],
                        ['name' => 'Final', 'amount' => $finalAmount, 'due_on' => '2026-11-20'],
                    ],
                ])->assertSessionHasErrors('installments');
        }
        $this->assertSame(15000, (int) $programmeInvoice->installments()->sum('amount_minor'));
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.invoices.installments.update', $programmeInvoice), [
                'installments' => [
                    ['name' => 'Agreed first payment', 'amount' => '60.00', 'due_on' => '2026-10-20'],
                    ['name' => 'Agreed balance', 'amount' => '90.00', 'due_on' => '2026-11-20'],
                ],
            ])->assertRedirect()->assertInertiaFlash('toast.message', 'Student-specific installment schedule saved.');

        $this->recordPayment($owner, $academy, $programmeInvoice, '60.00', 'PROGRAMME-FIRST-001')->assertRedirect();
        $application->refresh();
        $this->assertSame('enrolled', $application->status->value);
        $enrollment = $application->convertedPlayer->enrollments()->firstOrFail();
        $this->assertSame($cycle->id, $enrollment->academy_cycle_id);
        $this->assertSame($offering->id, $enrollment->programme_cycle_id);
        $this->assertSame(today()->toDateString(), $enrollment->starts_on->toDateString());
        $this->assertSame('2026-12-31', $enrollment->ends_on?->toDateString());
        $this->assertSame('partially_paid', $programmeInvoice->fresh()->status->value);
        $this->assertSame(9000, $programmeInvoice->fresh()->balanceMinor());
        $this->assertDatabaseHas('academy_payments', [
            'academy_id' => $academy->id, 'player_id' => $application->converted_player_id,
            'external_reference' => 'PROGRAMME-FIRST-001', 'amount_minor' => 6000,
        ]);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.players.show', $application->converted_player_id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/player-details')
                ->where('player.first_name', 'Amara')
                ->where('player.guardians.0.name', 'Ada Okafor')
                ->where('player.guardians.0.relationship', 'Parent')
                ->where('player.total_billed', '200.00')
                ->where('player.total_paid', '110.00')
                ->where('player.total_owed', '90.00')
                ->where('player.cycles.0.name', '2026 Academic Year')
                ->where('player.cycles.0.enrollments.0.programme', $offering->programme->name)
                ->has('player.cycles.0.payments', 2));
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.invoices.installments.update', $programmeInvoice), [
                'installments' => [['name' => 'Changed', 'amount' => '150.00', 'due_on' => '2026-11-20']],
            ])->assertSessionHasErrors('installments');

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.payments.index', [
                'cycle_id' => $cycle->id, 'programme_id' => $offering->programme_id,
            ]))->assertInertia(fn (Assert $page) => $page
            ->component('academies/payments')->has('payments.data', 2)
            ->where('payments.data.0.payer_name', 'Amara Okafor')
            ->where('payments.data.0.player_id', $application->converted_player_id)
            ->where('payments.data.0.cycle_name', '2026 Academic Year')
            ->where('payments.data.0.programme_name', $offering->programme->name));
    }

    public function test_rolling_cycle_starts_on_acceptance_even_when_programme_fee_is_unpaid(): void
    {
        [$owner, $academy] = $this->member();
        [$cycle, $offering] = $this->offering($academy, 'rolling', 'full_payment', 0, 'one_off');
        $application = $this->submitApplication($owner, $academy, $offering);
        $this->review($owner, $academy, $application, 'accept')->assertRedirect();
        $invoice = $application->invoices()->where('purpose', 'programme')->firstOrFail();
        $application->refresh();
        $enrollment = $application->convertedPlayer->enrollments()->firstOrFail();
        $this->assertSame('enrolled', $application->status->value);
        $this->assertSame(today()->toDateString(), $enrollment->starts_on->toDateString());
        $this->assertSame($cycle->rollingEndDate(today())->toDateString(), $enrollment->ends_on?->toDateString());
        $this->assertSame(today()->toDateString(), $invoice->installments()->firstOrFail()->due_on?->toDateString());

        $this->recordPayment($owner, $academy, $invoice, '50.00', 'ROLLING-PART-1', '2026-10-15')->assertRedirect();
        $this->assertSame('enrolled', $application->fresh()->status->value);
        $this->assertDatabaseCount('players', 1);
        $this->recordPayment($owner, $academy, $invoice->fresh(), '100.00', 'ROLLING-PART-2', '2026-10-20')->assertRedirect();

        $application->refresh();
        $this->assertSame($cycle->id, $enrollment->academy_cycle_id);
        $this->assertSame('paid', $invoice->fresh()->status->value);
    }

    public function test_student_can_pay_in_full_even_when_the_programme_has_a_default_installment_schedule(): void
    {
        [$owner, $academy] = $this->member();
        [, $offering] = $this->offering($academy, 'fixed', 'full_payment', 0);
        $application = $this->submitApplication($owner, $academy, $offering);
        $this->review($owner, $academy, $application, 'accept')->assertRedirect();
        $invoice = $application->invoices()->where('purpose', 'programme')->firstOrFail();

        $this->assertCount(2, $invoice->installments);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.invoices.payments.store', $invoice), [
                'amount' => '150.00', 'method' => 'cheque', 'received_on' => '2026-10-20',
                'external_reference' => 'CHQ-FULL-WITH-INSTALLMENTS',
            ])->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertSame(15000, (int) $invoice->installments()->sum('paid_minor'));
        $this->assertSame('enrolled', $application->fresh()->status->value);
        $this->assertDatabaseHas('academy_payments', [
            'academy_id' => $academy->id, 'method' => 'cheque',
            'external_reference' => 'CHQ-FULL-WITH-INSTALLMENTS',
        ]);
    }

    public function test_legacy_accepted_applications_can_be_backfilled_without_settling_programme_balance(): void
    {
        [$owner, $academy] = $this->member();
        [, $offering] = $this->offering($academy, 'fixed', 'full_payment', 0, 'one_off');
        $application = $this->submitApplication($owner, $academy, $offering);
        $application->update([
            'status' => 'accepted', 'reviewed_by_user_id' => $owner->id,
            'reviewed_at' => now()->subDay(),
        ]);

        $this->artisan('applications:enroll-accepted')->assertSuccessful()
            ->expectsOutput('Converted 1 accepted application(s); 0 failed.');

        $application->refresh();
        $this->assertSame('enrolled', $application->status->value);
        $this->assertNotNull($application->converted_player_id);
        $invoice = $application->invoices()->where('purpose', 'programme')->firstOrFail();
        $this->assertSame(15000, $invoice->balanceMinor());
        $this->assertSame($application->converted_player_id, $invoice->player_id);
    }

    public function test_application_requires_a_cohort_from_an_active_cycle(): void
    {
        [$owner, $academy] = $this->member();
        [, $offering] = $this->offering($academy, 'fixed', 'full_payment', 5000, 'one_off');

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.applications.store'), [
                'first_name' => 'Amara', 'last_name' => 'Okafor',
                'programme_id' => $offering->programme_id,
            ])->assertSessionHasErrors('cohort_id');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_overpayment_and_cross_tenant_payment_attempt_do_not_change_ledger(): void
    {
        [$owner, $academy] = $this->member();
        [, $offering] = $this->offering($academy, 'fixed', 'full_payment', 0, 'one_off');
        $application = $this->submitApplication($owner, $academy, $offering);
        $this->review($owner, $academy, $application, 'accept');
        $invoice = $application->invoices()->where('purpose', 'programme')->firstOrFail();
        $this->recordPayment($owner, $academy, $invoice, '151.00', 'TOO-MUCH')->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('academy_payments', 0);
        $this->assertSame(0, $invoice->fresh()->paid_minor);

        [$otherOwner, $otherAcademy] = $this->member();
        $this->recordPayment($otherOwner, $otherAcademy, $invoice, '10.00', 'FOREIGN')->assertNotFound();
        $this->assertDatabaseCount('academy_payments', 0);
    }

    public function test_programme_offering_cannot_change_after_application_invoice_is_issued(): void
    {
        [$owner, $academy] = $this->member();
        [$cycle, $offering] = $this->offering($academy, 'fixed', 'full_payment', 5000, 'one_off');
        $application = $this->submitApplication($owner, $academy, $offering);
        $otherProgramme = Programme::factory()->for($academy)->create();
        $otherOffering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $otherProgramme->id, 'currency' => 'NGN',
            'application_fee_minor' => 0, 'payment_mode' => 'one_off',
            'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);
        $otherCohort = Cohort::query()->create([
            'academy_id' => $academy->id, 'programme_cycle_id' => $otherOffering->id,
            'name' => 'Other cohort', 'starts_on' => '2026-01-01', 'status' => 'open',
        ]);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.applications.update', $application), [
                'first_name' => $application->first_name, 'last_name' => $application->last_name,
                'cohort_id' => $otherCohort->id,
                'programme_cycle_id' => $otherOffering->id, 'programme_id' => $otherProgramme->id,
            ])->assertSessionHasErrors('cohort_id');

        $this->assertSame($offering->id, $application->fresh()->programme_cycle_id);
    }

    /** @return array{AcademyCycle, ProgrammeCycle} */
    private function offering(
        Academy $academy,
        string $type,
        string $rule,
        int $applicationFee,
        string $paymentMode = 'installments',
    ): array {
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => $type === 'fixed' ? '2026 Academic Year' : 'Six Months',
            'type' => $type, 'starts_on' => $type === 'fixed' ? '2026-01-01' : null,
            'ends_on' => $type === 'fixed' ? '2026-12-31' : null,
            'duration_value' => $type === 'rolling' ? 6 : null,
            'duration_unit' => $type === 'rolling' ? 'months' : null, 'status' => 'active',
        ]);
        $programme = Programme::factory()->for($academy)->create();
        $offering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'currency' => 'NGN',
            'application_fee_minor' => $applicationFee, 'payment_mode' => $paymentMode,
            'enrollment_payment_rule' => $rule, 'is_active' => true,
        ]);
        $offering->feeItems()->createMany([
            ['academy_id' => $academy->id, 'name' => 'Tuition', 'amount_minor' => 10000, 'sort_order' => 0],
            ['academy_id' => $academy->id, 'name' => 'Kit', 'amount_minor' => 5000, 'sort_order' => 1],
        ]);
        if ($paymentMode === 'installments') {
            $offering->installments()->createMany([
                ['academy_id' => $academy->id, 'name' => 'First', 'amount_minor' => 10000, 'due_on' => $type === 'fixed' ? '2026-10-20' : null, 'due_offset_days' => $type === 'rolling' ? 0 : null, 'sequence' => 1],
                ['academy_id' => $academy->id, 'name' => 'Final', 'amount_minor' => 5000, 'due_on' => $type === 'fixed' ? '2026-11-20' : null, 'due_offset_days' => $type === 'rolling' ? 60 : null, 'sequence' => 2],
            ]);
        }
        Cohort::query()->create([
            'academy_id' => $academy->id, 'programme_cycle_id' => $offering->id,
            'name' => 'Default cohort', 'starts_on' => $type === 'fixed' ? '2026-01-01' : now()->toDateString(),
            'ends_on' => $type === 'fixed' ? '2026-12-31' : null, 'status' => 'open',
        ]);

        return [$cycle, $offering];
    }

    private function submitApplication(User $owner, Academy $academy, ProgrammeCycle $offering): Application
    {
        $cohort = Cohort::query()->where('programme_cycle_id', $offering->id)->firstOrFail();
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.applications.store'), [
                'first_name' => 'Amara', 'last_name' => 'Okafor', 'date_of_birth' => '2014-05-02',
                'guardian_first_name' => 'Ada', 'guardian_last_name' => 'Okafor', 'guardian_phone' => '08012345678',
                'cohort_id' => $cohort->id,
                'programme_cycle_id' => $offering->id, 'programme_id' => $offering->programme_id,
            ])->assertRedirect();

        return Application::query()->whereBelongsTo($academy)->firstOrFail();
    }

    private function review(User $owner, Academy $academy, Application $application, string $decision)
    {
        return $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.applications.review', $application), [
                'decision' => $decision, 'review_notes' => 'Reviewed',
            ]);
    }

    private function recordPayment(
        User $owner,
        Academy $academy,
        AcademyInvoice $invoice,
        string $amount,
        string $reference,
        string $receivedOn = '2026-10-20',
    ) {
        return $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.invoices.payments.store', $invoice), [
                'amount' => $amount, 'method' => 'bank_transfer', 'received_on' => $receivedOn,
                'external_reference' => $reference,
            ]);
    }

    /** @return array{User, Academy} */
    private function member(): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$user, $academy];
    }
}
