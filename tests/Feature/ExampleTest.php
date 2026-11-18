<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_active_pricing_and_search_metadata(): void
    {
        // The three launch plans are seeded by migration; an inactive plan is hidden.
        Plan::factory()->create([
            'code' => 'retired',
            'name' => 'Retired',
            'is_active' => false,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->has('plans', 3)
                ->where('plans.0.code', 'essential')
                ->where('plans.0.prices.monthly', 2_500_000)
                ->where('plans.0.prices.quarterly', 7_125_000)
                ->where('plans.0.prices.annual', 27_000_000)
                ->where('plans.0.active_player_limit', 75)
                ->where('plans.0.branch_limit', 1)
                ->where('plans.1.is_recommended', true)
                ->where('plans.2.flags.api_access', true)
                ->has('comparison.included')
                ->where('trial.days', 30)
                ->where('trial.plan_name', 'Enterprise')
                ->where('seo.canonical_url', rtrim((string) config('app.url'), '/').'/')
                ->where('seo.image_url', rtrim((string) config('app.url'), '/').'/images/bootcademy-academy-owner.jpg'));
    }
}
