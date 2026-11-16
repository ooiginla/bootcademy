<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketingPageTest extends TestCase
{
    public function test_every_marketing_page_is_public_and_has_its_own_canonical_url(): void
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        foreach (config('marketing.pages') as $page => $path) {
            $this->get($path)
                ->assertOk()
                ->assertInertia(fn (Assert $inertia) => $inertia
                    ->component('marketing/show')
                    ->where('page', $page)
                    ->where('seo.canonical_url', $baseUrl.$path)
                    ->where('seo.image_url', $baseUrl.'/images/bootcademy-academy-owner.jpg'));
        }
    }

    public function test_marketing_pages_do_not_require_authentication(): void
    {
        $this->get('/football-academy-management-software')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/show')
                ->where('page', 'football-academy-management-software'));
    }
}
