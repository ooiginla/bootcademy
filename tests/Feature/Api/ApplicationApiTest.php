<?php

namespace Tests\Feature\Api;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyApiKey;
use App\Models\AcademyCustomField;
use App\Models\AcademyMembership;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_one_time_visible_key_and_can_revoke_it(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.api-keys.store'), ['name' => 'Website'])
            ->assertRedirect(route('academy.api-keys.index'))
            ->assertInertiaFlash('toast.message', 'API key created. Copy it now; it will not be shown again.');

        $key = AcademyApiKey::query()->whereBelongsTo($academy)->firstOrFail();
        $secret = session('new_api_secret');
        $this->assertIsString($secret);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.api-keys.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/api-integrations')
                ->has('api_keys', 1)
                ->where('new_api_secret', fn ($secret): bool => is_string($secret) && str_starts_with($secret, 'aca_')));
        $this->assertNotSame($secret, $key->secret_hash);
        $this->assertSame(hash('sha256', $secret), $key->secret_hash);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.api-keys.revoke', $key))->assertRedirect();
        $this->assertNotNull($key->fresh()->revoked_at);

        [$otherOwner, $otherAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $this->actingAs($otherOwner)->withSession(['active_academy_id' => $otherAcademy->id])
            ->patch(route('academy.api-keys.revoke', $key))->assertNotFound();
        [$coach, $coachAcademy] = $this->member(AcademyRole::Coach);
        $this->actingAs($coach)->withSession(['active_academy_id' => $coachAcademy->id])
            ->get(route('academy.api-keys.index'))->assertForbidden();
    }

    public function test_api_creates_idempotent_application_with_consent_and_never_creates_player(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$key, $secret] = $this->apiKey($academy, $owner);
        $programme = Programme::factory()->for($academy)->create();
        $branch = Branch::factory()->for($academy)->create();
        AcademyCustomField::query()->create([
            'academy_id' => $academy->id, 'entity_type' => 'player', 'key' => 'boot_size',
            'label' => 'Boot size', 'type' => 'number', 'options' => null,
            'is_required' => true, 'is_sensitive' => false, 'is_active' => true, 'sort_order' => 0,
        ]);
        $payload = [
            'external_reference' => 'website-registration-1001', 'consent_accepted' => true,
            'privacy_policy_version' => '2026-10', 'first_name' => 'Amara', 'middle_name' => 'Chidi',
            'last_name' => 'Okafor', 'date_of_birth' => '2014-05-02', 'gender' => 'Female',
            'country' => 'Nigeria', 'guardian_first_name' => 'Ada', 'guardian_last_name' => 'Okafor',
            'guardian_email' => 'ada@example.test', 'guardian_phone' => '08012345678',
            'guardian_relationship' => 'Mother', 'programme_id' => $programme->id, 'branch_id' => $branch->id,
            'custom_fields' => ['boot_size' => 40],
        ];

        $this->withToken($secret)->postJson(route('api.v1.applications.store'), $payload)
            ->assertCreated()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.existing', false);
        $application = Application::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame('api', $application->source);
        $this->assertSame('website-registration-1001', $application->external_reference);
        $this->assertNotNull($application->guardian_consent_at);
        $this->assertSame('2026-10', $application->privacy_policy_version);
        $this->assertSame(['boot_size' => 40], $application->custom_fields);
        $this->assertDatabaseCount('players', 0);
        $this->assertNotNull($key->fresh()->last_used_at);

        $this->withToken($secret)->postJson(route('api.v1.applications.store'), $payload)
            ->assertOk()->assertJsonPath('data.existing', true)
            ->assertJsonPath('data.reference', $application->reference);
        $this->assertDatabaseCount('applications', 1);

        $this->withToken($secret)->postJson(route('api.v1.applications.store'), [
            ...$payload, 'first_name' => 'Changed',
        ])->assertConflict()->assertJsonPath('message', 'This external_reference was already used with different application data.');
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_configuration_status_and_tenant_boundaries_are_enforced(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [, $secret] = $this->apiKey($academy, $owner);
        $programme = Programme::factory()->for($academy)->create(['name' => 'Academy Programme']);
        $foreignProgramme = Programme::factory()->create();

        $this->getJson(route('api.v1.configuration'))->assertUnauthorized();
        $this->withToken($secret)->getJson(route('api.v1.configuration'))
            ->assertOk()->assertJsonPath('data.academy.name', $academy->name)
            ->assertJsonPath('data.programmes.0.id', $programme->id);
        $payload = [
            'external_reference' => 'foreign-programme', 'consent_accepted' => true,
            'privacy_policy_version' => 'v1', 'first_name' => 'Tobi', 'last_name' => 'Adeleke',
            'programme_id' => $foreignProgramme->id,
        ];
        $this->withToken($secret)->postJson(route('api.v1.applications.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('programme_id');

        $this->withToken($secret)->getJson(route('api.v1.applications.show', ['external_reference' => 'missing']))
            ->assertNotFound();
    }

    public function test_duplicate_person_unknown_custom_field_and_revoked_key_are_rejected(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$key, $secret] = $this->apiKey($academy, $owner);
        Application::query()->create([
            'academy_id' => $academy->id, 'reference' => 'APP-EXISTING', 'first_name' => 'Amara',
            'last_name' => 'Okafor', 'date_of_birth' => '2014-05-02', 'status' => 'submitted',
        ]);
        $payload = [
            'external_reference' => 'new-reference', 'consent_accepted' => true,
            'privacy_policy_version' => 'v1', 'first_name' => 'Amara', 'last_name' => 'Okafor',
            'date_of_birth' => '2014-05-02',
        ];
        $this->withToken($secret)->postJson(route('api.v1.applications.store'), $payload)
            ->assertConflict()->assertJsonPath('existing_reference', 'APP-EXISTING');
        $this->withToken($secret)->postJson(route('api.v1.applications.store'), [
            ...$payload, 'external_reference' => 'unknown-field', 'first_name' => 'Different',
            'custom_fields' => ['not_configured' => 'value'],
        ])->assertUnprocessable()->assertJsonValidationErrors('custom_fields.not_configured');

        $key->update(['revoked_at' => now()]);
        $this->withToken($secret)->getJson(route('api.v1.configuration'))->assertUnauthorized();
    }

    /** @return array{AcademyApiKey, string} */
    private function apiKey(Academy $academy, User $creator): array
    {
        $secret = 'aca_'.str()->lower(str()->random(72));
        $key = AcademyApiKey::query()->create([
            'academy_id' => $academy->id, 'created_by_user_id' => $creator->id,
            'name' => 'Website', 'prefix' => substr($secret, 0, 12), 'secret_hash' => hash('sha256', $secret),
        ]);

        return [$key, $secret];
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
