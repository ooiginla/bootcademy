<?php

namespace Tests\Feature\Academies;

use App\Academies\AcademyWebsiteDefaults;
use App\Academies\Actions\CreateAcademy;
use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\AcademyCycle;
use App\Models\AcademyMembership;
use App\Models\AcademyWebsite;
use App\Models\ApplicationIntake;
use App\Models\Branch;
use App\Models\Cohort;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_academies_receive_a_clean_unique_bootcademy_subdomain(): void
    {
        $create = app(CreateAcademy::class);
        $first = $create->handle(User::factory()->create(), [
            'name' => 'Bright Future Academy', 'academy_type' => 'general', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);
        $second = $create->handle(User::factory()->create(), [
            'name' => 'Bright Future Academy', 'academy_type' => 'general', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);

        $this->assertSame('bright-future-academy', $first->slug);
        $this->assertSame('bright-future-academy-2', $second->slug);
        $this->assertDatabaseHas('academy_websites', [
            'academy_id' => $first->id,
            'subdomain' => 'bright-future-academy',
            'theme_key' => 'launch',
            'is_published' => true,
        ]);
    }

    public function test_owner_can_configure_sections_and_other_roles_cannot_manage_the_website(): void
    {
        [$owner, $academy, $website] = $this->academyWebsite('studio-one');
        $sections = AcademyWebsiteDefaults::sections();
        $sections['hero']['headline'] = 'Build your future here';
        $sections['testimonials']['enabled'] = true;
        $sections['testimonials']['items'] = 'A genuine review | Parent Name | Parent';

        $this->asAcademy($owner, $academy)->patch(route('academy.website.update'), [
            'subdomain' => 'future-studio',
            'theme_key' => 'launch',
            'is_published' => true,
            'sections' => $sections,
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Public website settings saved.');

        $website->refresh();
        $this->assertSame('future-studio', $website->subdomain);
        $this->assertSame('Build your future here', $website->sections['hero']['headline']);
        $this->assertTrue($website->sections['testimonials']['enabled']);
        $this->assertDatabaseHas('audit_logs', [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.website.updated',
        ]);

        $coach = User::factory()->create();
        AcademyMembership::factory()->for($coach)->for($academy)->asRole(AcademyRole::Coach)->create();
        $this->asAcademy($coach, $academy)->get(route('academy.website.edit'))->assertForbidden();
        $this->asAcademy($coach, $academy)->patch(route('academy.website.update'), [])->assertForbidden();
    }

    public function test_legacy_string_section_flags_are_resolved_as_real_booleans(): void
    {
        [, , $website] = $this->academyWebsite('boolean-academy');
        $website->update(['sections' => [
            'events' => ['enabled' => '0'],
            'news' => ['enabled' => '1'],
        ]]);

        $sections = $website->fresh()->resolvedSections();
        $this->assertFalse($sections['events']['enabled']);
        $this->assertTrue($sections['news']['enabled']);
    }

    public function test_public_site_is_tenant_scoped_and_unpublished_sites_return_not_found(): void
    {
        [$owner, $academy, $website] = $this->academyWebsite('alpha-academy');
        $programme = Programme::factory()->for($academy)->create(['name' => 'Alpha Robotics']);
        Branch::factory()->for($academy)->create(['name' => 'Alpha Centre']);
        [, $foreignAcademy] = $this->academyWebsite('foreign-academy');
        Programme::factory()->for($foreignAcademy)->create(['name' => 'Private Foreign Programme']);

        $this->get(route('public.academy.home', ['subdomain' => $website->subdomain]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/home')
            ->where('academy.name', $academy->name)
            ->where('site.subdomain', 'alpha-academy')
            ->has('programmes', 1)
            ->where('programmes.0.id', $programme->id)
            ->where('locations.0.name', 'Alpha Centre')
            );

        $this->get('http://alpha-academy.bootcademy.com/')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/home')
            ->where('base_url', '')
            ->where('academy.name', $academy->name)
            );

        $website->update(['is_published' => false]);
        $this->get(route('public.academy.home', ['subdomain' => 'alpha-academy']))->assertNotFound();
    }

    public function test_public_programme_page_receives_profile_content_and_active_fees(): void
    {
        Storage::fake('local');
        [, $academy, $website] = $this->academyWebsite('profile-academy');
        $programme = Programme::factory()->for($academy)->create([
            'name' => 'Product Design Bootcamp',
            'duration' => '4 Weeks',
            'delivery_format' => 'Hybrid',
            'programme_type' => 'Bootcamp',
            'public_timezone' => 'Africa/Lagos',
            'level' => 'Beginner',
            'outcomes_html' => '<p>Build a portfolio.</p>',
            'tools_platforms' => [['name' => 'Figma', 'logo_url' => null]],
            'faqs' => [['question' => 'Is a laptop required?', 'answer_html' => '<p>Yes.</p>']],
            'testimonials' => [['quote_html' => '<p>Worth it.</p>', 'name' => 'Ada', 'role' => 'Graduate']],
            'hero_image_path' => 'academies/'.$academy->id.'/programmes/hero.webp',
        ]);
        Storage::disk('local')->put($programme->hero_image_path, 'programme image');
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => 'January Cohort', 'type' => 'fixed',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-02-01', 'status' => 'active',
        ]);
        ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'fee_name' => 'Bootcamp tuition',
            'branch_id' => null, 'branch_scope_id' => 0, 'currency' => 'NGN',
            'application_fee_minor' => 500000, 'payment_mode' => 'one_off',
            'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);

        $this->get(route('public.academy.programme', ['subdomain' => $website->subdomain, 'programme' => $programme->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/programme')
            ->where('programme.name', 'Product Design Bootcamp')
            ->where('programme.duration', '4 Weeks')
            ->where('programme.public_timezone', 'Africa/Lagos')
            ->where('programme.outcomes_html', '<p>Build a portfolio.</p>')
            ->where('programme.tools_platforms.0.name', 'Figma')
            ->where('programme.faqs.0.question', 'Is a laptop required?')
            ->where('programme.image_url', fn (string $url): bool => str_contains($url, '/programmes/'.$programme->id.'/image?v='))
            ->has('programme.offerings', 1)
            ->where('programme.offerings.0.fee_name', 'Bootcamp tuition')
            );

        $this->get(route('public.academy.programme-image', ['subdomain' => $website->subdomain, 'programme' => $programme->id]))
            ->assertOk()->assertHeader('Cache-Control', 'max-age=3600, public');

        $foreignProgramme = Programme::factory()->create(['hero_image_path' => 'academies/foreign/programmes/hero.webp']);
        $this->get(route('public.academy.programme-image', ['subdomain' => $website->subdomain, 'programme' => $foreignProgramme->id]))
            ->assertNotFound();
    }

    public function test_application_preview_receives_active_offerings_fees_installments_and_custom_fields(): void
    {
        [, $academy, $website] = $this->academyWebsite('application-academy');
        $programme = Programme::factory()->for($academy)->create(['name' => 'Creative Coding']);
        $branch = Branch::factory()->for($academy)->create(['name' => 'Main Campus']);
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2027 Cohort', 'type' => 'fixed',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'status' => 'active',
        ]);
        $offering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'fee_name' => 'Cohort tuition',
            'branch_id' => $branch->id, 'branch_scope_id' => $branch->id,
            'currency' => 'NGN', 'application_fee_minor' => 500000,
            'payment_mode' => 'installments', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);
        $offering->feeItems()->create([
            'academy_id' => $academy->id, 'name' => 'Tuition', 'amount_minor' => 12000000, 'sort_order' => 1,
        ]);
        $offering->installments()->createMany([
            ['academy_id' => $academy->id, 'name' => 'First payment', 'amount_minor' => 6000000, 'sequence' => 1],
            ['academy_id' => $academy->id, 'name' => 'Final payment', 'amount_minor' => 6000000, 'sequence' => 2],
        ]);
        $plan = $offering->paymentPlans()->create([
            'academy_id' => $academy->id,
            'term_scope_id' => 0,
            'cohort_scope_id' => 0,
            'name' => 'Two payments',
            'description' => 'Two equal payments.',
            'sort_order' => 20,
            'currency' => 'NGN',
            'total_minor' => 12000000,
            'is_default' => true,
            'is_active' => true,
        ]);
        $plan->items()->create([
            'academy_id' => $academy->id, 'name' => 'Tuition', 'description' => '₦10,000/month',
            'amount_minor' => 12000000, 'sort_order' => 0,
        ]);
        $plan->installments()->createMany([
            ['academy_id' => $academy->id, 'name' => 'First payment', 'amount_minor' => 6000000, 'sequence' => 1],
            ['academy_id' => $academy->id, 'name' => 'Final payment', 'amount_minor' => 6000000, 'sequence' => 2],
        ]);
        $cohort = Cohort::query()->create([
            'academy_id' => $academy->id, 'programme_cycle_id' => $offering->id,
            'branch_id' => $branch->id, 'name' => '2027 cohort',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'status' => 'open',
        ]);
        ApplicationIntake::query()->create([
            'academy_id' => $academy->id,
            'programme_cycle_id' => $offering->id,
            'cohort_id' => $cohort->id,
            'cohort_scope_id' => $cohort->id,
            'branch_id' => $branch->id,
            'branch_scope_id' => $branch->id,
            'is_enabled' => true,
        ]);
        AcademyCustomField::query()->create([
            'academy_id' => $academy->id, 'entity_type' => 'player', 'key' => 'laptop_access',
            'label' => 'Laptop access', 'type' => 'boolean', 'is_required' => true,
            'is_sensitive' => false, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->get(route('public.academy.application', ['subdomain' => $website->subdomain]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/application')
            ->has('intakes', 1)
            ->where('intakes.0.programme_name', 'Creative Coding')
            ->where('intakes.0.cycle_name', '2027 Cohort')
            ->where('intakes.0.application_fee', '5000.00')
            ->where('intakes.0.total', '120000.00')
            ->has('intakes.0.installments', 2)
            ->has('intakes.0.payment_plans', 1)
            ->where('intakes.0.payment_plans.0.name', 'Two payments')
            ->where('intakes.0.payment_plans.0.sort_order', 20)
            ->where('intakes.0.payment_plans.0.total', '120000.00')
            ->where('intakes.0.payment_plans.0.items.0.description', '₦10,000/month')
            ->has('intakes.0.payment_plans.0.items', 1)
            ->has('intakes.0.payment_plans.0.installments', 2)
            ->where('academy.guardian_singular', 'Guardian')
            ->where('academy.collect_guardian_information', true)
            ->where('locations.0.id', $branch->id)
            ->where('custom_fields.0.key', 'laptop_access')
            ->where('custom_fields.0.required', true)
            );
    }

    /** @return array{User, Academy, AcademyWebsite} */
    private function academyWebsite(string $subdomain): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();
        $website = AcademyWebsite::query()->create([
            'academy_id' => $academy->id, 'subdomain' => $subdomain,
            'theme_key' => 'launch', 'is_published' => true, 'sections' => [],
        ]);

        return [$owner, $academy, $website];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
