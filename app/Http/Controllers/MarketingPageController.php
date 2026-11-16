<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingPageController extends Controller
{
    public function __invoke(Request $request, string $page): Response
    {
        $path = config("marketing.pages.{$page}");

        abort_unless(is_string($path), 404);

        $baseUrl = rtrim((string) config('app.url'), '/');

        return Inertia::render('marketing/show', [
            'page' => $page,
            'seo' => [
                'canonical_url' => $baseUrl.$path,
                'image_url' => $baseUrl.'/images/bootcademy-academy-owner.jpg',
            ],
        ]);
    }
}
