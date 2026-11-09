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
        Plan::factory()->create([
            'code' => 'essential',
            'name' => 'Essential',
            'monthly_price_minor' => 2_500_000,
            'annual_price_minor' => 27_000_000,
            'active_player_limit' => 100,
            'sort_order' => 10,
        ]);
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
                ->has('plans', 1)
                ->where('plans.0.code', 'essential')
                ->where('plans.0.monthly_price', '25000.00')
                ->where('plans.0.annual_price', '270000.00')
                ->where('plans.0.active_player_limit', 100)
                ->where('seo.canonical_url', rtrim((string) config('app.url'), '/').'/')
                ->where('seo.image_url', rtrim((string) config('app.url'), '/').'/images/bootcademy-academy-owner.jpg'));
    }
}
