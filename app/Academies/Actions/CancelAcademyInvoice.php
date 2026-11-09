<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoiceStatus;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelAcademyInvoice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Academy $academy, AcademyInvoice $invoice, User $actor, string $reason, Request $request): void
    {
        DB::transaction(function () use ($academy, $invoice, $actor, $reason, $request): void {
            $invoice = AcademyInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->academy_id === $academy->id, 404);
            if ($invoice->paid_minor !== 0 || $invoice->written_off_minor !== 0
                || $invoice->scholarship_concession_minor !== 0 || $invoice->sponsor_responsibility_minor !== 0
                || $invoice->status !== AcademyInvoiceStatus::Issued) {
                throw ValidationException::withMessages(['reason' => 'Only an unpaid, unadjusted issued invoice can be cancelled.']);
            }
            $invoice->update([
                'status' => AcademyInvoiceStatus::Void, 'cancellation_reason' => trim($reason),
                'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->id,
            ]);
            $this->audit->record('academy.invoice.cancelled', $actor, $academy, $invoice, ['reason' => trim($reason)], $request);
        });
    }
}
