<?php

namespace Tests\Feature\Academies;

use App\Academies\AcademyWebsiteDefaults;
use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AcademyWebsite;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicWebsiteSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_uses_the_academy_name_logo_and_what_it_offers(): void
    {
        [, $academy, $website] = $this->site('lekki-stars', [
            'name' => 'Lekki Stars Academy', 'academy_type' => 'football', 'logo_path' => 'academies/logo.png',
        ]);
        Programme::factory()->for($academy)->create(['name' => 'Under 10s']);
        Branch::factory()->for($academy)->create(['name' => 'Lekki', 'city' => 'Lagos', 'is_active' => true]);
        $logo = url('/sites/lekki-stars/logo').'?v=';
        $html = $this->get(route('public.academy.home', ['subdomain' => $website->subdomain]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.seo.title', 'Lekki Stars Academy')
                ->where('site.seo.robots', 'index, follow, max-image-preview:large')
                ->where('site.seo.description', 'Lekki Stars Academy is a football academy in Lagos. Programmes include Under 10s. Apply online.')
                ->where('site.seo.keywords', fn (string $keywords) => str_contains($keywords, 'football academy in Lagos') && str_contains($keywords, 'Under 10s'))
                ->where('site.seo.icon_url', fn (string $url) => str_starts_with($url, $logo))
                ->where('site.seo.structured_data.name', 'Lekki Stars Academy')
                ->where('site.seo.structured_data.areaServed', ['Lagos']))
            ->getContent();

        // Share previews read the first HTML response, so the academy's tags must be server-rendered.
        $this->assertStringContainsString('<title data-inertia="seo-title">Lekki Stars Academy</title>', $html);
        $this->assertStringContainsString('property="og:title" content="Lekki Stars Academy"', $html);
        $this->assertStringContainsString('property="og:image" content="'.$logo, $html);
        $this->assertStringContainsString('rel="icon" href="'.$logo, $html);
        $this->assertStringNotContainsString('bootcademy-mark.png', $html);
        $this->assertStringNotContainsString('<title>Bootcademy</title>', $html);
    }

    public function test_academy_seo_overrides_from_the_website_editor_win(): void
    {
        [$owner, $academy, $website] = $this->site('override-academy');
        $sections = AcademyWebsiteDefaults::sections();
        $sections['header']['seo_title'] = 'Best Football Academy in Ikeja';
        $sections['header']['seo_description'] = 'Weekend football training for ages 2 to 17.';
        $sections['header']['seo_keywords'] = 'football academy Ikeja, kids football';

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.website.update'), ['subdomain' => 'override-academy', 'theme_key' => 'launch', 'is_published' => true, 'sections' => $sections])
            ->assertSessionHasNoErrors();

        $this->get(route('public.academy.home', ['subdomain' => $website->subdomain]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.seo.title', 'Best Football Academy in Ikeja')
                ->where('site.seo.description', 'Weekend football training for ages 2 to 17.')
                ->where('site.seo.keywords', 'football academy Ikeja, kids football'));

        $sections['header']['seo_description'] = str_repeat('a', 161);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.website.update'), ['subdomain' => 'override-academy', 'theme_key' => 'launch', 'is_published' => true, 'sections' => $sections])
            ->assertSessionHasErrors('sections.header.seo_description');
    }

    public function test_programme_pages_share_the_programme_and_private_pages_are_not_indexed(): void
    {
        [, $academy, $website] = $this->site('page-academy', ['name' => 'Page Academy']);
        $programme = Programme::factory()->for($academy)->create([
            'name' => 'Goalkeeping Clinic', 'description' => 'Specialist goalkeeper sessions.', 'is_active' => true,
        ]);
        $application = Application::query()->create([
            'academy_id' => $academy->id, 'reference' => 'APP-SEO-1', 'first_name' => 'Ada', 'last_name' => 'Okafor',
            'public_access_token_hash' => hash('sha256', 'private-token'),
        ]);

        $this->get(route('public.academy.programme', ['subdomain' => 'page-academy', 'programme' => $programme->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.seo.title', 'Goalkeeping Clinic · Page Academy')
                ->where('site.seo.description', 'Specialist goalkeeper sessions.'));
        $this->get(route('public.academy.application', ['subdomain' => 'page-academy']))
            ->assertInertia(fn (Assert $page) => $page->where('site.seo.title', 'Apply · Page Academy'));

        $html = $this->get(route('public.academy.application-complete', ['subdomain' => 'page-academy', 'reference' => $application->reference, 'token' => 'private-token']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.seo.title', 'Application submitted · Page Academy')
                ->where('site.seo.robots', 'noindex, nofollow'))
            ->getContent();
        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $html);
    }

    public function test_the_bootcademy_pages_keep_the_platform_title_and_favicon(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('bootcademy-mark.png', $html);
        $this->assertStringNotContainsString('data-inertia="seo-title"', $html);
    }

    /**
     * @param  array<string, mixed>  $academyAttributes
     * @return array{User, Academy, AcademyWebsite}
     */
    private function site(string $subdomain, array $academyAttributes = []): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create($academyAttributes);
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();
        $website = AcademyWebsite::query()->create([
            'academy_id' => $academy->id, 'subdomain' => $subdomain,
            'theme_key' => 'launch', 'is_published' => true, 'sections' => [],
        ]);

        return [$owner, $academy, $website];
    }
}
