<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgrammeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_programme_for_selected_tenant_branches(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $branches = Branch::factory()->count(2)->for($academy)->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.programmes.store'), $this->payload($branches->pluck('id')->all()))
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Programme created.');

        $programme = Programme::query()->where('academy_id', $academy->id)->firstOrFail();
        $this->assertSame('BEGINNER', $programme->code);
        $this->assertEqualsCanonicalizing($branches->pluck('id')->all(), $programme->branches()->pluck('branches.id')->all());
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.programme.created',
        ]);
    }

    public function test_programme_cannot_attach_a_branch_from_another_academy(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $otherBranch = Branch::factory()->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.programmes.store'), $this->payload([$otherBranch->id]))
            ->assertSessionHasErrors(['branch_ids.0']);

        $this->assertDatabaseMissing(Programme::class, ['academy_id' => $academy->id]);
    }

    public function test_database_constraint_rejects_cross_tenant_programme_branch_pair(): void
    {
        $programme = Programme::factory()->create();
        $otherBranch = Branch::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('programme_branch')->insert([
            'academy_id' => $programme->academy_id,
            'programme_id' => $programme->id,
            'branch_id' => $otherBranch->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_staff_can_view_programmes_but_cannot_manage_them(): void
    {
        [$coach, $academy] = $this->member(AcademyRole::Coach);
        Programme::factory()->for($academy)->create(['name' => 'Young Creators']);

        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.programmes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/programmes')
                ->where('can_manage', false)
                ->where('programmes.0.name', 'Young Creators')
            );

        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.programmes.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_cross_tenant_programme_update_returns_not_found(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $otherProgramme = Programme::factory()->create(['name' => 'Other Programme']);

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.programmes.update', $otherProgramme), $this->payload())
            ->assertNotFound();

        $this->assertSame('Other Programme', $otherProgramme->fresh()->name);
    }

    public function test_owner_can_update_branch_availability_and_deactivate_a_programme(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$firstBranch, $secondBranch] = Branch::factory()->count(2)->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $programme->branches()->attach($firstBranch->id, ['academy_id' => $academy->id]);

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.programmes.update', $programme), [
                ...$this->payload([$secondBranch->id]),
                'name' => 'Advanced Programme',
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Programme updated.');

        $programme->refresh();
        $this->assertSame('Advanced Programme', $programme->name);
        $this->assertFalse($programme->is_active);
        $this->assertSame([$secondBranch->id], $programme->branches()->pluck('branches.id')->all());
    }

    public function test_owner_can_publish_structured_programme_details_and_unsafe_html_is_removed(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->for($academy)->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.programmes.update', $programme), [
                ...$this->payload(),
                'duration' => '4 Weeks',
                'delivery_format' => 'Live and Online',
                'programme_type' => 'Bootcamp',
                'public_timezone' => 'Africa/Lagos',
                'level' => 'Beginner to expert',
                'outcomes_html' => '<h3 onclick="steal()">Build confidently</h3><script>alert(1)</script>',
                'skills_gained_html' => '<ul><li>Product design</li></ul>',
                'curriculum_html' => '<p>Week one</p>',
                'eligibility_html' => '<p>No prior experience.</p>',
                'audience_html' => '<p>Career starters.</p>',
                'demand_html' => '<p>High-demand skills.</p>',
                'application_process_html' => '<p><a href="javascript:alert(1)">Apply</a></p>',
                'tools_platforms' => [['name' => 'Figma', 'logo_url' => 'https://example.test/figma.svg']],
                'faqs' => [['question' => 'Do I need experience?', 'answer_html' => '<p>No.</p>']],
                'testimonials' => [['quote_html' => '<p>Excellent course.</p>', 'name' => 'Ada', 'role' => 'Graduate']],
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Programme updated.');

        $programme->refresh();
        $this->assertSame('4 Weeks', $programme->duration);
        $this->assertSame('Africa/Lagos', $programme->public_timezone);
        $this->assertSame('Figma', $programme->tools_platforms[0]['name']);
        $this->assertSame('Do I need experience?', $programme->faqs[0]['question']);
        $this->assertStringContainsString('<h3>Build confidently</h3>', $programme->outcomes_html);
        $this->assertStringNotContainsString('script', $programme->outcomes_html);
        $this->assertStringNotContainsString('onclick', $programme->outcomes_html);
        $this->assertStringNotContainsString('javascript:', $programme->application_process_html);
    }

    public function test_owner_can_upload_replace_serve_and_remove_a_private_programme_image(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->for($academy)->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.programmes.image.update', $programme), ['image' => $this->validPng('first.png')])
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Programme image updated.');

        $firstPath = $programme->fresh()->hero_image_path;
        $this->assertNotNull($firstPath);
        Storage::disk('local')->assertExists($firstPath);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id, 'actor_user_id' => $owner->id,
            'action' => 'academy.programme.image.updated',
        ]);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.programmes.image', $programme))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.programmes.image.update', $programme), ['image' => $this->validPng('replacement.png')])
            ->assertRedirect();
        Storage::disk('local')->assertMissing($firstPath);
        $replacementPath = $programme->fresh()->hero_image_path;
        Storage::disk('local')->assertExists($replacementPath);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->delete(route('academy.programmes.image.destroy', $programme))
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Programme image removed.');
        $this->assertNull($programme->fresh()->hero_image_path);
        Storage::disk('local')->assertMissing($replacementPath);
    }

    public function test_programme_image_rejects_unsafe_files_and_cross_tenant_updates(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->for($academy)->create();
        $foreignProgramme = Programme::factory()->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.programmes.image.update', $programme), [
                'image' => UploadedFile::fake()->createWithContent('image.svg', '<svg><script/></svg>'),
            ])->assertSessionHasErrors('image');
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.programmes.image.update', $foreignProgramme), ['image' => $this->validPng()])
            ->assertNotFound();

        $this->assertNull($programme->fresh()->hero_image_path);
        $this->assertNull($foreignProgramme->fresh()->hero_image_path);
    }

    public function test_super_admin_acting_as_owner_can_manage_programmes(): void
    {
        $superAdmin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('platform.academies.support-access.start', $academy));
        $this->actingAs($superAdmin)
            ->post(route('academy.programmes.store'), $this->payload())
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Programme created.');

        $this->assertDatabaseHas(Programme::class, [
            'academy_id' => $academy->id,
            'name' => 'Foundation Programme',
        ]);
    }

    public function test_programme_details_include_basic_information_and_branch_filtered_members(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$firstBranch, $secondBranch] = Branch::factory()->count(2)->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create(['name' => 'Under 17']);
        $programme->branches()->attach([$firstBranch->id, $secondBranch->id], ['academy_id' => $academy->id]);
        $firstPlayer = Player::factory()->for($academy)->create(['first_name' => 'Visible']);
        $secondPlayer = Player::factory()->for($academy)->create(['first_name' => 'Filtered']);
        $this->enroll($academy, $programme, $firstPlayer, $firstBranch);
        $this->enroll($academy, $programme, $secondPlayer, $secondBranch);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.programmes.show', ['programme' => $programme, 'tab' => 'members', 'branch' => $firstBranch->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/programme-details')
                ->where('programme.name', 'Under 17')
                ->where('active_tab', 'members')
                ->where('selected_branch_id', $firstBranch->id)
                ->has('members.data', 1)
                ->where('members.data.0.player_name', fn (string $name): bool => str_contains($name, 'Visible'))
                ->where('members.total', 1)
            );
    }

    public function test_branch_scoped_staff_only_see_members_from_assigned_branches(): void
    {
        [$coach, $academy] = $this->member(AcademyRole::Coach);
        [$assignedBranch, $hiddenBranch] = Branch::factory()->count(2)->for($academy)->create();
        $membership = AcademyMembership::query()->whereBelongsTo($coach)->whereBelongsTo($academy)->firstOrFail();
        $membership->branches()->attach($assignedBranch->id, ['academy_id' => $academy->id]);
        $programme = Programme::factory()->for($academy)->create();
        $visiblePlayer = Player::factory()->for($academy)->create(['first_name' => 'Assigned']);
        $hiddenPlayer = Player::factory()->for($academy)->create(['first_name' => 'Hidden']);
        $this->enroll($academy, $programme, $visiblePlayer, $assignedBranch);
        $this->enroll($academy, $programme, $hiddenPlayer, $hiddenBranch);

        $this->actingAs($coach)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.programmes.show', ['programme' => $programme, 'tab' => 'members']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('branch_options', 1)
                ->where('branch_options.0.id', $assignedBranch->id)
                ->has('members.data', 1)
                ->where('members.data.0.player_name', fn (string $name): bool => str_contains($name, 'Assigned'))
            );

        $this->actingAs($coach)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.programmes.show', ['programme' => $programme, 'tab' => 'members', 'branch' => $hiddenBranch->id]))
            ->assertForbidden();
    }

    public function test_cross_tenant_programme_details_return_not_found(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $otherProgramme = Programme::factory()->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.programmes.show', $otherProgramme))
            ->assertNotFound();
    }

    public function test_programme_members_are_paginated(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $branch = Branch::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        Player::factory()->count(21)->for($academy)->create()
            ->each(fn (Player $player) => $this->enroll($academy, $programme, $player, $branch));

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.programmes.show', ['programme' => $programme, 'tab' => 'members']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('members.data', 20)
                ->where('members.total', 21)
                ->where('members.last_page', 2)
            );
    }

    /** @param array<int, int> $branchIds */
    private function payload(array $branchIds = []): array
    {
        return [
            'name' => 'Foundation Programme',
            'code' => 'beginner',
            'description' => 'A structured introduction for new learners.',
            'branch_ids' => $branchIds,
            'is_active' => true,
        ];
    }

    private function enroll(Academy $academy, Programme $programme, Player $player, Branch $branch): Enrollment
    {
        return Enrollment::query()->create([
            'academy_id' => $academy->id,
            'player_id' => $player->id,
            'programme_id' => $programme->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'starts_on' => '2026-10-28',
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

    private function validPng(string $name = 'programme.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $png ?: '');
    }
}
