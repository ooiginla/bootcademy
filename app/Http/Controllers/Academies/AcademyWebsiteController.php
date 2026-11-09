<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\AcademyWebsiteDefaults;
use App\Academies\Actions\UpdateAcademyWebsite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\UpdateAcademyWebsiteRequest;
use App\Models\AcademyWebsite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AcademyWebsiteController extends Controller
{
    public function edit(AcademyContext $context): Response
    {
        $academy = $context->academy();
        Gate::authorize('manage', $academy);
        $website = AcademyWebsite::query()->firstOrCreate(
            ['academy_id' => $academy->id],
            ['subdomain' => $academy->slug, 'theme_key' => 'launch', 'is_published' => true, 'sections' => []],
        );

        return Inertia::render('academies/website', [
            'website' => [
                'subdomain' => $website->subdomain,
                'theme_key' => $website->theme_key,
                'is_published' => $website->is_published,
                'sections' => $website->resolvedSections(),
                'preview_url' => route('public.academy.home', ['subdomain' => $website->subdomain]),
                'public_url' => 'https://'.$website->subdomain.'.'.config('branding.tenant_domain'),
            ],
            'definitions' => AcademyWebsiteDefaults::definitions(),
            'tenant_domain' => config('branding.tenant_domain'),
            'store_module' => [
                'enabled' => (bool) data_get($website->resolvedSections(), 'store.enabled', false),
                'product_count' => $academy->products()->count(),
                'active_product_count' => $academy->products()->where('status', 'active')->count(),
                'order_count' => $academy->storeOrders()->count(),
                'manage_url' => route('academy.store.index'),
                'preview_url' => route('public.academy.store', ['subdomain' => $website->subdomain]),
            ],
        ]);
    }

    public function update(
        UpdateAcademyWebsiteRequest $request,
        AcademyContext $context,
        UpdateAcademyWebsite $updateWebsite,
    ): RedirectResponse {
        $academy = $context->academy();
        $website = AcademyWebsite::query()->firstOrCreate(
            ['academy_id' => $academy->id],
            ['subdomain' => $academy->slug, 'theme_key' => 'launch', 'is_published' => true, 'sections' => []],
        );
        $updateWebsite->handle($academy, $website, $request->user(), $request->validated(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Public website settings saved.']);

        return back();
    }
}
