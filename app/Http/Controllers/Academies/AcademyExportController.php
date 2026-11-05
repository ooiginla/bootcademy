<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Http\Controllers\Controller;
use App\Models\AcademyExpense;
use App\Models\AcademyInvoice;
use App\Models\AcademyPayment;
use App\Models\AttendanceRecord;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademyExportController extends Controller
{
    public function payments(Request $request, AcademyContext $context, AuditLogger $audit): StreamedResponse
    {
        $this->authorize($context);
        $rows = $context->academy()->payments()->with(['player:id,first_name,middle_name,last_name', 'application:id,first_name,middle_name,last_name', 'allocations.invoice:id,number,branch_id', 'allocations.invoice.branch:id,name'])->orderBy('received_on')->cursor();

        return $this->csv('payments.csv', ['Date', 'Reference', 'Payer', 'Method', 'External reference', 'Original amount minor', 'Reversed minor', 'Net amount minor', 'Currency', 'Invoices', 'Branches'], function () use ($rows) {
            foreach ($rows as $payment) {
                /** @var AcademyPayment $payment */
                $person = $payment->player ?? $payment->application;
                yield [$payment->received_on->toDateString(), $payment->reference, collect([$person?->first_name, $person?->middle_name, $person?->last_name])->filter()->implode(' '), $payment->method->value, $payment->external_reference, $payment->amount_minor, $payment->reversed_minor, $payment->amount_minor - $payment->reversed_minor, $payment->currency, $payment->allocations->pluck('invoice.number')->unique()->implode('|'), $payment->allocations->pluck('invoice.branch.name')->filter()->unique()->implode('|')];
            }
        }, $audit, $request, $context, 'payments');
    }

    public function invoices(Request $request, AcademyContext $context, AuditLogger $audit): StreamedResponse
    {
        $this->authorize($context);
        $rows = $context->academy()->invoices()->with(['player:id,first_name,middle_name,last_name', 'application:id,first_name,middle_name,last_name', 'cycle:id,name', 'programme:id,name', 'branch:id,name'])->orderBy('issued_at')->cursor();

        return $this->csv('invoices.csv', ['Issued', 'Invoice', 'Payer', 'Fee', 'Status', 'Cycle', 'Programme', 'Branch', 'Billed minor', 'Paid minor', 'Written off minor', 'Owed minor', 'Currency'], function () use ($rows) {
            foreach ($rows as $invoice) {
                /** @var AcademyInvoice $invoice */
                $person = $invoice->player ?? $invoice->application;
                yield [$invoice->issued_at->toDateString(), $invoice->number, collect([$person?->first_name, $person?->middle_name, $person?->last_name])->filter()->implode(' '), $invoice->fee_name, $invoice->status->value, $invoice->cycle?->name, $invoice->programme?->name, $invoice->branch?->name, $invoice->total_minor, $invoice->paid_minor, $invoice->written_off_minor, max(0, $invoice->balanceMinor()), $invoice->currency];
            }
        }, $audit, $request, $context, 'invoices');
    }

    public function expenses(Request $request, AcademyContext $context, AuditLogger $audit): StreamedResponse
    {
        $this->authorize($context);
        $rows = $context->academy()->expenses()->with(['branch:id,name', 'cycle:id,name'])->orderBy('incurred_on')->cursor();

        return $this->csv('expenses.csv', ['Date', 'Category', 'Description', 'Vendor', 'Reference', 'Branch', 'Cycle', 'Amount minor', 'Currency', 'Source'], function () use ($rows) {
            foreach ($rows as $expense) {
                /** @var AcademyExpense $expense */
                yield [$expense->incurred_on->toDateString(), $expense->category, $expense->description, $expense->vendor, $expense->reference, $expense->branch?->name, $expense->cycle?->name, $expense->amount_minor, $expense->currency, $expense->academy_invoice_write_off_id ? 'write_off' : 'manual'];
            }
        }, $audit, $request, $context, 'expenses');
    }

    public function attendance(Request $request, AcademyContext $context, AuditLogger $audit): StreamedResponse
    {
        $this->authorize($context);
        $rows = AttendanceRecord::query()->where('attendance_records.academy_id', $context->id())->with(['player:id,first_name,middle_name,last_name,internal_identifier', 'session:id,title,starts_at,programme_id', 'session.programme:id,name', 'session.branches:id,name'])->orderBy('training_session_id')->cursor();

        return $this->csv('attendance.csv', ['Session date', 'Session', 'Programme', 'Branches', 'Student ID', 'Student', 'Status', 'Notes'], function () use ($rows, $context) {
            foreach ($rows as $record) {
                /** @var AttendanceRecord $record */
                yield [$record->session->starts_at->setTimezone($context->academy()->timezone)->toDateString(), $record->session->title, $record->session->programme->name, $record->session->branches->pluck('name')->implode('|'), $record->player->internal_identifier, collect([$record->player->first_name, $record->player->middle_name, $record->player->last_name])->filter()->implode(' '), $record->status, $record->notes];
            }
        }, $audit, $request, $context, 'attendance');
    }

    private function authorize(AcademyContext $context): void
    {
        abort_unless($context->canManageFinance(), 403);
    }

    /** @param list<string> $headers */
    private function csv(string $filename, array $headers, callable $rows, AuditLogger $audit, Request $request, AcademyContext $context, string $type): StreamedResponse
    {
        $audit->record("academy.export.{$type}", $request->user(), $context->academy(), null, [], $request);

        return response()->streamDownload(function () use ($headers, $rows): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $headers);
            foreach ($rows() as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
