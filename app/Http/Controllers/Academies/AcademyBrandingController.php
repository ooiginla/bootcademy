<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\UpdateAcademyBankDetails;
use App\Academies\Actions\UpdateAcademyProfile;
use App\Academies\Actions\UpdateAcademyTerminology;
use App\Academies\Actions\UpdateAcademyTheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\UpdateAcademyBankDetailsRequest;
use App\Http\Requests\Academies\UpdateAcademyLogoRequest;
use App\Http\Requests\Academies\UpdateAcademyProfileRequest;
use App\Http\Requests\Academies\UpdateAcademyTerminologyRequest;
use App\Http\Requests\Academies\UpdateAcademyThemeRequest;
use App\Platform\AuditLogger;
use App\Support\AccessibleColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AcademyBrandingController extends Controller
{
    public function edit(AcademyContext $context): Response
    {
        Gate::authorize('manage', $context->academy());

        return Inertia::render('academies/branding', [
            'branding' => [
                'name' => $context->academy()->name,
                'logo_url' => $context->academy()->logo_path ? route('academy.logo') : null,
                'theme' => AccessibleColor::theme(
                    $context->academy()->primary_color,
                    $context->academy()->secondary_color,
                ),
                'terminology' => [
                    'singular' => $context->academy()->member_label_singular,
                    'plural' => $context->academy()->member_label_plural,
                    'staff_singular' => $context->academy()->staff_label_singular,
                    'staff_plural' => $context->academy()->staff_label_plural,
                    'guardian_singular' => $context->academy()->guardian_label_singular,
                    'guardian_plural' => $context->academy()->guardian_label_plural,
                    'collect_guardian_information' => $context->academy()->collect_guardian_information,
                ],
                'bank_details' => [
                    'bank_name' => $context->academy()->bank_name,
                    'account_name' => $context->academy()->bank_account_name,
                    'account_number' => $context->academy()->bank_account_number,
                ],
            ],
        ]);
    }

    public function update(
        UpdateAcademyLogoRequest $request,
        AcademyContext $context,
        AuditLogger $audit,
    ): RedirectResponse {
        $academy = $context->academy();
        $file = $request->file('logo');
        $path = $file->storeAs(
            'academies/'.$academy->id.'/branding',
            Str::uuid().'.'.$file->extension(),
            'local',
        );

        abort_unless(is_string($path), 500, 'The logo could not be stored.');
        $oldPath = $academy->logo_path;

        try {
            DB::transaction(function () use ($academy, $path, $request, $audit): void {
                $academy->update(['logo_path' => $path]);
                $audit->record('academy.logo.updated', $request->user(), $academy, $academy, request: $request);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy logo updated.']);

        return back();
    }

    public function updateTheme(
        UpdateAcademyThemeRequest $request,
        AcademyContext $context,
        UpdateAcademyTheme $updateTheme,
    ): RedirectResponse {
        $updateTheme->handle(
            $context->academy(),
            $request->user(),
            $request->string('primary_color')->toString(),
            $request->string('secondary_color')->toString(),
            $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy theme updated.']);

        return back();
    }

    public function updateProfile(
        UpdateAcademyProfileRequest $request,
        AcademyContext $context,
        UpdateAcademyProfile $updateProfile,
    ): RedirectResponse {
        $updateProfile->handle(
            $context->academy(),
            $request->user(),
            $request->string('name')->toString(),
            $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy profile updated.']);

        return back();
    }

    public function updateTerminology(
        UpdateAcademyTerminologyRequest $request,
        AcademyContext $context,
        UpdateAcademyTerminology $updateTerminology,
    ): RedirectResponse {
        $updateTerminology->handle(
            $context->academy(),
            $request->user(),
            $request->string('member_label_singular')->toString(),
            $request->string('member_label_plural')->toString(),
            $request->string('staff_label_singular')->toString(),
            $request->string('staff_label_plural')->toString(),
            $request->string('guardian_label_singular')->toString(),
            $request->string('guardian_label_plural')->toString(),
            $request->boolean('collect_guardian_information'),
            $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy terminology updated.']);

        return back();
    }

    public function updateBankDetails(
        UpdateAcademyBankDetailsRequest $request,
        AcademyContext $context,
        UpdateAcademyBankDetails $updateBankDetails,
    ): RedirectResponse {
        $updateBankDetails->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bank details updated.']);

        return back();
    }
}
