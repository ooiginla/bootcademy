<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademyInvoice;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AcademyInvoiceController extends Controller
{
    public function index(Request $request, AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);
        $academy = $context->academy();
        $activeCycleId = $academy->cycles()->where('status', 'active')->value('id');
        $cycleId = $request->has('cycle_id') ? ($request->integer('cycle_id') ?: null) : $activeCycleId;
        $programmeId = $request->integer('programme_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        $feeName = mb_substr(trim((string) $request->query('fee_name', '')), 0, 160);
        $status = trim((string) $request->query('status', ''));
        if (! in_array($status, array_column(AcademyInvoiceStatus::cases(), 'value'), true)) {
            $status = '';
        }
        $dateFrom = $this->validDate((string) $request->query('date_from', ''));
        $dateTo = $this->validDate((string) $request->query('date_to', ''));

        $query = $academy->invoices()
            ->when($cycleId, fn (Builder $query) => $query->where('academy_cycle_id', $cycleId))
            ->when($programmeId, fn (Builder $query) => $query->where('programme_id', $programmeId))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($feeName !== '', fn (Builder $query) => $query->where('fee_name', $feeName))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($dateFrom, fn (Builder $query) => $query->where('issued_at', '>=', Carbon::parse($dateFrom, $academy->timezone)->startOfDay()->utc()))
            ->when($dateTo, fn (Builder $query) => $query->where('issued_at', '<=', Carbon::parse($dateTo, $academy->timezone)->endOfDay()->utc()));

        $summary = (clone $query)->toBase()->selectRaw(
            "COALESCE(SUM(CASE WHEN status <> 'void' THEN total_minor ELSE 0 END), 0) AS billed_minor,
             COALESCE(SUM(CASE WHEN status <> 'void' THEN paid_minor ELSE 0 END), 0) AS paid_minor,
             COALESCE(SUM(CASE WHEN status <> 'void' THEN total_minor - paid_minor - written_off_minor - scholarship_concession_minor ELSE 0 END), 0) AS owed_minor,
             COALESCE(SUM(CASE WHEN status <> 'void' THEN written_off_minor ELSE 0 END), 0) AS written_off_minor",
        )->first();

        return Inertia::render('academies/invoices', [
            'invoices' => $query->with([
                'application:id,reference,first_name,middle_name,last_name',
                'player:id,first_name,middle_name,last_name',
                'cycle:id,name', 'programme:id,name', 'branch:id,name',
            ])->orderByDesc('issued_at')->orderByDesc('id')->paginate(25)->withQueryString()
                ->through(fn (AcademyInvoice $invoice): array => $this->invoiceData($invoice, $academy->timezone)),
            'summary' => [
                'currency' => $academy->currency,
                'billed' => Money::fromMinor((int) $summary->billed_minor),
                'paid' => Money::fromMinor((int) $summary->paid_minor),
                'owed' => Money::fromMinor((int) $summary->owed_minor),
                'written_off' => Money::fromMinor((int) $summary->written_off_minor),
            ],
            'cycles' => $academy->cycles()->orderByRaw("CASE status WHEN 'active' THEN 1 ELSE 2 END")->orderByDesc('starts_on')->get(['id', 'name']),
            'programmes' => $academy->programmes()->orderBy('name')->get(['id', 'name']),
            'branches' => $academy->branches()->orderBy('name')->get(['id', 'name']),
            'fee_names' => $academy->invoices()->whereNotNull('fee_name')->distinct()->orderBy('fee_name')->pluck('fee_name'),
            'filters' => [
                'cycle_id' => $cycleId, 'programme_id' => $programmeId, 'branch_id' => $branchId,
                'fee_name' => $feeName, 'status' => $status, 'date_from' => $dateFrom, 'date_to' => $dateTo,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function invoiceData(AcademyInvoice $invoice, string $timezone): array
    {
        $person = $invoice->player ?? $invoice->application;

        return [
            'id' => $invoice->id, 'number' => $invoice->number, 'fee_name' => $invoice->fee_name ?? 'Fee',
            'fee_type' => $invoice->purpose->value, 'status' => $invoice->status->value,
            'issued_on' => $invoice->issued_at->setTimezone($timezone)->toDateString(),
            'currency' => $invoice->currency, 'billed' => Money::fromMinor($invoice->total_minor),
            'paid' => Money::fromMinor($invoice->paid_minor),
            'owed' => Money::fromMinor($invoice->status === AcademyInvoiceStatus::Void ? 0 : $invoice->balanceMinor()),
            'written_off' => Money::fromMinor($invoice->written_off_minor),
            'scholarship_concession' => Money::fromMinor($invoice->scholarship_concession_minor),
            'sponsor_responsibility' => Money::fromMinor($invoice->sponsor_responsibility_minor),
            'sponsor_paid' => Money::fromMinor($invoice->sponsor_paid_minor),
            'personal_owed' => Money::fromMinor($invoice->personalBalanceMinor()),
            'payer_name' => $person ? collect([$person->first_name, $person->middle_name, $person->last_name])->filter()->implode(' ') : 'Unknown payer',
            'player_id' => $invoice->player_id, 'application_id' => $invoice->application_id,
            'application_reference' => $invoice->application?->reference,
            'cycle_name' => $invoice->cycle->name, 'programme_name' => $invoice->programme?->name,
            'branch_name' => $invoice->branch?->name,
        ];
    }

    private function validDate(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
