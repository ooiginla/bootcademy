<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyInvoice;
use App\Models\AcademyMembership;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Player;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\User;
use App\Notifications\AcademyCommunicationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunicationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_upcoming_payments_and_renewals_and_can_send_idempotent_reminders(): void
    {
        Notification::fake();
        [$user, $academy, $invoice, $enrollment] = $this->records();

        $this->asAcademy($user, $academy)->get(route('academy.communications.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/communications')
            ->where('payment_due.0.id', $invoice->id)
            ->where('renewals.0.id', $enrollment->id));

        $this->asAcademy($user, $academy)->post(route('academy.communications.invoices.remind', $invoice))->assertRedirect();
        $this->asAcademy($user, $academy)->post(route('academy.communications.invoices.remind', $invoice))->assertRedirect();
        $this->assertDatabaseCount('notification_deliveries', 1);
        Notification::assertSentOnDemand(AcademyCommunicationNotification::class);
    }

    public function test_owner_can_publish_tenant_scoped_announcement_and_update_automation_settings(): void
    {
        Notification::fake();
        [$user, $academy] = $this->records();

        $this->asAcademy($user, $academy)->post(route('academy.communications.announcements.store'), [
            'title' => 'Training update', 'body' => 'Saturday training starts one hour earlier.',
            'audience_type' => 'all', 'publish_now' => true,
        ])->assertRedirect()->assertInertiaFlash('toast.type', 'success');
        $this->assertDatabaseHas('announcements', ['academy_id' => $academy->id, 'title' => 'Training update', 'status' => 'published']);
        $this->assertDatabaseCount('notification_deliveries', 1);

        $this->asAcademy($user, $academy)->patch(route('academy.communications.settings.update'), [
            'automatic_payment_reminders' => true, 'payment_reminder_days' => [14, 7, 1, 0],
            'automatic_renewal_reminders' => true, 'renewal_reminder_days' => [30, 7],
        ])->assertRedirect();
        $this->assertDatabaseHas('academy_communication_settings', ['academy_id' => $academy->id, 'automatic_payment_reminders' => true, 'automatic_renewal_reminders' => true]);
    }

    public function test_another_academy_cannot_send_a_reminder_for_foreign_invoice(): void
    {
        [$user, $academy] = $this->member();
        [, , $foreignInvoice] = $this->records();

        $this->asAcademy($user, $academy)->post(route('academy.communications.invoices.remind', $foreignInvoice))->assertNotFound();
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    /** @return array{User, Academy, AcademyInvoice, Enrollment} */
    private function records(): array
    {
        [$user, $academy] = $this->member();
        $branch = Branch::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $cycle = AcademyCycle::query()->create(['academy_id' => $academy->id, 'name' => '2027', 'type' => 'fixed', 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth(), 'status' => 'active']);
        $offering = ProgrammeCycle::query()->create(['academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id, 'programme_id' => $programme->id, 'branch_id' => $branch->id, 'branch_scope_id' => $branch->id, 'fee_name' => 'Tuition', 'currency' => 'NGN', 'application_fee_minor' => 0, 'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true]);
        $player = Player::factory()->for($academy)->create();
        $guardian = Guardian::factory()->for($academy)->create(['email' => 'family@example.com']);
        $player->guardians()->attach($guardian->id, ['academy_id' => $academy->id, 'relationship' => 'Parent', 'is_primary' => true, 'is_emergency_contact' => true, 'is_authorized_pickup' => false]);
        $enrollment = Enrollment::factory()->for($player)->for($programme)->create(['academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id, 'programme_cycle_id' => $offering->id, 'branch_id' => $branch->id, 'status' => 'active', 'starts_on' => now()->subMonths(5), 'ends_on' => now()->addDays(20)]);
        $invoice = AcademyInvoice::query()->create(['academy_id' => $academy->id, 'number' => 'INV-REMINDER-1', 'fee_name' => 'Tuition', 'player_id' => $player->id, 'academy_cycle_id' => $cycle->id, 'programme_cycle_id' => $offering->id, 'programme_id' => $programme->id, 'branch_id' => $branch->id, 'purpose' => 'programme', 'status' => 'issued', 'currency' => 'NGN', 'total_minor' => 10000000, 'paid_minor' => 2000000, 'written_off_minor' => 0, 'issued_at' => now(), 'due_on' => now()->addDays(7), 'created_by_user_id' => $user->id]);
        $invoice->installments()->create(['academy_id' => $academy->id, 'name' => 'Balance', 'amount_minor' => 10000000, 'paid_minor' => 2000000, 'written_off_minor' => 0, 'due_on' => now()->addDays(7), 'sequence' => 1]);

        return [$user, $academy, $invoice, $enrollment];
    }

    /** @return array{User, Academy} */
    private function member(): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$user, $academy];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
