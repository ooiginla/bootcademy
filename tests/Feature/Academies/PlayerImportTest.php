<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\AcademyMembership;
use App\Models\Branch;
use App\Models\Player;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlayerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_previews_and_atomically_imports_profiles_guardians_enrollments_and_custom_fields(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $programme = Programme::factory()->for($academy)->create(['name' => 'Under 17']);
        $branch = Branch::factory()->for($academy)->create(['name' => 'Central']);
        $field = AcademyCustomField::query()->create([
            'academy_id' => $academy->id, 'entity_type' => 'player', 'key' => 'jersey_size',
            'label' => 'Jersey size', 'type' => 'text', 'options' => null,
            'is_required' => true, 'is_sensitive' => false, 'is_active' => true, 'sort_order' => 0,
        ]);
        $csv = implode("\n", [
            'internal_identifier,first_name,middle_name,last_name,date_of_birth,gender,country,guardian_first_name,guardian_last_name,guardian_email,guardian_phone,guardian_whatsapp_number,guardian_relationship,programme,branch,enrollment_starts_on,custom_jersey_size',
            'LRN-100,Amara,Chidi,Okafor,2014-05-02,Female,Nigeria,Ada,Okafor,ada@example.test,08012345678,08087654321,Mother,Under 17,Central,2026-09-01,Medium',
        ]);

        $response = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.import.preview'), [
                'csv_file' => UploadedFile::fake()->createWithContent('players.csv', $csv),
            ])->assertRedirect();
        $token = $this->tokenFromRedirect($response->headers->get('Location'));

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.players.import.create', ['token' => $token]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/player-import')
                ->where('preview.total_rows', 1)
                ->where('preview.error_rows', 0)
                ->where('preview.can_import', true)
                ->where('preview.rows.0.label', 'Amara Okafor'));

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.import.confirm'), ['token' => $token])
            ->assertRedirect(route('academy.players.index'))
            ->assertInertiaFlash('toast.message', '1 profiles imported successfully.');

        $player = Player::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame('LRN-100', $player->internal_identifier);
        $this->assertSame('Chidi', $player->middle_name);
        $this->assertSame('Nigeria', $player->country);
        $this->assertDatabaseHas('guardians', [
            'academy_id' => $academy->id, 'email' => 'ada@example.test', 'whatsapp_number' => '08087654321',
        ]);
        $this->assertDatabaseHas('player_guardian', [
            'academy_id' => $academy->id, 'player_id' => $player->id, 'relationship' => 'Mother',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'academy_id' => $academy->id, 'player_id' => $player->id,
            'programme_id' => $programme->id, 'branch_id' => $branch->id,
        ]);
        $this->assertSame('2026-09-01', $player->enrollments()->firstOrFail()->starts_on->toDateString());
        $this->assertSame('Medium', $player->customFieldValues()->where('custom_field_id', $field->id)->firstOrFail()->value);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
        $this->assertDatabaseHas('audit_logs', [
            'academy_id' => $academy->id, 'actor_user_id' => $owner->id, 'action' => 'academy.players.imported',
        ]);
    }

    public function test_invalid_and_duplicate_rows_block_the_entire_import_and_have_error_report(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $csv = implode("\n", [
            'internal_identifier,first_name,last_name,date_of_birth,gender,country',
            'LRN-200,Amara,Okafor,2014-05-02,Female,Nigeria',
            'LRN-200,Amara,Okafor,2014-05-02,Unknown,Atlantis',
        ]);
        $response = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.import.preview'), [
                'csv_file' => UploadedFile::fake()->createWithContent('invalid.csv', $csv),
            ])->assertRedirect();
        $token = $this->tokenFromRedirect($response->headers->get('Location'));

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.players.import.create', ['token' => $token]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.total_rows', 2)
                ->where('preview.error_rows', 1)
                ->where('preview.can_import', false));
        $errorReport = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.players.import.errors', ['token' => $token]))
            ->assertOk()->assertDownload('student-import-errors.csv');
        $this->assertStringContainsString('duplicates another row', $errorReport->streamedContent());
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.import.confirm'), ['token' => $token])
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseCount('players', 0);
    }

    public function test_import_token_is_user_and_tenant_scoped_and_non_admin_is_denied(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $response = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.players.import.preview'), [
                'csv_file' => UploadedFile::fake()->createWithContent('players.csv', "first_name,last_name\nAmara,Okafor"),
            ])->assertRedirect();
        $token = $this->tokenFromRedirect($response->headers->get('Location'));

        [$otherOwner, $otherAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $this->actingAs($otherOwner)->withSession(['active_academy_id' => $otherAcademy->id])
            ->get(route('academy.players.import.create', ['token' => $token]))->assertNotFound();

        [$coach, $coachAcademy] = $this->member(AcademyRole::Coach);
        $this->actingAs($coach)->withSession(['active_academy_id' => $coachAcademy->id])
            ->get(route('academy.players.import.create'))->assertForbidden();
        $this->actingAs($coach)->withSession(['active_academy_id' => $coachAcademy->id])
            ->post(route('academy.players.import.preview'), [
                'csv_file' => UploadedFile::fake()->createWithContent('players.csv', "first_name,last_name\nTest,Player"),
            ])->assertForbidden();
    }

    public function test_template_contains_active_academy_custom_fields(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        AcademyCustomField::query()->create([
            'academy_id' => $academy->id, 'entity_type' => 'player', 'key' => 'allergies',
            'label' => 'Allergies', 'type' => 'textarea', 'options' => null,
            'is_required' => false, 'is_sensitive' => true, 'is_active' => true, 'sort_order' => 0,
        ]);

        $template = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.players.import.template'))
            ->assertOk()->assertDownload('student-import-template.csv');
        $this->assertStringContainsString('custom_allergies', $template->streamedContent());
    }

    private function tokenFromRedirect(?string $location): string
    {
        $this->assertNotNull($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertIsString($query['token'] ?? null);

        return $query['token'];
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
