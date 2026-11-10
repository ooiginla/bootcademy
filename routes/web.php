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
use App\Http\Controllers\Academies\ApplicationIntakeController;
use App\Http\Controllers\Academies\AttendanceController;
use App\Http\Controllers\Academies\BranchController;
use App\Http\Controllers\Academies\CoachController;
use App\Http\Controllers\Academies\CohortController;
use App\Http\Controllers\Academies\CommunicationController;
use App\Http\Controllers\Academies\CustomFieldController;
use App\Http\Controllers\Academies\DashboardController;
use App\Http\Controllers\Academies\GuardianSignatureController;
use App\Http\Controllers\Academies\PlayerController;
use App\Http\Controllers\Academies\PlayerImportController;
use App\Http\Controllers\Academies\ProgrammeController;
use App\Http\Controllers\Academies\ProgrammeCyclePaymentPlanController;
use App\Http\Controllers\Academies\ReferralController;
use App\Http\Controllers\Academies\ScholarshipController;
use App\Http\Controllers\Academies\StoreController;
use App\Http\Controllers\Academies\SubscriptionController;
use App\Http\Controllers\Academies\TrainingSessionController;
use App\Http\Controllers\Platform\AcademyBrandingController as PlatformAcademyBrandingController;
use App\Http\Controllers\Platform\AcademySubscriptionController as PlatformAcademySubscriptionController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SupportAccessController;
use App\Http\Controllers\PublicAcademyWebsiteController;
use App\Http\Controllers\PublicApplicationController;
use App\Http\Controllers\PublicStoreController;
use App\Http\Controllers\RequiredPasswordController;
use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use App\Http\Middleware\EnsureTemporaryPasswordChanged;
use App\Http\Middleware\ResolveActiveAcademy;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (Plan $plan): array => [
            'code' => $plan->code,
            'name' => $plan->name,
            'currency' => $plan->currency,
            'monthly_price' => number_format($plan->monthly_price_minor / 100, 2, '.', ''),
            'annual_price' => number_format($plan->annual_price_minor / 100, 2, '.', ''),
            'active_player_limit' => $plan->active_player_limit,
        ])->values(),
        'seo' => [
            'canonical_url' => rtrim((string) config('app.url'), '/').'/',
            'image_url' => rtrim((string) config('app.url'), '/').'/images/bootcademy-academy-owner.jpg',
        ],
    ]);
})->name('home');

Route::prefix('sites/{subdomain}')->group(function (): void {
    Route::get('/', [PublicAcademyWebsiteController::class, 'home'])->name('public.academy.home');
    Route::get('logo', [PublicAcademyWebsiteController::class, 'logo'])->name('public.academy.logo');
    Route::get('programmes', [PublicAcademyWebsiteController::class, 'programmes'])->name('public.academy.programmes');
    Route::get('programmes/{programme}', [PublicAcademyWebsiteController::class, 'programme'])->whereNumber('programme')->name('public.academy.programme');
    Route::get('programmes/{programme}/image', [PublicAcademyWebsiteController::class, 'programmeImage'])->whereNumber('programme')->name('public.academy.programme-image');
    Route::get('apply', [PublicAcademyWebsiteController::class, 'application'])->name('public.academy.application');
    Route::post('apply', [PublicApplicationController::class, 'store'])->middleware('throttle:10,1')->name('public.academy.application.store');
    Route::get('applications/{reference}/fees', [PublicApplicationController::class, 'fees'])->name('public.academy.application-fees');
    Route::get('applications/{reference}/complete', [PublicApplicationController::class, 'complete'])->name('public.academy.application-complete');
    Route::post('applications/{reference}/payments', [PublicApplicationController::class, 'initialize'])->middleware('throttle:10,1')->name('public.academy.payment-initialize');
    Route::post('applications/{reference}/payments/{intent}/verify', [PublicApplicationController::class, 'verify'])->middleware('throttle:30,1')->name('public.academy.payment-verify');
    Route::get('store', [PublicStoreController::class, 'index'])->name('public.academy.store');
    Route::post('store/orders', [PublicStoreController::class, 'store'])->middleware('throttle:10,1')->name('public.academy.store-order');
    Route::get('store/orders/{token}', [PublicStoreController::class, 'show'])->name('public.academy.store-order.show');
    Route::post('store/orders/{token}/payments', [PublicStoreController::class, 'initializePayment'])->middleware('throttle:10,1')->name('public.academy.store-payment-initialize');
    Route::post('store/orders/{token}/payments/{payment}/verify', [PublicStoreController::class, 'verifyPayment'])->middleware('throttle:30,1')->name('public.academy.store-payment-verify');
    Route::get('store/products/{product}/image', [PublicStoreController::class, 'image'])->whereNumber('product')->name('public.academy.store-image');
});

if ($tenantDomain = config('branding.tenant_domain')) {
    Route::domain('{subdomain}.'.$tenantDomain)->name('public.tenant.')->group(function (): void {
        Route::get('/', [PublicAcademyWebsiteController::class, 'home'])->name('home');
        Route::get('logo', [PublicAcademyWebsiteController::class, 'logo'])->name('logo');
        Route::get('programmes', [PublicAcademyWebsiteController::class, 'programmes'])->name('programmes');
        Route::get('programmes/{programme}', [PublicAcademyWebsiteController::class, 'programme'])->whereNumber('programme')->name('programme');
        Route::get('programmes/{programme}/image', [PublicAcademyWebsiteController::class, 'programmeImage'])->whereNumber('programme')->name('programme-image');
        Route::get('apply', [PublicAcademyWebsiteController::class, 'application'])->name('application');
        Route::post('apply', [PublicApplicationController::class, 'store'])->middleware('throttle:10,1')->name('application.store');
        Route::get('applications/{reference}/fees', [PublicApplicationController::class, 'fees'])->name('application-fees');
        Route::get('applications/{reference}/complete', [PublicApplicationController::class, 'complete'])->name('application-complete');
        Route::post('applications/{reference}/payments', [PublicApplicationController::class, 'initialize'])->middleware('throttle:10,1')->name('payment-initialize');
        Route::post('applications/{reference}/payments/{intent}/verify', [PublicApplicationController::class, 'verify'])->middleware('throttle:30,1')->name('payment-verify');
        Route::get('store', [PublicStoreController::class, 'index'])->name('store');
        Route::post('store/orders', [PublicStoreController::class, 'store'])->middleware('throttle:10,1')->name('store-order');
        Route::get('store/orders/{token}', [PublicStoreController::class, 'show'])->name('store-order.show');
        Route::post('store/orders/{token}/payments', [PublicStoreController::class, 'initializePayment'])->middleware('throttle:10,1')->name('store-payment-initialize');
        Route::post('store/orders/{token}/payments/{payment}/verify', [PublicStoreController::class, 'verifyPayment'])->middleware('throttle:30,1')->name('store-payment-verify');
        Route::get('store/products/{product}/image', [PublicStoreController::class, 'image'])->whereNumber('product')->name('store-image');
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
        Route::patch('app/programmes/{programme}/enrollments/complete', [ProgrammeController::class, 'completeEnrollments'])->name('academy.programmes.enrollments.complete');
        Route::post('app/programmes/{programme}/enrollments/reenroll', [ProgrammeController::class, 'reenrollMembers'])->name('academy.programmes.enrollments.reenroll');
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
        Route::post('app/finance/cycles/{cycle}/terms', [AcademyCycleController::class, 'storeTerm'])->name('academy.cycles.terms.store');
        Route::patch('app/finance/cycles/{cycle}/terms/{term}', [AcademyCycleController::class, 'updateTerm'])->name('academy.cycles.terms.update');
        Route::post('app/finance/cycles/{cycle}/programmes', [AcademyCycleController::class, 'storeOffering'])->name('academy.cycles.offerings.store');
        Route::get('app/finance/cycles/{cycle}/programmes/{programmeCycle}', [ProgrammeCyclePaymentPlanController::class, 'show'])->name('academy.cycles.offerings.show');
        Route::patch('app/finance/cycles/{cycle}/programmes/{programmeCycle}', [AcademyCycleController::class, 'updateOffering'])->name('academy.cycles.offerings.update');
        Route::post('app/finance/cycles/{cycle}/programmes/{programmeCycle}/payment-plans', [ProgrammeCyclePaymentPlanController::class, 'store'])->name('academy.cycles.offerings.payment-plans.store');
        Route::patch('app/finance/cycles/{cycle}/programmes/{programmeCycle}/payment-plans/{paymentPlan}', [ProgrammeCyclePaymentPlanController::class, 'update'])->name('academy.cycles.offerings.payment-plans.update');
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
        Route::post('app/application-intakes', [ApplicationIntakeController::class, 'store'])->name('academy.application-intakes.store');
        Route::patch('app/application-intakes/{applicationIntake}', [ApplicationIntakeController::class, 'update'])->name('academy.application-intakes.update');
        Route::post('app/cohorts', [CohortController::class, 'store'])->name('academy.cohorts.store');
        Route::patch('app/cohorts/{cohort}', [CohortController::class, 'update'])->name('academy.cohorts.update');
        Route::get('app/applications/{application}', [ApplicationController::class, 'show'])->name('academy.applications.show');
        Route::patch('app/applications/{application}', [ApplicationController::class, 'update'])->name('academy.applications.update');
        Route::patch('app/applications/{application}/review', [ApplicationController::class, 'review'])->name('academy.applications.review');
        Route::get('app/finance/payments', [AcademyPaymentController::class, 'index'])->name('academy.payments.index');
        Route::get('app/finance/payments/{payment}/receipt', [AcademyPaymentController::class, 'receipt'])->name('academy.payments.receipt');
        Route::post('app/finance/payments/{payment}/reversals', [AcademyPaymentController::class, 'reverse'])->name('academy.payments.reversals.store');
        Route::get('app/finance/invoices', [AcademyInvoiceController::class, 'index'])->name('academy.invoices.index');
        Route::get('app/finance/expenses', [AcademyExpenseController::class, 'index'])->name('academy.expenses.index');
        Route::get('app/communications', [CommunicationController::class, 'index'])->name('academy.communications.index');
        Route::post('app/communications/announcements', [CommunicationController::class, 'storeAnnouncement'])->name('academy.communications.announcements.store');
        Route::patch('app/communications/settings', [CommunicationController::class, 'updateSettings'])->name('academy.communications.settings.update');
        Route::post('app/communications/invoices/{invoice}/remind', [CommunicationController::class, 'remindInvoice'])->name('academy.communications.invoices.remind');
        Route::post('app/communications/enrollments/{enrollment}/remind', [CommunicationController::class, 'remindRenewal'])->name('academy.communications.enrollments.remind');
        Route::get('app/referrals', [ReferralController::class, 'index'])->name('academy.referrals.index');
        Route::post('app/referrals', [ReferralController::class, 'store'])->name('academy.referrals.store');
        Route::patch('app/referrals/settings', [ReferralController::class, 'updateSettings'])->name('academy.referrals.settings.update');
        Route::post('app/referrals/reconcile', [ReferralController::class, 'reconcile'])->name('academy.referrals.reconcile');
        Route::patch('app/referrals/rewards/{reward}/approve', [ReferralController::class, 'approve'])->name('academy.referrals.rewards.approve');
        Route::patch('app/referrals/rewards/{reward}/paid', [ReferralController::class, 'markPaid'])->name('academy.referrals.rewards.paid');
        Route::get('app/scholarships', [ScholarshipController::class, 'index'])->name('academy.scholarships.index');
        Route::post('app/scholarships/sponsors', [ScholarshipController::class, 'storeSponsor'])->name('academy.scholarships.sponsors.store');
        Route::patch('app/scholarships/sponsors/{sponsor}', [ScholarshipController::class, 'updateSponsor'])->name('academy.scholarships.sponsors.update');
        Route::post('app/scholarships/commitments', [ScholarshipController::class, 'storeCommitment'])->name('academy.scholarships.commitments.store');
        Route::patch('app/scholarships/commitments/{commitment}', [ScholarshipController::class, 'updateCommitment'])->name('academy.scholarships.commitments.update');
        Route::post('app/scholarships/schemes', [ScholarshipController::class, 'storeScheme'])->name('academy.scholarships.schemes.store');
        Route::patch('app/scholarships/schemes/{scheme}', [ScholarshipController::class, 'updateScheme'])->name('academy.scholarships.schemes.update');
        Route::post('app/scholarships/awards', [ScholarshipController::class, 'storeAward'])->name('academy.scholarships.awards.store');
        Route::post('app/scholarships/awards/{award}/allocations', [ScholarshipController::class, 'applyAward'])->name('academy.scholarships.awards.allocations.store');
        Route::post('app/scholarships/allocations/{allocation}/contributions', [ScholarshipController::class, 'recordContribution'])->name('academy.scholarships.contributions.store');
        Route::get('app/store', [StoreController::class, 'index'])->name('academy.store.index');
        Route::post('app/store/products', [StoreController::class, 'storeProduct'])->name('academy.store.products.store');
        Route::patch('app/store/products/{product}', [StoreController::class, 'updateProduct'])->name('academy.store.products.update');
        Route::get('app/store/products/{product}/image', [StoreController::class, 'image'])->name('academy.store.products.image');
        Route::post('app/store/products/{product}/image', [StoreController::class, 'updateImage'])->name('academy.store.products.image.update');
        Route::post('app/store/products/{product}/variants', [StoreController::class, 'storeVariant'])->name('academy.store.variants.store');
        Route::post('app/store/variants/{variant}/stock', [StoreController::class, 'adjustStock'])->name('academy.store.stock.adjust');
        Route::post('app/store/variants/{variant}/stock-transfer', [StoreController::class, 'transferStock'])->name('academy.store.stock.transfer');
        Route::post('app/store/categories', [StoreController::class, 'storeCategory'])->name('academy.store.categories.store');
        Route::post('app/store/tags', [StoreController::class, 'storeTag'])->name('academy.store.tags.store');
        Route::post('app/store/orders', [StoreController::class, 'storeOrder'])->name('academy.store.orders.store');
        Route::get('app/store/orders/{order}', [StoreController::class, 'showOrder'])->name('academy.store.orders.show');
        Route::post('app/store/orders/{order}/payments', [StoreController::class, 'recordPayment'])->name('academy.store.orders.payments.store');
        Route::patch('app/store/orders/{order}/fulfillment', [StoreController::class, 'updateFulfillment'])->name('academy.store.orders.fulfillment.update');
        Route::patch('app/store/orders/{order}/cancel', [StoreController::class, 'cancelOrder'])->name('academy.store.orders.cancel');
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
        Route::patch('app/settings/academy/profile', [AcademyBrandingController::class, 'updateProfile'])
            ->name('academy.branding.profile.update');
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
            Route::patch('academies/{academy}/paystack-fees', [PlatformAcademyBrandingController::class, 'updatePaystackFees'])
                ->name('academies.paystack-fees.update');
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
