<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Enums\ApplicationStatus;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\AcademyMembership;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Player;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_filter_tenant_application(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->for($academy)->create();
        $branch = Branch::factory()->for($academy)->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.applications.store'), [
                'internal_identifier' => 'lrn-001', 'first_name' => 'Amara', 'middle_name' => 'Chidi',
                'last_name' => 'Okafor', 'date_of_birth' => '2014-05-02', 'gender' => 'Female',
                'phone' => '08011112222', 'whatsapp_number' => '08011112223',
                'home_address' => '12 Academy Road', 'city' => 'Lagos', 'state_region' => 'Lagos',
                'country' => 'Nigeria',
                'guardian_first_name' => 'Ada', 'guardian_last_name' => 'Okafor',
                'guardian_phone' => '08012345678', 'guardian_relationship' => 'Mother',
                'guardian_whatsapp_number' => '08087654321',
                'guardian_signature' => UploadedFile::fake()->image('signature.png', 400, 120),
                'programme_id' => $programme->id, 'branch_id' => $branch->id,
            ])->assertRedirect()->assertInertiaFlash('toast.message', 'Application created.');

        $application = Application::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertStringStartsWith('APP-', $application->reference);
        $this->assertSame('LRN-001', $application->internal_identifier);
        $this->assertSame('08011112223', $application->whatsapp_number);
        $this->assertSame('08087654321', $application->guardian_whatsapp_number);
        $this->assertNotNull($application->guardian_signature_path);
        Storage::disk('local')->assertExists($application->guardian_signature_path);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.applications.index', ['search' => 'Amara', 'status' => 'submitted']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/applications')->has('applications.data', 1)
                ->where('applications.data.0.first_name', 'Amara')
                ->where('applications.data.0.age', $application->date_of_birth?->age)
                ->where('countries', fn ($countries): bool => $countries->contains('Nigeria') && $countries->contains('Ghana'))
            );
    }

    public function test_application_rejects_unsupported_gender_and_country_values(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.applications.store'), [
                'first_name' => 'Amara', 'last_name' => 'Okafor',
                'gender' => 'Unknown', 'country' => 'Atlantis',
            ])->assertSessionHasErrors(['gender', 'country']);
    }

    public function test_owner_can_edit_application_details_without_changing_status_or_reference(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $application = $this->application($academy, ['guardian_signature_path' => 'existing-signature.png']);
        Storage::disk('local')->put('existing-signature.png', 'signature');
        $reference = $application->reference;

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.applications.update', $application), [
                'first_name' => 'Amarachi', 'middle_name' => 'Chidi', 'last_name' => 'Okafor',
                'date_of_birth' => '2014-05-02', 'gender' => 'Female', 'country' => 'Nigeria',
                'guardian_first_name' => 'Ada', 'guardian_last_name' => 'Okafor',
                'guardian_phone' => '08099999999', 'guardian_relationship' => 'Mother',
            ])->assertRedirect()->assertInertiaFlash('toast.message', 'Application details updated.');

        $application->refresh();
        $this->assertSame('Amarachi', $application->first_name);
        $this->assertSame('Chidi', $application->middle_name);
        $this->assertSame('08099999999', $application->guardian_phone);
        $this->assertSame($reference, $application->reference);
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame('existing-signature.png', $application->guardian_signature_path);
        Storage::disk('local')->assertExists('existing-signature.png');

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.applications.show', $application))
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/application-details')
                ->where('application.first_name', 'Amarachi')
                ->where('application.guardian_phone', '08099999999')
                ->has('active_cycles'));
    }

    public function test_enrolled_or_cross_tenant_application_cannot_be_edited(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $application = $this->application($academy, [
            'status' => ApplicationStatus::Enrolled,
            'converted_player_id' => Player::factory()->for($academy)->create()->id,
        ]);
        $payload = ['first_name' => 'Changed', 'last_name' => 'Name'];

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.applications.update', $application), $payload)
            ->assertSessionHasErrors('application');

        $foreign = $this->application(Academy::factory()->create());
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.applications.update', $foreign), $payload)->assertNotFound();
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.applications.show', $foreign))->assertNotFound();
        $this->assertSame('Amara', $application->fresh()->first_name);
    }

    public function test_cross_tenant_programme_and_branch_are_rejected(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.applications.store'), [
                'first_name' => 'Test', 'last_name' => 'Applicant',
                'programme_id' => $programme->id, 'branch_id' => $branch->id,
            ])->assertSessionHasErrors(['programme_id', 'branch_id']);
    }

    public function test_accepted_application_converts_atomically_to_player_guardian_and_enrollment(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->for($academy)->create();
        $branch = Branch::factory()->for($academy)->create();
        $customField = AcademyCustomField::query()->create([
            'academy_id' => $academy->id, 'entity_type' => 'player', 'key' => 'jersey_size',
            'label' => 'Jersey size', 'type' => 'text', 'options' => null,
            'is_required' => false, 'is_sensitive' => false, 'is_active' => true, 'sort_order' => 0,
        ]);
        $applicationSignature = "academies/{$academy->id}/applications/signatures/source.png";
        Storage::disk('local')->put($applicationSignature, 'private signature');
        $application = $this->application($academy, [
            'internal_identifier' => 'LRN-002', 'middle_name' => 'Chidi', 'gender' => 'Female',
            'phone' => '08011112222', 'whatsapp_number' => '08011112223',
            'home_address' => '12 Academy Road', 'city' => 'Lagos', 'state_region' => 'Lagos',
            'country' => 'Nigeria', 'guardian_email' => 'ada@example.test',
            'guardian_whatsapp_number' => '08087654321', 'guardian_signature_path' => $applicationSignature,
            'custom_fields' => ['jersey_size' => 'Medium'],
        ]);

        $this->review($owner, $academy, $application, 'review')->assertRedirect();
        $this->review($owner, $academy, $application, 'accept', $programme, $branch)->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Enrolled, $application->status);
        $this->assertNotNull($application->converted_player_id);
        $player = Player::query()->findOrFail($application->converted_player_id);
        $this->assertSame([
            'LRN-002', 'Amara', 'Chidi', 'Okafor', '2014-05-02', 'Female',
            '08011112222', '08011112223', '12 Academy Road', 'Lagos', 'Lagos', 'Nigeria',
        ], [
            $player->internal_identifier, $player->first_name, $player->middle_name, $player->last_name,
            $player->date_of_birth?->toDateString(), $player->gender, $player->phone,
            $player->whatsapp_number, $player->home_address, $player->city, $player->state_region, $player->country,
        ]);
        $guardian = Guardian::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame('ada@example.test', $guardian->email);
        $this->assertSame('08087654321', $guardian->whatsapp_number);
        $this->assertNotNull($guardian->signature_path);
        $this->assertNotSame($applicationSignature, $guardian->signature_path);
        Storage::disk('local')->assertExists($applicationSignature);
        Storage::disk('local')->assertExists($guardian->signature_path);
        $this->assertDatabaseHas('player_guardian', [
            'academy_id' => $academy->id, 'player_id' => $player->id,
            'guardian_id' => $guardian->id, 'relationship' => 'Mother',
        ]);
        $this->assertSame('Medium', $player->customFieldValues()->where('custom_field_id', $customField->id)->firstOrFail()->value);
        $this->assertDatabaseHas('enrollments', [
            'academy_id' => $academy->id, 'player_id' => $application->converted_player_id,
            'programme_id' => $programme->id, 'branch_id' => $branch->id,
        ]);
    }

    public function test_duplicate_profile_blocks_application_conversion(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->for($academy)->create();
        $application = $this->application($academy);
        Player::factory()->for($academy)->create([
            'first_name' => $application->first_name, 'last_name' => $application->last_name,
            'date_of_birth' => $application->date_of_birth,
        ]);
        $application->update(['status' => ApplicationStatus::Accepted]);

        $this->review($owner, $academy, $application, 'enrol', $programme)
            ->assertSessionHasErrors('decision');
        $this->assertSame(ApplicationStatus::Accepted, $application->fresh()->status);
        $this->assertNull($application->fresh()->converted_player_id);
    }

    public function test_non_admin_and_cross_tenant_access_are_denied(): void
    {
        [$coach, $academy] = $this->member(AcademyRole::Coach);
        $foreignApplication = $this->application(Academy::factory()->create());
        $this->actingAs($coach)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.applications.index'))->assertForbidden();

        [$owner, $ownAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $this->actingAs($owner)->withSession(['active_academy_id' => $ownAcademy->id])
            ->patch(route('academy.applications.review', $foreignApplication), [
                'decision' => 'reject', 'review_notes' => 'Not eligible',
            ])->assertNotFound();
    }

    /** @param array<string, mixed> $overrides */
    private function application(Academy $academy, array $overrides = []): Application
    {
        return Application::query()->create([
            'academy_id' => $academy->id, 'reference' => 'APP-'.str()->upper(str()->random(10)),
            'first_name' => 'Amara', 'last_name' => 'Okafor', 'date_of_birth' => '2014-05-02',
            'guardian_first_name' => 'Ada', 'guardian_last_name' => 'Okafor',
            'guardian_phone' => '08012345678', 'guardian_relationship' => 'Mother',
            'status' => ApplicationStatus::Submitted,
            ...$overrides,
        ]);
    }

    private function review(User $user, Academy $academy, Application $application, string $decision, ?Programme $programme = null, ?Branch $branch = null): TestResponse
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.applications.review', $application), [
                'decision' => $decision, 'review_notes' => 'Reviewed',
                'programme_id' => $programme?->id, 'branch_id' => $branch?->id,
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
}
