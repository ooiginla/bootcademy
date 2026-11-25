<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AcademyResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademyResourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_and_publicly_serve_an_approved_resource(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->academyMember(AcademyRole::OwnerAdmin);

        $this->asAcademy($owner, $academy)
            ->post(route('academy.resources.store'), [
                'resource' => UploadedFile::fake()->image('academy-header.png', 1200, 600)->size(800),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Resource uploaded.');

        $resource = AcademyResource::query()->sole();
        $this->assertSame($academy->id, $resource->academy_id);
        $this->assertSame($owner->id, $resource->uploaded_by_user_id);
        $this->assertSame('academy-header.png', $resource->original_name);
        $this->assertSame('image/png', $resource->mime_type);
        $this->assertTrue(Str::isUuid($resource->public_id));
        Storage::disk('local')->assertExists($resource->storage_path);
        $this->assertDatabaseHas('audit_logs', [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.resource.uploaded',
            'auditable_id' => $resource->id,
        ]);

        $this->get(route('public.resources.show', $resource))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_resource_directory_only_lists_the_active_academys_files(): void
    {
        [$owner, $academy] = $this->academyMember(AcademyRole::OwnerAdmin);
        $own = $this->resource($academy, $owner, 'training-guide.pdf');
        [$foreignOwner, $foreignAcademy] = $this->academyMember(AcademyRole::OwnerAdmin);
        $this->resource($foreignAcademy, $foreignOwner, 'foreign-secret.pdf');

        $this->asAcademy($owner, $academy)
            ->get(route('academy.resources.index', ['search' => 'training']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/resources')
                ->where('filters.search', 'training')
                ->has('resources.data', 1)
                ->where('resources.data.0.id', $own->public_id)
                ->where('resources.data.0.name', 'training-guide.pdf')
                ->where('resources.data.0.public_url', route('public.resources.show', $own))
            );
    }

    public function test_upload_rejects_unsafe_file_types(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->academyMember(AcademyRole::OwnerAdmin);

        $this->asAcademy($owner, $academy)
            ->post(route('academy.resources.store'), [
                'resource' => UploadedFile::fake()->create('page.html', 2, 'text/html'),
            ])
            ->assertSessionHasErrors('resource');

        $this->assertDatabaseCount('academy_resources', 0);
    }

    public function test_non_admin_members_cannot_list_or_upload_resources(): void
    {
        Storage::fake('local');
        [$coach, $academy] = $this->academyMember(AcademyRole::Coach);

        $this->asAcademy($coach, $academy)
            ->get(route('academy.resources.index'))
            ->assertForbidden();

        $this->asAcademy($coach, $academy)
            ->post(route('academy.resources.store'), [
                'resource' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('academy_resources', 0);
    }

    public function test_delete_is_tenant_scoped_audited_and_revokes_the_public_url(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->academyMember(AcademyRole::OwnerAdmin);
        $resource = $this->resource($academy, $owner, 'public-brochure.pdf');
        Storage::disk('local')->put($resource->storage_path, 'brochure');
        [$foreignOwner, $foreignAcademy] = $this->academyMember(AcademyRole::OwnerAdmin);

        $this->asAcademy($foreignOwner, $foreignAcademy)
            ->delete(route('academy.resources.destroy', $resource))
            ->assertNotFound();
        $this->assertNotSoftDeleted('academy_resources', ['id' => $resource->id]);

        $this->asAcademy($owner, $academy)
            ->delete(route('academy.resources.destroy', $resource))
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Resource deleted.');

        $this->assertSoftDeleted('academy_resources', ['id' => $resource->id]);
        Storage::disk('local')->assertMissing($resource->storage_path);
        $this->get(route('public.resources.show', $resource))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.resource.deleted',
            'auditable_id' => $resource->id,
        ]);
    }

    /** @return array{User, Academy} */
    private function academyMember(AcademyRole $role): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }

    private function resource(Academy $academy, User $uploader, string $name): AcademyResource
    {
        $publicId = (string) Str::uuid();

        return AcademyResource::query()->create([
            'public_id' => $publicId,
            'academy_id' => $academy->id,
            'uploaded_by_user_id' => $uploader->id,
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'storage_disk' => 'local',
            'storage_path' => "academies/{$academy->id}/resources/{$publicId}.pdf",
        ]);
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
