<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveCohort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveCohortRequest;
use App\Models\Cohort;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CohortController extends Controller
{
    public function store(SaveCohortRequest $request, AcademyContext $context, SaveCohort $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cohort created.']);

        return back();
    }

    public function update(SaveCohortRequest $request, Cohort $cohort, AcademyContext $context, SaveCohort $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request, $cohort);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cohort updated.']);

        return back();
    }
}
