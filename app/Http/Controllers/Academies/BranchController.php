<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveBranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        return Inertia::render('academies/branches', [
            'branches' => $context->academy()->branches()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'address_line' => $branch->address_line,
                    'city' => $branch->city,
                    'state_region' => $branch->state_region,
                    'country_code' => $branch->country_code,
                    'timezone' => $branch->timezone,
                    'contact_email' => $branch->contact_email,
                    'contact_phone' => $branch->contact_phone,
                    'is_active' => $branch->is_active,
                ]),
            'academy_timezone' => $context->academy()->timezone,
            'can_manage' => $context->canManageAcademy(),
        ]);
    }

    public function store(
        SaveBranchRequest $request,
        AcademyContext $context,
        SaveBranch $saveBranch,
    ): RedirectResponse {
        $saveBranch->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Branch created.']);

        return back();
    }

    public function update(
        SaveBranchRequest $request,
        Branch $branch,
        AcademyContext $context,
        SaveBranch $saveBranch,
    ): RedirectResponse {
        $saveBranch->handle($context->academy(), $request->user(), $request->validated(), $request, $branch);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Branch updated.']);

        return back();
    }
}
