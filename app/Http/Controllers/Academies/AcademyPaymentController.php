<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\CancelAcademyInvoice;
use App\Academies\Actions\RecordVerifiedAcademyPayment;
use App\Academies\Actions\ReverseAcademyPayment;
use App\Academies\Actions\UpdateInvoiceInstallments;
use App\Academies\Actions\WriteOffAcademyInvoice;
use App\Enums\AcademyPaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\CancelAcademyInvoiceRequest;
use App\Http\Requests\Academies\RecordAcademyPaymentRequest;
use App\Http\Requests\Academies\ReverseAcademyPaymentRequest;
use App\Http\Requests\Academies\UpdateInvoiceInstallmentsRequest;
use App\Http\Requests\Academies\WriteOffAcademyInvoiceRequest;
use App\Models\AcademyInvoice;
use App\Models\AcademyPayment;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademyPaymentController extends Controller
{
    public function index(Request $request, AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);
        $academy = $context->academy();
        $cycleId = $request->integer('cycle_id') ?: null;
        $programmeId = $request->integer('programme_id') ?: null;
        $method = trim((string) $request->query('method', ''));
        if (! in_array($method, array_column(AcademyPaymentMethod::cases(), 'value'), true)) {
            $method = '';
        }

        return Inertia::render('academies/payments', [
            'payments' => $academy->payments()
                ->withCount('adjustments')->with([
                    'application:id,reference,first_name,middle_name,last_name,converted_player_id',
                    'player:id,first_name,middle_name,last_name',
                    'sponsor:id,name',
                    'allocations.invoice:id,number,purpose,academy_cycle_id,programme_cycle_id,programme_id',
                    'allocations.invoice.cycle:id,name', 'allocations.invoice.programmeCycle.programme:id,name',
                ])
                ->when($cycleId, fn ($query) => $query->whereHas('allocations.invoice', fn ($query) => $query->where('academy_cycle_id', $cycleId)))
                ->when($programmeId, fn ($query) => $query->whereHas('allocations.invoice', fn ($query) => $query->where('programme_id', $programmeId)))
                ->when($method !== '', fn ($query) => $query->where('method', $method))
                ->orderByDesc('received_on')->orderByDesc('id')->paginate(25)->withQueryString()
                ->through(function (AcademyPayment $payment): array {
                    $invoice = $payment->allocations->first()?->invoice;
                    $person = $payment->player ?? $payment->application;

                    return [
                        'id' => $payment->id, 'reference' => $payment->reference,
                        'external_reference' => $payment->external_reference,
                        'received_on' => $payment->received_on->toDateString(),
                        'currency' => $payment->currency, 'amount' => Money::fromMinor($payment->amount_minor),
                        'method' => $payment->method->value, 'status' => $payment->status->value,
                        'reversed' => Money::fromMinor($payment->reversed_minor),
                        'net' => Money::fromMinor($payment->amount_minor - $payment->reversed_minor),
                        'adjustments_count' => $payment->adjustments_count,
                        'payer_name' => $payment->sponsor?->name ?? ($person ? collect([$person->first_name, $person->middle_name, $person->last_name])->filter()->implode(' ') : 'Unknown payer'),
                        'payer_type' => $payment->sponsor_id !== null ? 'Sponsor' : 'Student / applicant',
                        'application_id' => $payment->application_id, 'player_id' => $payment->player_id,
                        'application_reference' => $payment->application?->reference,
                        'invoice_number' => $invoice?->number, 'payment_type' => $invoice?->purpose->value,
                        'cycle_name' => $invoice?->cycle?->name,
                        'programme_name' => $invoice?->programmeCycle?->programme?->name,
                    ];
                }),
            'cycles' => $academy->cycles()->orderByDesc('starts_on')->get(['id', 'name']),
            'programmes' => $academy->programmes()->orderBy('name')->get(['id', 'name']),
            'filters' => ['cycle_id' => $cycleId, 'programme_id' => $programmeId, 'method' => $method],
            'today' => now($academy->timezone)->toDateString(),
        ]);
    }

    public function updateInstallments(
        UpdateInvoiceInstallmentsRequest $request,
        AcademyInvoice $invoice,
        AcademyContext $context,
        UpdateInvoiceInstallments $update,
    ): RedirectResponse {
        $update->handle($context->academy(), $invoice, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Student-specific installment schedule saved.']);

        return back();
    }

    public function store(
        RecordAcademyPaymentRequest $request,
        AcademyInvoice $invoice,
        AcademyContext $context,
        RecordVerifiedAcademyPayment $record,
    ): RedirectResponse {
        $record->handle($context->academy(), $invoice, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Verified payment recorded.']);

        return back();
    }

    public function cancel(CancelAcademyInvoiceRequest $request, AcademyInvoice $invoice, AcademyContext $context, CancelAcademyInvoice $cancel): RedirectResponse
    {
        $cancel->handle($context->academy(), $invoice, $request->user(), $request->validated('reason'), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice cancelled.']);

        return back();
    }

    public function writeOff(WriteOffAcademyInvoiceRequest $request, AcademyInvoice $invoice, AcademyContext $context, WriteOffAcademyInvoice $writeOff): RedirectResponse
    {
        $writeOff->handle($context->academy(), $invoice, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice write-off recorded as an expense.']);

        return back();
    }

    public function reverse(ReverseAcademyPaymentRequest $request, AcademyPayment $payment, AcademyContext $context, ReverseAcademyPayment $reverse): RedirectResponse
    {
        $reverse->handle($context->academy(), $payment, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment reversal recorded.']);

        return back();
    }

    public function receipt(AcademyPayment $payment, AcademyContext $context): View
    {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($payment->academy_id === $context->id(), 404);
        $payment->load([
            'application:id,reference,first_name,middle_name,last_name',
            'player:id,first_name,middle_name,last_name',
            'sponsor:id,name',
            'allocations.invoice:id,number,fee_name,purpose,academy_cycle_id,programme_id',
            'allocations.invoice.cycle:id,name', 'allocations.invoice.programme:id,name',
            'adjustments:id,academy_payment_id,amount_minor,reason,effective_on',
        ]);

        return view('academy-payment-receipt', ['academy' => $context->academy(), 'payment' => $payment]);
    }
}
