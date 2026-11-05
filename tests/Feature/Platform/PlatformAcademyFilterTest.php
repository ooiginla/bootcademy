<?php

namespace Tests\Feature\Platform;

use App\Models\Academy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAcademyFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_filter_academies_by_name_with_pagination_preserved(): void
    {
        $superAdmin = User::factory()->create(['is_platform_super_admin' => true]);
        foreach (range(1, 31) as $number) {
            Academy::factory()->create(['name' => sprintf('Alpha Academy %02d', $number)]);
        }
        Academy::factory()->create(['name' => 'Harmony School']);

        $this->actingAs($superAdmin)
            ->get(route('platform.academies.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/academies/index')
                ->where('filters.search', 'Alpha')
                ->has('academies.data', 30)
                ->where('academies.data.0.name', 'Alpha Academy 01')
                ->where('academies.next_page_url', fn (?string $url): bool => $url !== null && str_contains($url, 'search=Alpha'))
            );
    }
}
