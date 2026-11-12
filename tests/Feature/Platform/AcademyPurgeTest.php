<?php

namespace Tests\Feature\Platform;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyMembership;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AcademyPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_permanently_deletes_an_academy_and_everything_it_owns(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        [$spam, $spamOwner] = $this->populatedAcademy('Spam Bot Academy');
        [$real, $realOwner] = $this->populatedAcademy('Real Academy');

        // Someone in both academies keeps their account.
        $shared = User::factory()->create();
        AcademyMembership::factory()->for($shared)->for($spam)->asRole(AcademyRole::Coach)->create();
        AcademyMembership::factory()->for($shared)->for($real)->asRole(AcademyRole::Coach)->create();
        Storage::disk('local')->putFileAs("academies/{$spam->id}/branding", UploadedFile::fake()->image('logo.png'), 'logo.png');

        $this->actingAs($admin)
            ->delete(route('platform.academies.destroy', $spam), ['confirmation' => 'Spam Bot Academy'])
            ->assertRedirect(route('platform.academies.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('academies', ['id' => $spam->id]);
        foreach ($this->academyTables() as $table) {
            $this->assertSame(0, DB::table($table)->where('academy_id', $spam->id)->count(), "{$table} still has rows");
        }
        $this->assertDatabaseMissing('users', ['id' => $spamOwner->id]);
        $this->assertDatabaseHas('users', ['id' => $shared->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        Storage::disk('local')->assertMissing("academies/{$spam->id}/branding/logo.png");

        // The other academy is untouched.
        $this->assertDatabaseHas('academies', ['id' => $real->id]);
        $this->assertDatabaseHas('users', ['id' => $realOwner->id]);
        $this->assertSame(1, Player::query()->where('academy_id', $real->id)->count());
        $this->assertSame(1, Enrollment::query()->where('academy_id', $real->id)->count());

        $log = AuditLog::query()->where('action', 'platform.academy.purged')->sole();
        $this->assertNull($log->academy_id);
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame('Spam Bot Academy', $log->metadata['academy_name']);
    }

    public function test_the_academy_name_must_be_typed_exactly(): void
    {
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        [$academy] = $this->populatedAcademy('Lagos Stars');

        $this->actingAs($admin)
            ->delete(route('platform.academies.destroy', $academy), ['confirmation' => 'lagos stars'])
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($admin)
            ->delete(route('platform.academies.destroy', $academy), [])
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('academies', ['id' => $academy->id]);
        $this->assertSame(1, Player::query()->where('academy_id', $academy->id)->count());
    }

    public function test_only_platform_super_admins_can_delete_academies(): void
    {
        [$academy, $owner] = $this->populatedAcademy('Lagos Stars');

        $this->actingAs($owner)
            ->delete(route('platform.academies.destroy', $academy), ['confirmation' => 'Lagos Stars'])
            ->assertForbidden();

        $this->assertDatabaseHas('academies', ['id' => $academy->id]);
    }

    /** @return array{Academy, User} */
    private function populatedAcademy(string $name): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create(['name' => $name]);
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();
        $branch = Branch::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $programme->branches()->attach($branch->id, ['academy_id' => $academy->id]);
        $player = Player::factory()->for($academy)->create();
        Enrollment::factory()->create(['player_id' => $player->id, 'programme_id' => $programme->id, 'branch_id' => $branch->id]);
        $cycle = AcademyCycle::query()->create(['academy_id' => $academy->id, 'name' => 'Year', 'type' => 'fixed', 'status' => 'active']);
        $application = Application::query()->create([
            'academy_id' => $academy->id, 'reference' => 'APP-'.$academy->id, 'first_name' => 'Spam', 'last_name' => 'Bot',
            'programme_id' => $programme->id, 'branch_id' => $branch->id,
        ]);
        DB::table('academy_invoices')->insert([
            'academy_id' => $academy->id, 'number' => 'INV-'.$academy->id, 'academy_cycle_id' => $cycle->id,
            'application_id' => $application->id, 'purpose' => 'application', 'currency' => 'NGN',
            'total_minor' => 500000, 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'academy_id' => $academy->id, 'actor_user_id' => $owner->id, 'action' => 'academy.created', 'created_at' => now(),
        ]);

        return [$academy, $owner];
    }

    /** @return list<string> */
    private function academyTables(): array
    {
        return array_values(array_filter(
            array_map(fn (string $table): string => preg_replace('/^[^.]+\./', '', $table) ?? $table, Schema::getTableListing()),
            fn (string $table): bool => $table !== 'academies' && Schema::hasColumn($table, 'academy_id'),
        ));
    }
}
