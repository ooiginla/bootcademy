<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveApplicationIntake;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveApplicationIntakeRequest;
use App\Models\ApplicationIntake;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ApplicationIntakeController extends Controller
{
    public function store(SaveApplicationIntakeRequest $request, AcademyContext $context, SaveApplicationIntake $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application setting created.']);

        return back();
    }

    public function update(SaveApplicationIntakeRequest $request, ApplicationIntake $applicationIntake, AcademyContext $context, SaveApplicationIntake $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request, $applicationIntake);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application setting updated.']);

        return back();
    }
}
