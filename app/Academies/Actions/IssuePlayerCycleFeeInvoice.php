<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoicePurpose;
use App\Enums\AcademyInvoiceStatus;
use App\Models\Academy;
use App\Models\AcademyCycleFee;
use App\Models\AcademyInvoice;
use App\Models\Application;
use App\Models\Player;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssuePlayerCycleFeeInvoice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Academy $academy, Player $player, AcademyCycleFee $fee, User $actor, Request $request, ?Application $application = null): AcademyInvoice
    {
        return DB::transaction(function () use ($academy, $player, $fee, $actor, $request, $application): AcademyInvoice {
            abort_unless($player->academy_id === $academy->id && $fee->academy_id === $academy->id, 404);
            $enrollments = $player->enrollments()
                ->where('academy_cycle_id', $fee->academy_cycle_id)
                ->where('status', 'active');
            if (! $fee->applies_to_all_programmes) {
                $enrollments->whereIn('programme_id', $fee->programmes()->select('programmes.id'));
            }
            $enrollment = $enrollments->first();
            if ($enrollment === null) {
                throw ValidationException::withMessages(['fee_id' => 'This fee does not apply to any active programme enrollment for the player.']);
            }
            $existing = $academy->invoices()->where('player_id', $player->id)->where('academy_cycle_fee_id', $fee->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            do {
                $number = 'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
            } while ($academy->invoices()->where('number', $number)->exists());
            $invoice = AcademyInvoice::query()->create([
                'academy_id' => $academy->id, 'number' => $number, 'application_id' => $application?->id,
                'fee_name' => $fee->name,
                'player_id' => $player->id, 'academy_cycle_id' => $fee->academy_cycle_id,
                'academy_cycle_fee_id' => $fee->id, 'programme_cycle_id' => $enrollment->programme_cycle_id,
                'programme_id' => $enrollment->programme_id, 'branch_id' => $enrollment->branch_id,
                'purpose' => AcademyInvoicePurpose::Custom, 'status' => AcademyInvoiceStatus::Issued,
                'currency' => $fee->currency, 'total_minor' => $fee->amount_minor, 'paid_minor' => 0,
                'written_off_minor' => 0, 'issued_at' => now(), 'due_on' => $enrollment->ends_on,
                'created_by_user_id' => $actor->id,
            ]);
            $invoice->items()->create(['academy_id' => $academy->id, 'description' => $fee->name, 'amount_minor' => $fee->amount_minor, 'sort_order' => 0]);
            $invoice->installments()->create([
                'academy_id' => $academy->id, 'name' => $fee->name, 'amount_minor' => $fee->amount_minor,
                'paid_minor' => 0, 'written_off_minor' => 0, 'due_on' => $enrollment->ends_on,
                'due_offset_days' => null, 'sequence' => 1,
            ]);
            $this->audit->record('academy.invoice.manual_fee_issued', $actor, $academy, $invoice, ['cycle_fee_id' => $fee->id], $request);

            return $invoice;
        });
    }
}
