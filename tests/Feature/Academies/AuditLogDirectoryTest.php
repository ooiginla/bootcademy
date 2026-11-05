<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_filter_read_only_academy_logs_without_cross_tenant_leakage(): void
    {
        [$owner, $academy] = $this->academyOwner();
        $branch = Branch::factory()->for($academy)->create();
        $matching = AuditLog::query()->create([
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.branch.updated',
            'auditable_type' => $branch->getMorphClass(),
            'auditable_id' => $branch->id,
            'metadata' => ['changed_fields' => ['name']],
            'ip_address' => '127.0.0.1',
        ]);
        $matching->forceFill(['created_at' => '2026-11-04 10:00:00'])->save();
        AuditLog::query()->create([
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.player.created',
        ]);

        [$foreignOwner, $foreignAcademy] = $this->academyOwner();
        AuditLog::query()->create([
            'academy_id' => $foreignAcademy->id,
            'actor_user_id' => $foreignOwner->id,
            'action' => 'academy.branch.updated',
            'auditable_type' => $branch->getMorphClass(),
            'auditable_id' => $branch->id,
            'metadata' => ['private' => 'foreign academy data'],
        ]);

        $this->asAcademy($owner, $academy)->get(route('academy.audit-log.index', [
            'actor_user_id' => $owner->id,
            'action' => 'academy.branch.updated',
            'subject_type' => $branch->getMorphClass(),
            'subject_id' => $branch->id,
            'date_from' => '2026-11-04',
            'date_to' => '2026-11-04',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/audit-log')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
            ->where('logs.data.0.actor.email', $owner->email)
            ->where('logs.data.0.action', 'academy.branch.updated')
            ->where('logs.data.0.subject_label', 'Branch')
            ->where('logs.data.0.metadata.changed_fields.0', 'name')
            ->where('filters.subject_id', $branch->id)
        );

        $this->assertDatabaseHas('audit_logs', [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.audit_log.viewed',
        ]);
    }

    public function test_non_owner_roles_cannot_view_the_audit_log(): void
    {
        [, $academy] = $this->academyOwner();

        foreach ([AcademyRole::FinanceOfficer, AcademyRole::BranchManager, AcademyRole::Coach, AcademyRole::ReadOnlyAuditor] as $role) {
            $user = User::factory()->create();
            AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

            $this->asAcademy($user, $academy)->get(route('academy.audit-log.index'))->assertForbidden();
        }
    }

    public function test_super_admin_can_view_an_academy_audit_log_in_support_mode(): void
    {
        [, $academy] = $this->academyOwner();
        $superAdmin = User::factory()->create(['is_platform_super_admin' => true]);
        AuditLog::query()->create([
            'academy_id' => $academy->id,
            'actor_user_id' => $superAdmin->id,
            'action' => 'platform.support_access.started',
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['platform_support_access' => ['academy_id' => $academy->id]])
            ->get(route('academy.audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/audit-log')
                ->where('academy.can_view_audit_log', true)
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'platform.support_access.started')
            );
    }

    /** @return array{User, Academy} */
    private function academyOwner(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$owner, $academy];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
