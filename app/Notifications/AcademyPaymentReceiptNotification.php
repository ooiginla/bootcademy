<?php

namespace App\Notifications;

use App\Enums\AcademyInvoiceStatus;
use App\Models\AcademyPayment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AcademyPaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AcademyPayment $payment)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment->loadMissing([
            'application.invoices', 'player', 'allocations.invoice', 'allocations.installment',
        ]);
        $academy = $payment->academy;
        $applicant = $payment->application === null
            ? trim(($payment->player?->first_name ?? '').' '.($payment->player?->middle_name ?? '').' '.($payment->player?->last_name ?? ''))
            : trim($payment->application->first_name.' '.$payment->application->middle_name.' '.$payment->application->last_name);
        $message = (new MailMessage)
            ->subject('Payment confirmed · '.$academy->name.' · '.$payment->reference)
            ->greeting('Payment successful')
            ->line('Hello '.($applicant ?: 'Applicant').',')
            ->line($academy->name.' has received and independently verified your Paystack payment.')
            ->line('Receipt reference: '.$payment->reference)
            ->line('Paystack reference: '.($payment->external_reference ?? 'Not provided'))
            ->line('Payment date: '.$payment->received_on->format('j F Y'))
            ->line('Payment method: Paystack')
            ->line('Payment status: Verified');

        if ($payment->application !== null) {
            $message->line('Application reference: '.$payment->application->reference);
        }

        $message->line('Academy fees paid: '.Money::format($payment->currency, Money::fromMinor($payment->amount_minor)));
        if ($payment->customer_fee_minor > 0) {
            $message->line('Online processing fee: '.Money::format($payment->currency, Money::fromMinor($payment->customer_fee_minor)))
                ->line('Total charged: '.Money::format($payment->currency, Money::fromMinor($payment->gross_amount_minor ?? $payment->amount_minor)));
        }
        foreach ($payment->allocations as $allocation) {
            $label = $allocation->invoice->fee_name ?? 'Academy fee';
            if ($allocation->installment !== null) {
                $label .= ' · '.$allocation->installment->name;
            }
            $message->line($label.' ('.$allocation->invoice->number.') — '.Money::format($payment->currency, Money::fromMinor($allocation->amount_minor)));
        }
        if ($payment->unallocated_minor > 0) {
            $message->line('Unapplied credit requiring academy review: '.Money::format($payment->currency, Money::fromMinor($payment->unallocated_minor)));
        }

        $outstanding = (int) ($payment->application?->invoices
            ->where('status', '!=', AcademyInvoiceStatus::Void)
            ->sum(fn ($invoice): int => max(0, $invoice->balanceMinor())) ?? 0);
        $message->line('Remaining application balance: '.Money::format($payment->currency, Money::fromMinor($outstanding)));

        return $message
            ->line('This email is your payment receipt. Keep it for your records.')
            ->line('If any detail is incorrect, contact '.$academy->name.' and quote the receipt reference above.');
    }
}
