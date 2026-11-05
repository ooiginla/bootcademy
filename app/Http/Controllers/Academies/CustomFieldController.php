<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveCustomField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveCustomFieldRequest;
use App\Models\AcademyCustomField;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CustomFieldController extends Controller
{
    public function store(SaveCustomFieldRequest $request, AcademyContext $context, SaveCustomField $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request);
        $subject = $request->string('entity_type')->toString() === 'coach'
            ? mb_strtolower($context->academy()->staff_label_singular)
            : mb_strtolower($context->academy()->member_label_singular);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Custom $subject field created."]);

        return back();
    }

    public function update(SaveCustomFieldRequest $request, AcademyCustomField $customField, AcademyContext $context, SaveCustomField $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request, $customField);
        $subject = $request->string('entity_type')->toString() === 'coach'
            ? mb_strtolower($context->academy()->staff_label_singular)
            : mb_strtolower($context->academy()->member_label_singular);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Custom $subject field updated."]);

        return back();
    }
}
