<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyInvoicePurpose;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyWebsite;
use App\Models\Application;
use App\Models\ApplicationIntake;
use App\Models\Branch;
use App\Models\Cohort;
use App\Models\OnlinePaymentIntent;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Notifications\AcademyPaymentReceiptNotification;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicApplicationPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_application_is_saved_before_payment_and_exposes_server_calculated_options(): void
    {
        Notification::fake();
        [$academy, $website, $offering] = $this->offering();

        $response = $this->post(route('public.academy.application.store', $website->subdomain), $this->payload($offering));
        $application = Application::query()->firstOrFail();
        $selectedPlan = $offering->paymentPlans()->sole();

        $response->assertRedirectContains('/applications/'.$application->reference.'/fees?token=');
        $response->assertRedirectContains('submitted=1');
        $this->assertSame('applicant@example.com', $application->email);
        $this->assertSame('website', $application->source);
        $this->assertSame($offering->branch_id, $application->branch_id);
        $this->assertSame($selectedPlan->id, $application->programme_cycle_payment_plan_id);
        $this->assertNotNull($application->public_access_token_hash);
        $this->assertDatabaseHas('academy_invoices', ['application_id' => $application->id, 'purpose' => AcademyInvoicePurpose::Application->value, 'total_minor' => 500000]);
        $this->assertDatabaseHas('academy_invoices', [
            'application_id' => $application->id,
            'purpose' => AcademyInvoicePurpose::Programme->value,
            'programme_cycle_payment_plan_id' => $selectedPlan->id,
            'total_minor' => 13000000,
        ]);
        $programmeInvoice = $application->invoices()->where('purpose', AcademyInvoicePurpose::Programme->value)->firstOrFail();
        $this->assertSame(['First plan payment', 'Final plan payment'], $programmeInvoice->installments()->orderBy('sequence')->pluck('name')->all());
        Notification::assertSentOnDemand(ApplicationSubmittedNotification::class, function ($notification, array $channels, $notifiable): bool {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routeNotificationFor('mail') === 'applicant@example.com'
                && in_array('mail', $channels, true)
                && str_contains(implode(' ', $mail->introLines), 'Application reference:')
                && str_contains(implode(' ', $mail->introLines), 'Design Bootcamp');
        });

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->get(route('public.academy.application-fees', [
            $website->subdomain,
            $application->reference,
            'token' => $query['token'],
            'submitted' => 1,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/application-fees')
                ->where('submitted', true)
                ->where('application.email', 'applicant@example.com')
                ->has('payment_plans', 1)
                ->where('payment_plans.0.is_selected', true)
                ->where('payment_plans.0.first_payment.name', 'First plan payment')
                ->has('payment_options', 1)
                ->where('payment_options.0.key', 'initial')
                ->where('payment_options.0.programme_label', 'First plan payment'));

        $this->get(route('public.academy.application-complete', [$website->subdomain, $application->reference, 'token' => $query['token']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/application-complete')
                ->where('application.reference', $application->reference)
                ->where('payment.status', 'skipped')
                ->where('payment.amount', null));

        $this->get(route('public.academy.application-fees', [$website->subdomain, $application->reference, 'token' => 'wrong']))->assertNotFound();
    }

    public function test_all_locations_intake_records_the_applicants_preferred_specific_location(): void
    {
        Notification::fake();
        [$academy, $website, $offering] = $this->offering();
        $preferredBranch = Branch::factory()->for($academy)->create(['name' => 'Preferred Campus']);
        $intake = ApplicationIntake::query()->where('programme_cycle_id', $offering->id)->firstOrFail();
        $offering->update(['branch_id' => null, 'branch_scope_id' => 0]);
        $intake->cohort()->update(['branch_id' => null]);
        $intake->update(['branch_id' => null, 'branch_scope_id' => 0]);

        $payload = $this->payload($offering);
        $payload['preferred_branch_id'] = $preferredBranch->id;
        $this->post(route('public.academy.application.store', $website->subdomain), $payload)->assertRedirect();

        $this->assertDatabaseHas(Application::class, [
            'academy_id' => $academy->id,
            'application_intake_id' => $intake->id,
            'branch_id' => $preferredBranch->id,
        ]);
    }

    public function test_applicant_can_change_payment_plan_and_add_general_cycle_fees_before_payment(): void
    {
        Notification::fake();
        [$academy, $website, $offering] = $this->offering();
        $alternatePlan = $offering->paymentPlans()->create([
            'academy_id' => $academy->id,
            'term_scope_id' => 0,
            'cohort_scope_id' => 0,
            'name' => 'Flexible plan',
            'description' => 'A lower first payment.',
            'sort_order' => 200,
            'currency' => 'NGN',
            'total_minor' => 8000000,
            'is_default' => false,
            'is_active' => true,
        ]);
        $alternatePlan->items()->create([
            'academy_id' => $academy->id,
            'name' => 'Flexible tuition',
            'description' => 'Two equal payments',
            'amount_minor' => 8000000,
            'sort_order' => 0,
        ]);
        $alternatePlan->installments()->createMany([
            ['academy_id' => $academy->id, 'name' => 'Flexible first payment', 'amount_minor' => 4000000, 'sequence' => 1],
            ['academy_id' => $academy->id, 'name' => 'Flexible final payment', 'amount_minor' => 4000000, 'sequence' => 2],
        ]);
        $compulsoryFee = $academy->cycleFees()->create([
            'academy_cycle_id' => $offering->academy_cycle_id,
            'term_scope_id' => 0,
            'cohort_scope_id' => 0,
            'name' => 'Annual insurance',
            'category' => 'Insurance',
            'currency' => 'NGN',
            'amount_minor' => 200000,
            'is_compulsory' => true,
            'applies_to_all_programmes' => true,
            'is_active' => true,
        ]);
        $optionalFee = $academy->cycleFees()->create([
            'academy_cycle_id' => $offering->academy_cycle_id,
            'term_scope_id' => 0,
            'cohort_scope_id' => 0,
            'name' => 'Training kit',
            'category' => 'Kit',
            'currency' => 'NGN',
            'amount_minor' => 300000,
            'is_compulsory' => false,
            'applies_to_all_programmes' => true,
            'is_active' => true,
        ]);

        [$application, $token] = $this->submit($website, $offering);
        $originalInvoice = $application->invoices()
            ->where('purpose', AcademyInvoicePurpose::Programme->value)
            ->firstOrFail();
        $this->assertDatabaseHas('academy_invoices', [
            'application_id' => $application->id,
            'academy_cycle_fee_id' => $compulsoryFee->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseMissing('academy_invoices', [
            'application_id' => $application->id,
            'academy_cycle_fee_id' => $optionalFee->id,
        ]);

        $this->post(route('public.academy.application-fees.configure', [
            $website->subdomain,
            $application->reference,
        ]), [
            'token' => $token,
            'programme_cycle_payment_plan_id' => $alternatePlan->id,
            'cycle_fee_ids' => [$optionalFee->id],
        ])->assertRedirect();

        $this->assertSame($alternatePlan->id, $application->fresh()->programme_cycle_payment_plan_id);
        $this->assertSame('void', $originalInvoice->fresh()->status->value);
        $this->assertDatabaseHas('academy_invoices', [
            'application_id' => $application->id,
            'purpose' => AcademyInvoicePurpose::Programme->value,
            'programme_cycle_payment_plan_id' => $alternatePlan->id,
            'total_minor' => 8000000,
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('academy_invoices', [
            'application_id' => $application->id,
            'academy_cycle_fee_id' => $optionalFee->id,
            'status' => 'issued',
        ]);

        $this->get(route('public.academy.application-fees', [
            $website->subdomain,
            $application->reference,
            'token' => $token,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('payment_plans.1.name', 'Flexible plan')
            ->where('payment_plans.1.is_selected', true)
            ->where('cycle_fees.0.name', 'Annual insurance')
            ->where('cycle_fees.0.is_selected', true)
            ->where('cycle_fees.1.name', 'Training kit')
            ->where('cycle_fees.1.is_selected', true)
            ->where('payment_options.0.key', 'initial')
            ->where('payment_options.0.programme_label', 'Flexible first payment')
            ->where('payment_options.0.base_amount', '50000.00'));

        $this->post(route('public.academy.application-fees.configure', [
            $website->subdomain,
            $application->reference,
        ]), [
            'token' => $token,
            'programme_cycle_payment_plan_id' => $alternatePlan->id,
            'cycle_fee_ids' => [],
        ])->assertRedirect();
        $this->assertDatabaseHas('academy_invoices', [
            'application_id' => $application->id,
            'academy_cycle_fee_id' => $compulsoryFee->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('academy_invoices', [
            'application_id' => $application->id,
            'academy_cycle_fee_id' => $optionalFee->id,
            'status' => 'void',
        ]);
    }

    public function test_guardian_information_can_be_disabled_per_academy(): void
    {
        Notification::fake();
        [$academy, $website, $offering] = $this->offering();
        $academy->update(['collect_guardian_information' => false]);

        $this->post(route('public.academy.application.store', $website->subdomain), $this->payload($offering))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertNull(Application::query()->firstOrFail()->guardian_consent_at);

        $payload = $this->payload($offering);
        $payload['email'] = 'another@example.com';
        $payload['guardian_first_name'] = 'Not accepted';
        $this->post(route('public.academy.application.store', $website->subdomain), $payload)
            ->assertSessionHasErrors('guardian_first_name');
    }

    public function test_public_application_rejects_a_payment_plan_from_another_academy(): void
    {
        Notification::fake();
        [, $website, $offering] = $this->offering('plan-owner');
        [, , $foreignOffering] = $this->offering('foreign-plan-owner');
        $payload = $this->payload($offering);
        $payload['programme_cycle_payment_plan_id'] = $foreignOffering->paymentPlans()->value('id');

        $this->post(route('public.academy.application.store', $website->subdomain), $payload)
            ->assertSessionHasErrors('programme_cycle_payment_plan_id');
        $this->assertDatabaseMissing(Application::class, ['academy_id' => $website->academy_id]);

        [$application, $token] = $this->submit($website, $offering);
        $selectedPlanId = $application->programme_cycle_payment_plan_id;

        $this->post(route('public.academy.application-fees.configure', [
            $website->subdomain,
            $application->reference,
        ]), [
            'token' => $token,
            'programme_cycle_payment_plan_id' => $foreignOffering->paymentPlans()->value('id'),
            'cycle_fee_ids' => [],
        ])->assertSessionHasErrors('programme_cycle_payment_plan_id');

        $this->assertSame($selectedPlanId, $application->fresh()->programme_cycle_payment_plan_id);
    }

    public function test_paystack_initialization_uses_only_the_server_calculated_amount(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        [$academy, $website, $offering] = $this->offering();
        [$application, $token] = $this->submit($website, $offering);
        Http::preventStrayRequests();

        // Make the provider echo the generated reference while still inspecting the request.
        Http::fake(function ($request) {
            return Http::response(['status' => true, 'data' => [
                'access_code' => 'access-code', 'reference' => $request['reference'],
            ]]);
        });

        $response = $this->postJson(route('public.academy.payment-initialize', [$website->subdomain, $application->reference]), [
            'token' => $token, 'selection' => 'initial', 'amount' => 1,
        ]);
        $response->assertOk()->assertJsonStructure(['access_code', 'reference', 'verify_url']);

        $intent = OnlinePaymentIntent::query()->firstOrFail();
        $this->assertSame(7000000, $intent->base_amount_minor);
        $this->assertGreaterThan(7000000, $intent->charged_amount_minor);
        $this->assertSame($response->json('reference'), $intent->reference);
        Http::assertSent(fn ($request): bool => $request['amount'] === $intent->charged_amount_minor
            && $request['email'] === 'applicant@example.com'
            && $request['reference'] === $intent->reference);

        $intent->update(['status' => 'failed', 'failure_reason' => 'Card declined.']);
        $this->get(route('public.academy.application-complete', [
            $website->subdomain, $application->reference, 'token' => $token, 'intent' => $intent->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('payment.status', 'failed')
            ->where('payment.amount', fn (string $amount): bool => (float) $amount > 5000));
    }

    public function test_verified_payment_is_allocated_once_and_receipt_is_queued(): void
    {
        Notification::fake();
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        [, $website, $offering] = $this->offering();
        [$application, $token] = $this->submit($website, $offering);
        Http::fake(fn ($request) => str_contains($request->url(), '/transaction/initialize')
            ? Http::response(['status' => true, 'data' => ['access_code' => 'access-code', 'reference' => $request['reference']]])
            : Http::response(['status' => true, 'data' => [
                'status' => 'success', 'reference' => OnlinePaymentIntent::query()->firstOrFail()->reference,
                'amount' => OnlinePaymentIntent::query()->firstOrFail()->charged_amount_minor,
                'currency' => 'NGN', 'fees' => 8600,
            ]]));

        $initialize = $this->postJson(route('public.academy.payment-initialize', [$website->subdomain, $application->reference]), [
            'token' => $token, 'selection' => 'initial',
        ])->assertOk();
        $intent = OnlinePaymentIntent::query()->firstOrFail();
        $verifyRoute = route('public.academy.payment-verify', [$website->subdomain, $application->reference, $intent]);

        $verified = $this->postJson($verifyRoute, ['token' => $token])
            ->assertOk()
            ->assertJson(['status' => 'verified'])
            ->assertJsonStructure(['completion_url']);
        $this->postJson($verifyRoute, ['token' => $token])->assertOk()->assertJson(['status' => 'verified']);

        $this->get($verified->json('completion_url'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/application-complete')
                ->where('payment.status', 'verified')
                ->where('payment.amount', fn (string $amount): bool => (float) $amount > 5000)
                ->where('payment.currency', 'NGN'));

        $this->assertDatabaseCount('academy_payments', 1);
        $this->assertDatabaseHas('academy_payments', [
            'application_id' => $application->id, 'amount_minor' => 7000000,
            'gross_amount_minor' => $intent->charged_amount_minor,
            'customer_fee_minor' => $intent->surcharge_minor, 'provider' => 'paystack',
        ]);
        $this->assertDatabaseHas('academy_invoices', [
            'application_id' => $application->id, 'purpose' => AcademyInvoicePurpose::Application->value,
            'paid_minor' => 500000, 'status' => 'paid',
        ]);
        $this->assertDatabaseHas('academy_invoices', [
            'application_id' => $application->id, 'purpose' => AcademyInvoicePurpose::Programme->value,
            'paid_minor' => 6500000, 'status' => 'partially_paid',
        ]);
        Notification::assertSentOnDemand(AcademyPaymentReceiptNotification::class, function ($notification, array $channels, $notifiable): bool {
            $mail = $notification->toMail($notifiable);
            $content = implode(' ', $mail->introLines);

            return $notifiable->routeNotificationFor('mail') === 'applicant@example.com'
                && in_array('mail', $channels, true)
                && str_contains($content, 'Paystack reference:')
                && str_contains($content, 'Application fee')
                && str_contains($content, 'Online processing fee:')
                && str_contains($content, 'Remaining application balance:');
        });
    }

    public function test_failed_payment_initialization_still_leads_to_the_submitted_application_page(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        [, $website, $offering] = $this->offering();
        [$application, $token] = $this->submit($website, $offering);
        Http::fake(['https://api.paystack.co/*' => Http::response(['status' => false], 502)]);

        $response = $this->postJson(route('public.academy.payment-initialize', [$website->subdomain, $application->reference]), [
            'token' => $token, 'selection' => 'initial',
        ])->assertStatus(502)->assertJsonStructure(['completion_url']);

        $this->get($response->json('completion_url'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/application-complete')
                ->where('application.reference', $application->reference)
                ->where('payment.status', 'failed')
                ->where('payment.amount', fn (string $amount): bool => (float) $amount > 5000));
    }

    public function test_another_academy_cannot_use_an_application_token_to_access_an_intent(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        [, $website, $offering] = $this->offering('first-academy');
        [$application, $token] = $this->submit($website, $offering);
        [, $otherWebsite] = $this->offering('second-academy');

        $this->postJson(route('public.academy.payment-initialize', [$otherWebsite->subdomain, $application->reference]), [
            'token' => $token, 'selection' => 'initial',
        ])->assertNotFound();
    }

    public function test_signed_paystack_webhooks_are_idempotent_and_invalid_signatures_are_rejected(): void
    {
        Notification::fake();
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        [, $website, $offering] = $this->offering();
        [$application, $token] = $this->submit($website, $offering);
        Http::fake(fn ($request) => Http::response(['status' => true, 'data' => [
            'access_code' => 'access-code', 'reference' => $request['reference'],
        ]]));
        $this->postJson(route('public.academy.payment-initialize', [$website->subdomain, $application->reference]), [
            'token' => $token, 'selection' => 'initial',
        ])->assertOk();
        $intent = OnlinePaymentIntent::query()->firstOrFail();
        $body = json_encode([
            'event' => 'charge.success',
            'data' => [
                'status' => 'success', 'reference' => $intent->reference,
                'amount' => $intent->charged_amount_minor, 'currency' => 'NGN', 'fees' => 8600,
            ],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $body, 'sk_test_secret');

        $this->call('POST', route('paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ], $body)->assertOk();
        $this->call('POST', route('paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ], $body)->assertOk();

        $this->assertDatabaseCount('academy_payments', 1);
        $this->call('POST', route('paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => str_repeat('0', 128),
        ], $body)->assertUnauthorized();
    }

    /** @return array{Academy, AcademyWebsite, ProgrammeCycle} */
    private function offering(string $subdomain = 'public-payments'): array
    {
        $academy = Academy::factory()->create();
        $website = AcademyWebsite::query()->create([
            'academy_id' => $academy->id, 'subdomain' => $subdomain,
            'theme_key' => 'launch', 'is_published' => true, 'sections' => [],
        ]);
        $programme = Programme::factory()->for($academy)->create(['name' => 'Design Bootcamp']);
        $branch = Branch::factory()->for($academy)->create(['name' => 'Main Campus']);
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => 'January Cohort', 'type' => 'fixed',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'status' => 'active',
        ]);
        $offering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'branch_id' => $branch->id, 'branch_scope_id' => $branch->id,
            'fee_name' => 'Bootcamp tuition', 'currency' => 'NGN', 'application_fee_minor' => 500000,
            'payment_mode' => 'installments', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);
        $offering->feeItems()->create(['academy_id' => $academy->id, 'name' => 'Tuition', 'amount_minor' => 12000000, 'sort_order' => 1]);
        $offering->installments()->createMany([
            ['academy_id' => $academy->id, 'name' => 'First installment', 'amount_minor' => 6000000, 'sequence' => 1],
            ['academy_id' => $academy->id, 'name' => 'Final installment', 'amount_minor' => 6000000, 'sequence' => 2],
        ]);
        $plan = $offering->paymentPlans()->create([
            'academy_id' => $academy->id,
            'term_scope_id' => 0,
            'cohort_scope_id' => 0,
            'name' => 'Two-payment plan',
            'description' => 'Two payments with a plan adjustment.',
            'currency' => 'NGN',
            'total_minor' => 13000000,
            'is_default' => true,
            'is_active' => true,
        ]);
        $plan->items()->createMany([
            ['academy_id' => $academy->id, 'name' => 'Tuition', 'amount_minor' => 12000000, 'sort_order' => 0],
            ['academy_id' => $academy->id, 'name' => 'Plan adjustment', 'amount_minor' => 1000000, 'sort_order' => 1],
        ]);
        $plan->installments()->createMany([
            ['academy_id' => $academy->id, 'name' => 'First plan payment', 'amount_minor' => 6500000, 'sequence' => 1],
            ['academy_id' => $academy->id, 'name' => 'Final plan payment', 'amount_minor' => 6500000, 'sequence' => 2],
        ]);
        $cohort = Cohort::query()->create([
            'academy_id' => $academy->id, 'programme_cycle_id' => $offering->id,
            'branch_id' => $branch->id, 'name' => 'January cohort',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'status' => 'open',
        ]);
        ApplicationIntake::query()->create([
            'academy_id' => $academy->id,
            'programme_cycle_id' => $offering->id,
            'cohort_id' => $cohort->id,
            'cohort_scope_id' => $cohort->id,
            'branch_id' => $branch->id,
            'branch_scope_id' => $branch->id,
            'is_enabled' => true,
        ]);

        return [$academy, $website, $offering];
    }

    /** @return array{Application, string} */
    private function submit(AcademyWebsite $website, ProgrammeCycle $offering): array
    {
        $response = $this->post(route('public.academy.application.store', $website->subdomain), $this->payload($offering));
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return [Application::query()->where('academy_id', $website->academy_id)->firstOrFail(), $query['token']];
    }

    /** @return array<string, mixed> */
    private function payload(ProgrammeCycle $offering): array
    {
        return [
            'application_intake_id' => ApplicationIntake::query()->where('programme_cycle_id', $offering->id)->value('id'),
            'preferred_branch_id' => $offering->branch_id,
            'programme_cycle_payment_plan_id' => $offering->paymentPlans()->where('is_default', true)->value('id'),
            'first_name' => 'Amara', 'last_name' => 'Okafor', 'email' => 'applicant@example.com',
            'date_of_birth' => '2014-05-02', 'gender' => 'Female', 'country' => 'Nigeria',
            'consent_accepted' => '1',
        ];
    }
}
