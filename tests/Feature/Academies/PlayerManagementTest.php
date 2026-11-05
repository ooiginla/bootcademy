<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\AcademyMembership;
use App\Models\Branch;
use App\Models\Guardian;
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

class PlayerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_guardian_and_player_with_tenant_scoped_link(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.guardians.store'), [
                'first_name' => 'Ada', 'last_name' => 'Okafor', 'phone' => '+2348012345678', 'email' => 'ADA@example.com',
            ])->assertRedirect()->assertInertiaFlash('toast.message', 'Guardian added.');

        $guardian = Guardian::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame('ada@example.com', $guardian->email);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.store'), [
                'first_name' => 'Amara', 'last_name' => 'Okafor', 'internal_identifier' => 'lrn-100',
                'date_of_birth' => '2014-05-02', 'status' => 'active', 'guardian_ids' => [$guardian->id],
            ])->assertRedirect()->assertInertiaFlash('toast.message', 'Player profile created.');

        $player = Player::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame('LRN-100', $player->internal_identifier);
        $this->assertTrue($player->guardians()->whereKey($guardian->id)->exists());
        $this->assertDatabaseHas('player_guardian', [
            'academy_id' => $academy->id, 'player_id' => $player->id, 'guardian_id' => $guardian->id, 'is_primary' => true,
        ]);
    }

    public function test_player_can_have_multiple_simultaneous_programme_enrollments(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $player = Player::factory()->for($academy)->create();
        $branch = Branch::factory()->for($academy)->create();
        $programmes = Programme::factory()->count(2)->for($academy)->create();

        foreach ($programmes as $programme) {
            $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
                ->post(route('academy.players.enrollments.store', $player), [
                    'programme_id' => $programme->id, 'branch_id' => $branch->id,
                    'status' => 'active', 'starts_on' => '2026-10-28', 'ends_on' => null,
                ])->assertRedirect();
        }

        $this->assertCount(2, $player->enrollments);
        $this->assertEqualsCanonicalizing($programmes->pluck('id')->all(), $player->enrollments->pluck('programme_id')->all());

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.enrollments.store', $player), [
                'programme_id' => $programmes->first()->id, 'branch_id' => $branch->id,
                'status' => 'active', 'starts_on' => '2026-11-01', 'ends_on' => null,
            ])->assertSessionHasErrors('programme_id');
        $this->assertCount(2, $player->fresh()->enrollments);
    }

    public function test_same_programme_can_be_booked_for_a_different_branch(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $player = Player::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $branches = Branch::factory()->count(2)->for($academy)->create();

        foreach ($branches as $branch) {
            $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
                ->post(route('academy.players.enrollments.store', $player), [
                    'programme_id' => $programme->id, 'branch_id' => $branch->id,
                    'status' => 'active', 'starts_on' => '2026-10-31',
                ])->assertRedirect();
        }

        $this->assertCount(2, $player->fresh()->enrollments);
    }

    public function test_database_constraint_rejects_duplicate_membership_key(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $player = Player::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $branch = Branch::factory()->for($academy)->create();
        $payload = [
            'programme_id' => $programme->id, 'branch_id' => $branch->id,
            'status' => 'active', 'starts_on' => '2026-10-31',
        ];
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.enrollments.store', $player), $payload)->assertRedirect();
        $key = $player->enrollments()->firstOrFail()->uniqueness_key;

        $this->expectException(QueryException::class);
        DB::table('enrollments')->insert([
            'academy_id' => $academy->id, 'player_id' => $player->id,
            'programme_id' => $programme->id, 'branch_id' => $branch->id,
            'uniqueness_key' => $key, 'status' => 'active', 'starts_on' => '2026-11-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_player_page_returns_only_active_academy_records(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        Player::factory()->for($academy)->create(['first_name' => 'Current', 'last_name' => 'Learner']);
        Player::factory()->create(['first_name' => 'Hidden', 'last_name' => 'Learner']);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.players.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/players-directory')
                ->has('players.data', 1)
                ->where('players.data.0.first_name', 'Current')
            );
    }

    public function test_player_and_guardian_directories_are_paginated(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        Player::factory()->count(16)->for($academy)->create();
        Guardian::factory()->count(16)->for($academy)->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.players.index', ['tab' => 'guardians']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('active_tab', 'guardians')
                ->has('players.data', 15)
                ->where('players.total', 16)
                ->has('guardians.data', 15)
                ->where('guardians.total', 16)
            );
    }

    public function test_guardian_picker_search_is_limited_to_active_academy(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        Guardian::factory()->for($academy)->create(['first_name' => 'Theresa', 'last_name' => 'Iginla']);
        Guardian::factory()->create(['first_name' => 'Theresa', 'last_name' => 'Hidden']);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->getJson(route('academy.guardians.search', ['q' => 'Theresa']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Theresa Iginla');
    }

    public function test_cross_tenant_guardian_programme_and_branch_are_rejected(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $otherGuardian = Guardian::factory()->create();
        $otherProgramme = Programme::factory()->create();
        $otherBranch = Branch::factory()->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.store'), [
                'first_name' => 'Test', 'last_name' => 'Learner', 'status' => 'active', 'guardian_ids' => [$otherGuardian->id],
            ])->assertSessionHasErrors(['guardian_ids.0']);

        $player = Player::factory()->for($academy)->create();
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.enrollments.store', $player), [
                'programme_id' => $otherProgramme->id, 'branch_id' => $otherBranch->id,
                'status' => 'active', 'starts_on' => '2026-10-28',
            ])->assertSessionHasErrors(['programme_id', 'branch_id']);
    }

    public function test_database_constraint_rejects_cross_tenant_player_guardian_link(): void
    {
        $player = Player::factory()->create();
        $guardian = Guardian::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('player_guardian')->insert([
            'academy_id' => $player->academy_id, 'player_id' => $player->id, 'guardian_id' => $guardian->id,
            'relationship' => 'Guardian', 'is_primary' => true, 'is_emergency_contact' => true,
            'is_authorized_pickup' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_database_constraint_rejects_cross_tenant_custom_field_value(): void
    {
        $player = Player::factory()->create();
        $otherAcademy = Academy::factory()->create();
        $field = AcademyCustomField::query()->create([
            'academy_id' => $otherAcademy->id, 'entity_type' => 'player', 'key' => 'jersey_size',
            'label' => 'Jersey size', 'type' => 'text', 'options' => null,
            'is_required' => false, 'is_sensitive' => false, 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->expectException(QueryException::class);
        DB::table('player_custom_field_values')->insert([
            'academy_id' => $player->academy_id, 'player_id' => $player->id,
            'custom_field_id' => $field->id, 'value' => json_encode('Medium', JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_non_admin_staff_cannot_view_or_manage_player_records(): void
    {
        [$coach, $academy] = $this->member(AcademyRole::Coach);

        $this->actingAs($coach)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.players.index'))->assertForbidden();
        $this->actingAs($coach)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.guardians.store'), [
                'first_name' => 'Ada', 'last_name' => 'Okafor', 'phone' => '08012345678',
            ])->assertForbidden();
    }

    public function test_cross_tenant_player_and_guardian_updates_return_not_found(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $otherPlayer = Player::factory()->create();
        $otherGuardian = Guardian::factory()->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.players.update', $otherPlayer), [
                'first_name' => 'Changed', 'last_name' => 'Player', 'status' => 'active',
            ])->assertNotFound();
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.guardians.update', $otherGuardian), [
                'first_name' => 'Changed', 'last_name' => 'Guardian', 'phone' => '08012345678',
            ])->assertNotFound();
    }

    public function test_owner_can_configure_typed_fields_and_save_extended_profile_values(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $guardian = Guardian::factory()->for($academy)->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.player-fields.store'), [
                'label' => 'Playing position', 'key' => 'playing_position', 'type' => 'select',
                'options' => 'Forward, Midfielder, Defender', 'is_required' => true,
                'is_sensitive' => false, 'is_active' => true, 'sort_order' => 1,
            ])->assertRedirect()->assertInertiaFlash('toast.message', 'Custom student field created.');

        $field = AcademyCustomField::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame(['Forward', 'Midfielder', 'Defender'], $field->options);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.store'), [
                'first_name' => 'Amara', 'middle_name' => 'Grace', 'last_name' => 'Okafor',
                'date_of_birth' => '2014-05-02', 'gender' => 'Female', 'phone' => '08011111111',
                'whatsapp_number' => '08022222222', 'home_address' => '10 Academy Way',
                'city' => 'Lagos', 'state_region' => 'Lagos', 'country' => 'Nigeria',
                'status' => 'active', 'guardian_ids' => [$guardian->id],
                'guardian_relationships' => [$guardian->id => 'Mother'],
                'custom_fields' => ['playing_position' => 'Forward'],
            ])->assertRedirect();

        $player = Player::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame('Grace', $player->middle_name);
        $this->assertSame('Nigeria', $player->country);
        $this->assertDatabaseHas('player_guardian', [
            'player_id' => $player->id, 'guardian_id' => $guardian->id, 'relationship' => 'Mother',
        ]);
        $this->assertDatabaseHas('player_custom_field_values', [
            'academy_id' => $academy->id, 'player_id' => $player->id, 'custom_field_id' => $field->id,
        ]);
        $this->assertSame('Forward', $player->customFieldValues()->firstOrFail()->value);
    }

    public function test_player_creation_can_create_and_link_a_guardian_inline(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.store'), [
                'first_name' => 'Chidi', 'last_name' => 'Okoro', 'status' => 'active',
                'new_guardian' => [
                    'first_name' => 'Ngozi', 'last_name' => 'Okoro', 'phone' => '08033333333',
                    'whatsapp_number' => '08044444444', 'email' => 'ngozi@example.test', 'relationship' => 'Mother',
                ],
            ])->assertRedirect();

        $player = Player::query()->whereBelongsTo($academy)->firstOrFail();
        $guardian = Guardian::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertDatabaseHas('player_guardian', [
            'academy_id' => $academy->id, 'player_id' => $player->id,
            'guardian_id' => $guardian->id, 'relationship' => 'Mother',
        ]);
        $this->assertSame('08044444444', $guardian->whatsapp_number);
    }

    public function test_guardian_signature_is_private_and_tenant_scoped(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.guardians.store'), [
                'first_name' => 'Ada', 'last_name' => 'Okafor', 'phone' => '08012345678',
                'whatsapp_number' => '08012345678',
                'signature' => UploadedFile::fake()->image('signature.png', 400, 120),
            ])->assertRedirect();

        $guardian = Guardian::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertNotNull($guardian->signature_path);
        Storage::disk('local')->assertExists($guardian->signature_path);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.guardians.signature', $guardian))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        [$otherOwner, $otherAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $this->actingAs($otherOwner)->withSession(['active_academy_id' => $otherAcademy->id])
            ->get(route('academy.guardians.signature', $guardian))->assertNotFound();
    }

    public function test_custom_field_definition_cannot_be_updated_across_tenants(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $otherField = AcademyCustomField::query()->create([
            'academy_id' => Academy::factory()->create()->id, 'entity_type' => 'player', 'key' => 'allergies',
            'label' => 'Allergies', 'type' => 'textarea', 'options' => null,
            'is_required' => false, 'is_sensitive' => true, 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.player-fields.update', $otherField), [
                'label' => 'Changed', 'key' => 'allergies', 'type' => 'textarea', 'options' => [],
                'is_required' => false, 'is_sensitive' => true, 'is_active' => true, 'sort_order' => 0,
            ])->assertNotFound();
    }

    /** @return array{User, Academy} */
    private function member(AcademyRole $role): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }
}
