<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Academy;
use App\Platform\Actions\PurgeAcademy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AcademyPurgeController extends Controller
{
    public function __invoke(Request $request, Academy $academy, PurgeAcademy $purge): RedirectResponse
    {
        $data = $request->validate(['confirmation' => ['required', 'string', 'max:255']]);
        $name = $academy->name;

        $purge->handle($academy, $request->user(), $data['confirmation'], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} and all of its data were permanently deleted."]);

        return to_route('platform.academies.index');
    }
}
