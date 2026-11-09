<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademyBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_open_branding_without_overwriting_shared_academy_context(): void
    {
        [$user, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.branding.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/branding')
                ->where('academy.id', $academy->id)
                ->where('academy.theme.primary', '#5B5BD6')
                ->where('branding.name', $academy->name)
                ->where('branding.theme.primary', '#5B5BD6')
            );
    }

    public function test_owner_admin_can_update_own_academy_theme_and_change_is_audited(): void
    {
        [$user, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.theme.update'), [
                'primary_color' => '#123abc',
                'secondary_color' => '#fedcba',
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Academy theme updated.');

        $this->assertDatabaseHas(Academy::class, [
            'id' => $academy->id,
            'primary_color' => '#123ABC',
            'secondary_color' => '#FEDCBA',
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $user->id,
            'action' => 'academy.theme.updated',
        ]);
    }

    public function test_owner_admin_can_configure_tenant_display_terminology(): void
    {
        [$user, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.terminology.update'), [
                'member_label_singular' => 'Player',
                'member_label_plural' => 'Players',
                'staff_label_singular' => 'Instructor',
                'staff_label_plural' => 'Instructors',
                'guardian_label_singular' => 'Next of Kin',
                'guardian_label_plural' => 'Next of Kin',
                'collect_guardian_information' => false,
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Academy terminology updated.');

        $this->assertDatabaseHas(Academy::class, [
            'id' => $academy->id,
            'member_label_singular' => 'Player',
            'member_label_plural' => 'Players',
            'staff_label_singular' => 'Instructor',
            'staff_label_plural' => 'Instructors',
            'guardian_label_singular' => 'Next of Kin',
            'guardian_label_plural' => 'Next of Kin',
            'collect_guardian_information' => false,
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $user->id,
            'action' => 'academy.terminology.updated',
        ]);

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('academy.terminology.singular', 'Player')
                ->where('academy.terminology.plural', 'Players')
                ->where('academy.terminology.staff_singular', 'Instructor')
                ->where('academy.terminology.staff_plural', 'Instructors')
                ->where('academy.terminology.guardian_singular', 'Next of Kin')
                ->where('academy.terminology.guardian_plural', 'Next of Kin')
                ->where('academy.collect_guardian_information', false)
            );

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.team.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles.3.value', 'coach')
                ->where('roles.3.label', 'Instructor'));
    }

    public function test_terminology_rejects_markup_and_preserves_existing_labels(): void
    {
        [$user, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.terminology.update'), [
                'member_label_singular' => '<script>alert(1)</script>',
                'member_label_plural' => 'Students',
            ])
            ->assertSessionHasErrors('member_label_singular');

        $this->assertSame('Student', $academy->fresh()->member_label_singular);
    }

    public function test_owner_admin_can_configure_complete_tenant_bank_details(): void
    {
        [$user, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($user)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.bank-details.update'), [
                'bank_name' => 'Access Bank',
                'bank_account_name' => 'Example Sports Academy Limited',
                'bank_account_number' => '0123456789',
            ])->assertRedirect()->assertInertiaFlash('toast.message', 'Bank details updated.');

        $this->assertDatabaseHas(Academy::class, [
            'id' => $academy->id, 'bank_name' => 'Access Bank',
            'bank_account_name' => 'Example Sports Academy Limited',
            'bank_account_number' => '0123456789',
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id, 'actor_user_id' => $user->id,
            'action' => 'academy.bank_details.updated',
        ]);
        $this->actingAs($user)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.branding.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.bank_details.bank_name', 'Access Bank')
                ->where('branding.bank_details.account_number', '0123456789'));
    }

    public function test_partial_bank_details_are_rejected(): void
    {
        [$user, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($user)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.bank-details.update'), [
                'bank_name' => 'Access Bank', 'bank_account_name' => '', 'bank_account_number' => '',
            ])->assertSessionHasErrors(['bank_account_name', 'bank_account_number']);

        $this->assertNull($academy->fresh()->bank_name);
    }

    public function test_owner_admin_can_upload_a_private_logo_and_change_is_audited(): void
    {
        Storage::fake('local');
        [$user, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $response = $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.branding.update'), [
                'logo' => $this->validPng(),
            ]);

        $response->assertRedirect();
        $academy->refresh();
        $this->assertNotNull($academy->logo_path);
        Storage::disk('local')->assertExists($academy->logo_path);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $user->id,
            'action' => 'academy.logo.updated',
        ]);

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.logo'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, private');
    }

    public function test_non_admin_member_cannot_open_or_change_branding(): void
    {
        Storage::fake('local');
        [$user, $academy] = $this->member(AcademyRole::Coach);

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.branding.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.terminology.update'), [
                'member_label_singular' => 'Player',
                'member_label_plural' => 'Players',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.bank-details.update'), [
                'bank_name' => 'Access Bank', 'bank_account_name' => 'Academy',
                'bank_account_number' => '0123456789',
            ])->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.branding.update'), ['logo' => $this->validPng()])
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.theme.update'), [
                'primary_color' => '#123456',
                'secondary_color' => '#ABCDEF',
            ])
            ->assertForbidden();
    }

    public function test_logo_rejects_unsupported_file_types(): void
    {
        Storage::fake('local');
        [$user, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.branding.update'), [
                'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg/>'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertNull($academy->fresh()->logo_path);
        $this->assertDatabaseCount(AuditLog::class, 0);
    }

    public function test_logo_from_another_academy_is_never_served(): void
    {
        Storage::fake('local');
        [$user, $ownAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $foreignAcademy = Academy::factory()->create([
            'logo_path' => 'academies/foreign/branding/logo.png',
        ]);
        Storage::disk('local')->put($foreignAcademy->logo_path, 'foreign-logo');

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $foreignAcademy->id])
            ->get(route('academy.logo'))
            ->assertNotFound();

        $this->assertNull($ownAcademy->logo_path);
    }

    /** @return array{User, Academy} */
    private function member(AcademyRole $role): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }

    private function validPng(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent('logo.png', $png ?: '');
    }
}
