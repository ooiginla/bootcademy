<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AcademyContext $context): Response
    {
        $academy = $context->academy();
        $today = now($academy->timezone)->toDateString();
        $dateFrom = $this->date((string) $request->query('date_from')) ?? now($academy->timezone)->startOfMonth()->toDateString();
        $dateTo = $this->date((string) $request->query('date_to')) ?? $today;
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        $branchId = $request->integer('branch_id') ?: null;
        $canManage = $context->canManageFinance();

        return Inertia::render('dashboard', [
            'operational' => $canManage ? $this->metrics($context, $dateFrom, $dateTo, $branchId) : null,
            'branches' => $academy->branches()->orderBy('name')->get(['id', 'name']),
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo, 'branch_id' => $branchId],
            'today' => $today,
        ]);
    }

    /** @return array<string, mixed> */
    private function metrics(AcademyContext $context, string $from, string $to, ?int $branchId): array
    {
        $academy = $context->academy();
        $fromUtc = Carbon::parse($from, $academy->timezone)->startOfDay()->utc();
        $toUtc = Carbon::parse($to, $academy->timezone)->endOfDay()->utc();
        $activePlayers = $academy->enrollments()->where('status', EnrollmentStatus::Active->value)
            ->whereDate('starts_on', '<=', $to)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $to))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->distinct('player_id')->count('player_id');
        $newEnrollments = $academy->enrollments()->whereBetween('starts_on', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
        $sessionQuery = $academy->trainingSessions()->getQuery()->whereBetween('starts_at', [$fromUtc, $toUtc]);
        $this->branchSessions($sessionQuery, $branchId);
        $sessions = (clone $sessionQuery)->count();
        $attendance = $academy->trainingSessions()->getQuery()->whereBetween('starts_at', [$fromUtc, $toUtc]);
        $this->branchSessions($attendance, $branchId);
        $attendanceRows = $attendance->join('attendance_records', 'training_sessions.id', '=', 'attendance_records.training_session_id')
            ->selectRaw("SUM(CASE WHEN attendance_records.status IN ('present','late','absent') THEN 1 ELSE 0 END) as marked, SUM(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) as attended, SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent")->first();
        $invoiceQuery = $academy->invoices()->where('status', '<>', 'void')->whereBetween('issued_at', [$fromUtc, $toUtc])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $billed = (int) (clone $invoiceQuery)->sum('total_minor');
        $outstanding = (int) (clone $invoiceQuery)->selectRaw('COALESCE(SUM(total_minor-paid_minor-written_off_minor),0) total')->value('total');
        $aging = ['not_due' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_plus' => 0];
        $asOf = Carbon::parse($to, $academy->timezone)->startOfDay();
        foreach ((clone $invoiceQuery)->whereRaw('total_minor > paid_minor + written_off_minor')->get(['total_minor', 'paid_minor', 'written_off_minor', 'due_on']) as $invoice) {
            $balance = $invoice->total_minor - $invoice->paid_minor - $invoice->written_off_minor;
            if ($invoice->due_on === null || $invoice->due_on->greaterThanOrEqualTo($asOf)) {
                $aging['not_due'] += $balance;

                continue;
            }
            $days = $invoice->due_on->diffInDays($asOf);
            $key = $days <= 30 ? 'days_1_30' : ($days <= 60 ? 'days_31_60' : 'days_61_plus');
            $aging[$key] += $balance;
        }
        $paymentQuery = $academy->payments()->whereBetween('received_on', [$from, $to])
            ->when($branchId, fn ($q) => $q->whereHas('allocations.invoice', fn ($invoice) => $invoice->where('branch_id', $branchId)));
        $collected = (int) (clone $paymentQuery)->sum('amount_minor');
        $reversals = $academy->payments()->getQuery()->join('academy_payment_adjustments', 'academy_payments.id', '=', 'academy_payment_adjustments.academy_payment_id')
            ->whereBetween('academy_payment_adjustments.effective_on', [$from, $to])
            ->when($branchId, fn ($q) => $q->whereHas('allocations.invoice', fn ($invoice) => $invoice->where('branch_id', $branchId)))
            ->sum('academy_payment_adjustments.amount_minor');
        $expenseQuery = $academy->expenses()->whereBetween('incurred_on', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $expenses = (int) (clone $expenseQuery)->sum('amount_minor');
        $cashExpenses = (int) (clone $expenseQuery)->whereNull('academy_invoice_write_off_id')->sum('amount_minor');

        return [
            'currency' => $academy->currency,
            'active_players' => $activePlayers, 'new_enrollments' => $newEnrollments, 'sessions' => $sessions,
            'attendance_marked' => (int) ($attendanceRows->marked ?? 0),
            'attendance_rate' => (int) ($attendanceRows->marked ?? 0) > 0 ? round(((int) $attendanceRows->attended / (int) $attendanceRows->marked) * 100, 1) : null,
            'billed' => Money::fromMinor($billed), 'outstanding' => Money::fromMinor($outstanding),
            'collected' => Money::fromMinor($collected), 'reversed' => Money::fromMinor((int) $reversals),
            'net_collected' => Money::fromMinor($collected - (int) $reversals), 'expenses' => Money::fromMinor($expenses),
            'net_cash' => Money::fromMinor($collected - (int) $reversals - $cashExpenses),
            'aging' => collect($aging)->map(fn (int $amount): string => Money::fromMinor($amount)),
            'branch_metrics' => $academy->branches()->orderBy('name')->get(['id', 'name'])->map(function ($branch) use ($academy, $from, $to): array {
                $active = $academy->enrollments()->where('branch_id', $branch->id)->where('status', EnrollmentStatus::Active->value)
                    ->whereDate('starts_on', '<=', $to)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $to))->distinct('player_id')->count('player_id');
                $paid = $academy->payments()->whereBetween('received_on', [$from, $to])->whereHas('allocations.invoice', fn ($q) => $q->where('branch_id', $branch->id))->sum('amount_minor');
                $spent = $academy->expenses()->whereBetween('incurred_on', [$from, $to])->where('branch_id', $branch->id)->sum('amount_minor');

                return ['id' => $branch->id, 'name' => $branch->name, 'active_players' => $active, 'collected' => Money::fromMinor((int) $paid), 'expenses' => Money::fromMinor((int) $spent)];
            }),
        ];
    }

    private function branchSessions(Builder $query, ?int $branchId): void
    {
        $query->when($branchId, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->where('all_branches', true)->orWhereHas('branches', fn (Builder $branches) => $branches->whereKey($branchId))));
    }

    private function date(string $value): ?string
    {
        try {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::createFromFormat('!Y-m-d', $value)->toDateString() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
