<?php

namespace App\Http\Controllers\Platform;

use App\Academies\Actions\UpdateAcademyTheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateAcademyThemeRequest;
use App\Models\Academy;
use App\Support\AccessibleColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademyBrandingController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $academies = Academy::query()->withCount('memberships');

        if ($search !== '') {
            $academies->where('name', 'like', '%'.$search.'%');
        }

        return Inertia::render('platform/academies/index', [
            'academies' => $academies
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Academy $academy): array => [
                    'id' => $academy->id,
                    'name' => $academy->name,
                    'academy_type' => $academy->academy_type,
                    'memberships_count' => $academy->memberships_count,
                    'theme' => AccessibleColor::theme($academy->primary_color, $academy->secondary_color),
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function edit(Academy $academy): Response
    {
        return Inertia::render('platform/academies/branding', [
            'managedAcademy' => [
                'id' => $academy->id,
                'name' => $academy->name,
                'theme' => AccessibleColor::theme($academy->primary_color, $academy->secondary_color),
            ],
        ]);
    }

    public function update(
        UpdateAcademyThemeRequest $request,
        Academy $academy,
        UpdateAcademyTheme $updateTheme,
    ): RedirectResponse {
        $updateTheme->handle(
            $academy,
            $request->user(),
            $request->string('primary_color')->toString(),
            $request->string('secondary_color')->toString(),
            $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy theme updated.']);

        return back();
    }
}
