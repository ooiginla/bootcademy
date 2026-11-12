<?php

namespace App\Academies\Deletion;

use App\Enums\AcademyInvitationStatus;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleFee;
use App\Models\AcademyExpense;
use App\Models\AcademyInvoice;
use App\Models\AcademyMembership;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Player;
use App\Models\Product;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lists what still belongs to a record. A record can only be soft-deleted
 * once nothing is listed. Soft-deleted dependents no longer count.
 */
class DeletionBlockers
{
    /** @return list<DeletionBlocker> */
    public function for(Model $record, Academy $academy): array
    {
        $blockers = match (true) {
            $record instanceof Application => $this->application($record),
            $record instanceof Branch => $this->branch($record, $academy),
            $record instanceof Programme => $this->programme($record, $academy),
            $record instanceof TrainingSession => $this->session($record),
            $record instanceof AcademyCycle => $this->cycle($record),
            $record instanceof AcademyCycleFee => $this->cycleFee($record),
            $record instanceof ProgrammeCycle => $this->programmeFee($record),
            $record instanceof ProgrammeCyclePaymentPlan => $this->paymentPlan($record),
            $record instanceof AcademyExpense => $this->expense($record),
            $record instanceof Player => $this->player($record),
            $record instanceof AcademyMembership => $this->coach($record),
            $record instanceof Product => $this->product($record),
            default => throw new InvalidArgumentException('Deletion is not supported for '.$record::class.'.'),
        };

        return array_values(array_filter($blockers, fn (DeletionBlocker $blocker): bool => $blocker->count > 0));
    }

    /** @return list<DeletionBlocker> */
    private function application(Application $application): array
    {
        $id = $application->id;

        return [
            // Unpaid invoices and payment attempts do not block: deleting voids the open invoices.
            // Money already received or adjusted must stay attached, so those still block.
            $this->history($this->rows($application, 'academy_payments', 'application_id', $id), 'payment', 'payments'),
            $this->history(AcademyInvoice::query()->where('academy_id', $application->academy_id)->where('application_id', $id)
                ->where(fn ($query) => $query->where('paid_minor', '>', 0)
                    ->orWhere('written_off_minor', '>', 0)
                    ->orWhere('scholarship_concession_minor', '>', 0)
                    ->orWhere('sponsor_responsibility_minor', '>', 0)
                    ->orWhereHas('allocations'))
                ->count(), 'invoice with payments or adjustments', 'invoices with payments or adjustments'),
            $this->history($this->rows($application, 'referrals', 'referred_application_id', $id), 'referral', 'referrals'),
            new DeletionBlocker($application->converted_player_id !== null && Player::query()->whereKey($application->converted_player_id)->exists() ? 1 : 0, 'converted profile', 'converted profiles'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function branch(Branch $branch, Academy $academy): array
    {
        $id = $branch->id;

        return [
            new DeletionBlocker($branch->programmes()->count(), 'programme offered here', 'programmes offered here'),
            new DeletionBlocker(TrainingSession::query()->where('academy_id', $branch->academy_id)
                ->where(fn ($query) => $query->where('branch_id', $id)->orWhereHas('branches', fn ($branches) => $branches->whereKey($id)))
                ->count(), 'session', 'sessions'),
            new DeletionBlocker(Application::query()->where('academy_id', $branch->academy_id)->where('branch_id', $id)->count(), 'application', 'applications'),
            new DeletionBlocker(AcademyExpense::query()->where('academy_id', $branch->academy_id)->where('branch_id', $id)->count(), 'expense', 'expenses'),
            new DeletionBlocker(ProgrammeCycle::query()->where('academy_id', $branch->academy_id)->where('branch_id', $id)->count(), 'programme fee', 'programme fees'),
            new DeletionBlocker($this->rows($branch, 'cohorts', 'branch_id', $id), 'cohort', 'cohorts'),
            new DeletionBlocker($this->rows($branch, 'application_intakes', 'branch_id', $id), 'application intake', 'application intakes'),
            new DeletionBlocker($this->rows($branch, 'scholarship_schemes', 'branch_id', $id), 'scholarship scheme', 'scholarship schemes'),
            new DeletionBlocker(DB::table('academy_membership_branch')
                ->join('academy_memberships', 'academy_memberships.id', '=', 'academy_membership_branch.academy_membership_id')
                ->where('academy_membership_branch.academy_id', $branch->academy_id)
                ->where('academy_membership_branch.branch_id', $id)
                ->whereNull('academy_memberships.deleted_at')
                ->count(), 'team member assigned here', 'team members assigned here'),
            new DeletionBlocker(DB::table('academy_invitation_branch')
                ->join('academy_invitations', 'academy_invitations.id', '=', 'academy_invitation_branch.academy_invitation_id')
                ->where('academy_invitation_branch.academy_id', $branch->academy_id)
                ->where('academy_invitation_branch.branch_id', $id)
                ->where('academy_invitations.status', AcademyInvitationStatus::Pending->value)
                ->count(), 'pending invitation', 'pending invitations'),
            new DeletionBlocker(DB::table('inventory_stocks')->where('academy_id', $branch->academy_id)->where('branch_id', $id)
                ->where(fn (Builder $query) => $query->where('on_hand', '>', 0)->orWhere('reserved', '>', 0))
                ->count(), 'stocked store item', 'stocked store items'),
            $this->history($this->rows($branch, 'enrollments', 'branch_id', $id), mb_strtolower($academy->member_label_singular).' enrolment', mb_strtolower($academy->member_label_singular).' enrolments'),
            $this->history($this->rows($branch, 'academy_invoices', 'branch_id', $id), 'invoice', 'invoices'),
            $this->history($this->rows($branch, 'store_orders', 'branch_id', $id), 'store order', 'store orders'),
            $this->history($this->rows($branch, 'stock_movements', 'branch_id', $id), 'stock movement', 'stock movements'),
            $this->history($this->rows($branch, 'announcements', 'branch_id', $id), 'announcement', 'announcements'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function programme(Programme $programme, Academy $academy): array
    {
        $id = $programme->id;

        return [
            new DeletionBlocker(ProgrammeCycle::query()->where('academy_id', $programme->academy_id)->where('programme_id', $id)->count(), 'programme fee', 'programme fees'),
            new DeletionBlocker(AcademyCycleFee::query()->where('academy_id', $programme->academy_id)
                ->whereHas('programmes', fn ($programmes) => $programmes->whereKey($id))->count(), 'cycle fee', 'cycle fees'),
            new DeletionBlocker(TrainingSession::query()->where('academy_id', $programme->academy_id)->where('programme_id', $id)->count(), 'session', 'sessions'),
            new DeletionBlocker(Application::query()->where('academy_id', $programme->academy_id)->where('programme_id', $id)->count(), 'application', 'applications'),
            new DeletionBlocker($this->rows($programme, 'scholarship_schemes', 'programme_id', $id), 'scholarship scheme', 'scholarship schemes'),
            $this->history($this->rows($programme, 'enrollments', 'programme_id', $id), 'enrolled '.mb_strtolower($academy->member_label_singular), 'enrolled '.mb_strtolower($academy->member_label_plural)),
            $this->history($this->rows($programme, 'academy_invoices', 'programme_id', $id), 'invoice', 'invoices'),
            $this->history($this->rows($programme, 'announcements', 'programme_id', $id), 'announcement', 'announcements'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function session(TrainingSession $session): array
    {
        return [
            $this->history($this->rows($session, 'attendance_records', 'training_session_id', $session->id), 'attendance record', 'attendance records'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function cycle(AcademyCycle $cycle): array
    {
        $id = $cycle->id;

        return [
            new DeletionBlocker($cycle->programmeCycles()->count(), 'programme fee', 'programme fees'),
            new DeletionBlocker($cycle->fees()->count(), 'cycle fee', 'cycle fees'),
            new DeletionBlocker(AcademyExpense::query()->where('academy_id', $cycle->academy_id)->where('academy_cycle_id', $id)->count(), 'expense', 'expenses'),
            new DeletionBlocker($this->rows($cycle, 'scholarship_schemes', 'academy_cycle_id', $id), 'scholarship scheme', 'scholarship schemes'),
            new DeletionBlocker($this->rows($cycle, 'sponsor_commitments', 'academy_cycle_id', $id), 'sponsor commitment', 'sponsor commitments'),
            $this->history($this->rows($cycle, 'enrollments', 'academy_cycle_id', $id), 'enrolment', 'enrolments'),
            $this->history($this->rows($cycle, 'academy_invoices', 'academy_cycle_id', $id), 'invoice', 'invoices'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function cycleFee(AcademyCycleFee $fee): array
    {
        return [
            $this->history($this->rows($fee, 'academy_invoices', 'academy_cycle_fee_id', $fee->id), 'invoice', 'invoices'),
        ];
    }

    /**
     * The standard plan mirrors the programme fee itself and is removed with
     * it, so only custom plans block the fee.
     *
     * @return list<DeletionBlocker>
     */
    private function programmeFee(ProgrammeCycle $offering): array
    {
        $id = $offering->id;
        $planIds = $offering->paymentPlans()->pluck('id');

        return [
            new DeletionBlocker($offering->paymentPlans()->count(), 'payment plan', 'payment plans'),
            new DeletionBlocker(Application::query()->where('academy_id', $offering->academy_id)
                ->where(fn ($query) => $query->where('programme_cycle_id', $id)->orWhereIn('programme_cycle_payment_plan_id', $planIds))
                ->count(), 'application', 'applications'),
            new DeletionBlocker($this->rows($offering, 'cohorts', 'programme_cycle_id', $id), 'cohort', 'cohorts'),
            new DeletionBlocker($this->rows($offering, 'application_intakes', 'programme_cycle_id', $id), 'application intake', 'application intakes'),
            $this->history($this->rows($offering, 'enrollments', 'programme_cycle_id', $id), 'enrolment', 'enrolments'),
            $this->history(DB::table('academy_invoices')->where('academy_id', $offering->academy_id)
                ->where(fn (Builder $query) => $query->where('programme_cycle_id', $id)->orWhereIn('programme_cycle_payment_plan_id', $planIds))
                ->count(), 'invoice', 'invoices'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function paymentPlan(ProgrammeCyclePaymentPlan $plan): array
    {
        return [
            new DeletionBlocker(Application::query()->where('academy_id', $plan->academy_id)->where('programme_cycle_payment_plan_id', $plan->id)->count(), 'application', 'applications'),
            $this->history($this->rows($plan, 'academy_invoices', 'programme_cycle_payment_plan_id', $plan->id), 'invoice', 'invoices'),
        ];
    }

    /**
     * Expenses created by a write-off or referral payout stay with that record.
     *
     * @return list<DeletionBlocker>
     */
    private function expense(AcademyExpense $expense): array
    {
        return [
            $this->history($expense->academy_invoice_write_off_id !== null ? 1 : 0, 'invoice write-off', 'invoice write-offs'),
            $this->history($expense->referral_reward_id !== null ? 1 : 0, 'referral reward payout', 'referral reward payouts'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function player(Player $player): array
    {
        $id = $player->id;

        return [
            $this->history($this->rows($player, 'enrollments', 'player_id', $id), 'programme enrolment', 'programme enrolments'),
            $this->history($this->rows($player, 'cohort_memberships', 'player_id', $id), 'cohort membership', 'cohort memberships'),
            $this->history($this->rows($player, 'academy_invoices', 'player_id', $id), 'invoice', 'invoices'),
            $this->history($this->rows($player, 'academy_payments', 'player_id', $id), 'payment', 'payments'),
            $this->history($this->rows($player, 'attendance_records', 'player_id', $id), 'attendance record', 'attendance records'),
            $this->history($this->rows($player, 'scholarship_awards', 'player_id', $id), 'scholarship award', 'scholarship awards'),
            $this->history(DB::table('referrals')->where('academy_id', $player->academy_id)
                ->where(fn (Builder $query) => $query->where('referred_player_id', $id)->orWhere('referrer_player_id', $id))
                ->count(), 'referral', 'referrals'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function coach(AcademyMembership $membership): array
    {
        $id = $membership->id;

        return [
            new DeletionBlocker(TrainingSession::query()->where('academy_id', $membership->academy_id)
                ->where(fn ($query) => $query->where('coach_membership_id', $id)->orWhereHas('coaches', fn ($coaches) => $coaches->whereKey($id)))
                ->count(), 'assigned session', 'assigned sessions'),
        ];
    }

    /** @return list<DeletionBlocker> */
    private function product(Product $product): array
    {
        return [
            new DeletionBlocker(DB::table('inventory_stocks')
                ->join('product_variants', 'product_variants.id', '=', 'inventory_stocks.product_variant_id')
                ->where('inventory_stocks.academy_id', $product->academy_id)
                ->where('product_variants.product_id', $product->id)
                ->where(fn (Builder $query) => $query->where('inventory_stocks.on_hand', '>', 0)->orWhere('inventory_stocks.reserved', '>', 0))
                ->count(), 'stock location with units on hand', 'stock locations with units on hand'),
            $this->history($this->rows($product, 'store_order_items', 'product_id', $product->id), 'order line', 'order lines'),
        ];
    }

    private function rows(Model $record, string $table, string $column, int $id): int
    {
        return DB::table($table)->where('academy_id', $record->getAttribute('academy_id'))->where($column, $id)->count();
    }

    private function history(int $count, string $singular, string $plural): DeletionBlocker
    {
        return new DeletionBlocker($count, $singular, $plural, permanent: true);
    }
}
