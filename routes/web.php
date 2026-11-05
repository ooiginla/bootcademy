<?php

use App\Http\Controllers\Academies\AcademyApiKeyController;
use App\Http\Controllers\Academies\AcademyAuditLogController;
use App\Http\Controllers\Academies\AcademyBrandingController;
use App\Http\Controllers\Academies\AcademyCycleController;
use App\Http\Controllers\Academies\AcademyExpenseController;
use App\Http\Controllers\Academies\AcademyExportController;
use App\Http\Controllers\Academies\AcademyInvitationController;
use App\Http\Controllers\Academies\AcademyInvoiceController;
use App\Http\Controllers\Academies\AcademyLogoController;
use App\Http\Controllers\Academies\AcademyOnboardingController;
use App\Http\Controllers\Academies\AcademyPaymentController;
use App\Http\Controllers\Academies\AcademyTeamController;
use App\Http\Controllers\Academies\AcademyWebsiteController;
use App\Http\Controllers\Academies\AcceptAcademyInvitationController;
use App\Http\Controllers\Academies\ActivateAcademyController;
use App\Http\Controllers\Academies\ApplicationController;
use App\Http\Controllers\Academies\AttendanceController;
use App\Http\Controllers\Academies\BranchController;
use App\Http\Controllers\Academies\CoachController;
use App\Http\Controllers\Academies\CustomFieldController;
use App\Http\Controllers\Academies\DashboardController;
use App\Http\Controllers\Academies\GuardianSignatureController;
use App\Http\Controllers\Academies\PlayerController;
use App\Http\Controllers\Academies\PlayerImportController;
use App\Http\Controllers\Academies\ProgrammeController;
use App\Http\Controllers\Academies\SubscriptionController;
use App\Http\Controllers\Academies\TrainingSessionController;
use App\Http\Controllers\Platform\AcademyBrandingController as PlatformAcademyBrandingController;
use App\Http\Controllers\Platform\AcademySubscriptionController as PlatformAcademySubscriptionController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SupportAccessController;
use App\Http\Controllers\PublicAcademyWebsiteController;
use App\Http\Controllers\RequiredPasswordController;
use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use App\Http\Middleware\EnsureTemporaryPasswordChanged;
use App\Http\Middleware\ResolveActiveAcademy;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('sites/{subdomain}')->group(function (): void {
    Route::get('/', [PublicAcademyWebsiteController::class, 'home'])->name('public.academy.home');
    Route::get('logo', [PublicAcademyWebsiteController::class, 'logo'])->name('public.academy.logo');
    Route::get('programmes', [PublicAcademyWebsiteController::class, 'programmes'])->name('public.academy.programmes');
    Route::get('programmes/{programme}', [PublicAcademyWebsiteController::class, 'programme'])->whereNumber('programme')->name('public.academy.programme');
    Route::get('programmes/{programme}/image', [PublicAcademyWebsiteController::class, 'programmeImage'])->whereNumber('programme')->name('public.academy.programme-image');
    Route::get('apply', [PublicAcademyWebsiteController::class, 'application'])->name('public.academy.application');
});

if ($tenantDomain = config('branding.tenant_domain')) {
    Route::domain('{subdomain}.'.$tenantDomain)->name('public.tenant.')->group(function (): void {
        Route::get('/', [PublicAcademyWebsiteController::class, 'home'])->name('home');
        Route::get('logo', [PublicAcademyWebsiteController::class, 'logo'])->name('logo');
        Route::get('programmes', [PublicAcademyWebsiteController::class, 'programmes'])->name('programmes');
        Route::get('programmes/{programme}', [PublicAcademyWebsiteController::class, 'programme'])->whereNumber('programme')->name('programme');
        Route::get('programmes/{programme}/image', [PublicAcademyWebsiteController::class, 'programmeImage'])->whereNumber('programme')->name('programme-image');
        Route::get('apply', [PublicAcademyWebsiteController::class, 'application'])->name('application');
    });
}

Route::middleware('throttle:20,1')->group(function (): void {
    Route::get('invites/{token}', [AcceptAcademyInvitationController::class, 'show'])
        ->name('academy.invites.show');
    Route::post('invites/{token}', [AcceptAcademyInvitationController::class, 'accept'])
        ->name('academy.invites.accept');
});

Route::middleware('auth')->group(function (): void {
    Route::get('account/required-password', [RequiredPasswordController::class, 'edit'])
        ->name('password.required.edit');
    Route::put('account/required-password', [RequiredPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.required.update');
});

Route::middleware([
    'auth',
    EnsureEmailIsVerifiedWhenRequired::class,
    EnsureTemporaryPasswordChanged::class,
])->group(function (): void {
    Route::get('app/onboarding', [AcademyOnboardingController::class, 'create'])
        ->name('academy.onboarding.create');
    Route::post('app/onboarding', [AcademyOnboardingController::class, 'store'])
        ->name('academy.onboarding.store');
    Route::post('app/academies/{academy}/activate', ActivateAcademyController::class)
        ->name('academy.activate');
    Route::get('app/invitations', [AcademyInvitationController::class, 'index'])
        ->name('academy.invitations.index');
    Route::post('app/invitations/{membership}/accept', [AcademyInvitationController::class, 'accept'])
        ->name('academy.invitations.accept');

    Route::middleware(ResolveActiveAcademy::class)->group(function (): void {
        Route::get('app/dashboard', DashboardController::class)->name('dashboard');
        Route::get('app/reports/payments.csv', [AcademyExportController::class, 'payments'])->name('academy.exports.payments');
        Route::get('app/reports/invoices.csv', [AcademyExportController::class, 'invoices'])->name('academy.exports.invoices');
        Route::get('app/reports/expenses.csv', [AcademyExportController::class, 'expenses'])->name('academy.exports.expenses');
        Route::get('app/reports/attendance.csv', [AcademyExportController::class, 'attendance'])->name('academy.exports.attendance');
        Route::get('app/branches', [BranchController::class, 'index'])->name('academy.branches.index');
        Route::post('app/branches', [BranchController::class, 'store'])->name('academy.branches.store');
        Route::patch('app/branches/{branch}', [BranchController::class, 'update'])->name('academy.branches.update');
        Route::get('app/programmes', [ProgrammeController::class, 'index'])->name('academy.programmes.index');
        Route::post('app/programmes', [ProgrammeController::class, 'store'])->name('academy.programmes.store');
        Route::get('app/programmes/{programme}', [ProgrammeController::class, 'show'])->name('academy.programmes.show');
        Route::patch('app/programmes/{programme}', [ProgrammeController::class, 'update'])->name('academy.programmes.update');
        Route::get('app/programmes/{programme}/image', [ProgrammeController::class, 'image'])->name('academy.programmes.image');
        Route::post('app/programmes/{programme}/image', [ProgrammeController::class, 'updateImage'])->name('academy.programmes.image.update');
        Route::delete('app/programmes/{programme}/image', [ProgrammeController::class, 'destroyImage'])->name('academy.programmes.image.destroy');
        Route::get('app/sessions', [TrainingSessionController::class, 'index'])->name('academy.sessions.index');
        Route::post('app/sessions', [TrainingSessionController::class, 'store'])->name('academy.sessions.store');
        Route::patch('app/sessions/{trainingSession}', [TrainingSessionController::class, 'update'])->name('academy.sessions.update');
        Route::get('app/sessions/{trainingSession}/attendance', [AttendanceController::class, 'show'])->name('academy.sessions.attendance.show');
        Route::put('app/sessions/{trainingSession}/attendance', [AttendanceController::class, 'update'])->name('academy.sessions.attendance.update');
        Route::get('app/finance/cycles', [AcademyCycleController::class, 'index'])->name('academy.cycles.index');
        Route::post('app/finance/cycles', [AcademyCycleController::class, 'store'])->name('academy.cycles.store');
        Route::get('app/finance/cycles/{cycle}', [AcademyCycleController::class, 'show'])->name('academy.cycles.show');
        Route::patch('app/finance/cycles/{cycle}', [AcademyCycleController::class, 'update'])->name('academy.cycles.update');
        Route::post('app/finance/cycles/{cycle}/programmes', [AcademyCycleController::class, 'storeOffering'])->name('academy.cycles.offerings.store');
        Route::patch('app/finance/cycles/{cycle}/programmes/{programmeCycle}', [AcademyCycleController::class, 'updateOffering'])->name('academy.cycles.offerings.update');
        Route::get('app/players', [PlayerController::class, 'index'])->name('academy.players.index');
        Route::get('app/players/import', [PlayerImportController::class, 'create'])->name('academy.players.import.create');
        Route::post('app/players/import/preview', [PlayerImportController::class, 'preview'])->name('academy.players.import.preview');
        Route::post('app/players/import/confirm', [PlayerImportController::class, 'confirm'])->name('academy.players.import.confirm');
        Route::delete('app/players/import', [PlayerImportController::class, 'cancel'])->name('academy.players.import.cancel');
        Route::get('app/players/import/template', [PlayerImportController::class, 'template'])->name('academy.players.import.template');
        Route::get('app/players/import/errors', [PlayerImportController::class, 'errors'])->name('academy.players.import.errors');
        Route::get('app/players/{player}', [PlayerController::class, 'show'])->name('academy.players.show');
        Route::get('app/applications', [ApplicationController::class, 'index'])->name('academy.applications.index');
        Route::post('app/applications', [ApplicationController::class, 'store'])->name('academy.applications.store');
        Route::get('app/applications/{application}', [ApplicationController::class, 'show'])->name('academy.applications.show');
        Route::patch('app/applications/{application}', [ApplicationController::class, 'update'])->name('academy.applications.update');
        Route::patch('app/applications/{application}/review', [ApplicationController::class, 'review'])->name('academy.applications.review');
        Route::get('app/finance/payments', [AcademyPaymentController::class, 'index'])->name('academy.payments.index');
        Route::get('app/finance/payments/{payment}/receipt', [AcademyPaymentController::class, 'receipt'])->name('academy.payments.receipt');
        Route::post('app/finance/payments/{payment}/reversals', [AcademyPaymentController::class, 'reverse'])->name('academy.payments.reversals.store');
        Route::get('app/finance/invoices', [AcademyInvoiceController::class, 'index'])->name('academy.invoices.index');
        Route::get('app/finance/expenses', [AcademyExpenseController::class, 'index'])->name('academy.expenses.index');
        Route::post('app/finance/expenses', [AcademyExpenseController::class, 'store'])->name('academy.expenses.store');
        Route::post('app/finance/expense-categories', [AcademyExpenseController::class, 'storeCategory'])->name('academy.expense-categories.store');
        Route::patch('app/finance/expense-categories/{category}', [AcademyExpenseController::class, 'updateCategory'])->name('academy.expense-categories.update');
        Route::delete('app/finance/expense-categories/{category}', [AcademyExpenseController::class, 'destroyCategory'])->name('academy.expense-categories.destroy');
        Route::post('app/finance/expense-vendors', [AcademyExpenseController::class, 'storeVendor'])->name('academy.expense-vendors.store');
        Route::patch('app/finance/expense-vendors/{vendor}', [AcademyExpenseController::class, 'updateVendor'])->name('academy.expense-vendors.update');
        Route::delete('app/finance/expense-vendors/{vendor}', [AcademyExpenseController::class, 'destroyVendor'])->name('academy.expense-vendors.destroy');
        Route::get('app/finance/expense-attachments/{attachment}', [AcademyExpenseController::class, 'attachment'])->name('academy.expense-attachments.show');
        Route::post('app/finance/invoices/{invoice}/payments', [AcademyPaymentController::class, 'store'])->name('academy.invoices.payments.store');
        Route::patch('app/finance/invoices/{invoice}/installments', [AcademyPaymentController::class, 'updateInstallments'])->name('academy.invoices.installments.update');
        Route::patch('app/finance/invoices/{invoice}/cancel', [AcademyPaymentController::class, 'cancel'])->name('academy.invoices.cancel');
        Route::post('app/finance/invoices/{invoice}/write-offs', [AcademyPaymentController::class, 'writeOff'])->name('academy.invoices.write-offs.store');
        Route::post('app/players', [PlayerController::class, 'store'])->name('academy.players.store');
        Route::patch('app/players/{player}', [PlayerController::class, 'update'])->name('academy.players.update');
        Route::post('app/guardians', [PlayerController::class, 'storeGuardian'])->name('academy.guardians.store');
        Route::get('app/guardians/search', [PlayerController::class, 'searchGuardians'])->name('academy.guardians.search');
        Route::patch('app/guardians/{guardian}', [PlayerController::class, 'updateGuardian'])->name('academy.guardians.update');
        Route::get('app/coaches', [CoachController::class, 'index'])->name('academy.coaches.index');
        Route::patch('app/coaches/{membership}', [CoachController::class, 'update'])->name('academy.coaches.update');
        Route::get('app/guardians/{guardian}/signature', GuardianSignatureController::class)->name('academy.guardians.signature');
        Route::post('app/settings/player-fields', [CustomFieldController::class, 'store'])->name('academy.player-fields.store');
        Route::patch('app/settings/player-fields/{customField}', [CustomFieldController::class, 'update'])->name('academy.player-fields.update');
        Route::post('app/settings/coach-fields', [CustomFieldController::class, 'store'])->name('academy.coach-fields.store');
        Route::patch('app/settings/coach-fields/{customField}', [CustomFieldController::class, 'update'])->name('academy.coach-fields.update');
        Route::post('app/players/{player}/enrollments', [PlayerController::class, 'storeEnrollment'])->name('academy.players.enrollments.store');
        Route::patch('app/players/{player}/enrollments/{enrollment}', [PlayerController::class, 'updateEnrollment'])->name('academy.players.enrollments.update');
        Route::post('app/players/{player}/fees', [PlayerController::class, 'bookFee'])->name('academy.players.fees.store');
        Route::post('app/finance/cycles/{cycle}/fees', [AcademyCycleController::class, 'storeFee'])->name('academy.cycles.fees.store');
        Route::get('app/settings/academy', [AcademyBrandingController::class, 'edit'])
            ->name('academy.branding.edit');
        Route::post('app/settings/academy/logo', [AcademyBrandingController::class, 'update'])
            ->name('academy.branding.update');
        Route::patch('app/settings/academy/theme', [AcademyBrandingController::class, 'updateTheme'])
            ->name('academy.branding.theme.update');
        Route::patch('app/settings/academy/terminology', [AcademyBrandingController::class, 'updateTerminology'])
            ->name('academy.branding.terminology.update');
        Route::patch('app/settings/academy/bank-details', [AcademyBrandingController::class, 'updateBankDetails'])
            ->name('academy.branding.bank-details.update');
        Route::get('app/settings/team', [AcademyTeamController::class, 'index'])
            ->name('academy.team.index');
        Route::post('app/settings/team', [AcademyTeamController::class, 'store'])
            ->name('academy.team.store');
        Route::post('app/settings/team/create', [AcademyTeamController::class, 'createMember'])
            ->name('academy.team.create');
        Route::patch('app/settings/team/{membership}', [AcademyTeamController::class, 'update'])
            ->name('academy.team.update');
        Route::get('app/settings/api', [AcademyApiKeyController::class, 'index'])
            ->name('academy.api-keys.index');
        Route::post('app/settings/api', [AcademyApiKeyController::class, 'store'])
            ->name('academy.api-keys.store');
        Route::patch('app/settings/api/{apiKey}/revoke', [AcademyApiKeyController::class, 'revoke'])
            ->name('academy.api-keys.revoke');
        Route::get('app/settings/subscription', [SubscriptionController::class, 'show'])
            ->name('academy.subscription.show');
        Route::get('app/settings/website', [AcademyWebsiteController::class, 'edit'])
            ->name('academy.website.edit');
        Route::patch('app/settings/website', [AcademyWebsiteController::class, 'update'])
            ->name('academy.website.update');
        Route::get('app/settings/audit-log', AcademyAuditLogController::class)
            ->name('academy.audit-log.index');
        Route::get('app/academy/logo', AcademyLogoController::class)
            ->name('academy.logo');
    });

    Route::prefix('platform')
        ->name('platform.')
        ->middleware(EnsurePlatformSuperAdmin::class)
        ->group(function (): void {
            Route::get('academies', [PlatformAcademyBrandingController::class, 'index'])
                ->name('academies.index');
            Route::get('academies/{academy}/branding', [PlatformAcademyBrandingController::class, 'edit'])
                ->name('academies.branding.edit');
            Route::patch('academies/{academy}/branding', [PlatformAcademyBrandingController::class, 'update'])
                ->name('academies.branding.update');
            Route::get('academies/{academy}/subscription', [PlatformAcademySubscriptionController::class, 'edit'])
                ->name('academies.subscription.edit');
            Route::patch('academies/{academy}/subscription', [PlatformAcademySubscriptionController::class, 'update'])
                ->name('academies.subscription.update');
            Route::get('academies/{academy}/support-access', [SupportAccessController::class, 'show'])
                ->name('academies.support-access.show');
            Route::post('academies/{academy}/support-access', [SupportAccessController::class, 'start'])
                ->name('academies.support-access.start');
            Route::delete('support-access', [SupportAccessController::class, 'destroy'])
                ->name('support-access.destroy');
            Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
            Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
            Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        });
});

Route::redirect('dashboard', '/app/dashboard');

require __DIR__.'/settings.php';
