<?php

namespace App\Notifications;

use App\Models\StoreOrderPayment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoreOrderPaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly StoreOrderPayment $payment)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment->loadMissing(['academy', 'order']);
        $order = $payment->order;

        $message = (new MailMessage)
            ->subject('Store payment confirmed · '.$payment->academy->name.' · '.$order->number)
            ->greeting('Payment successful')
            ->line('Hello '.$order->customer_name.',')
            ->line('Your Paystack payment for store order '.$order->number.' has been verified.')
            ->line('Order amount: '.Money::format($payment->currency, Money::fromMinor($payment->amount_minor)));

        if ($payment->surcharge_minor > 0) {
            $message->line('Online processing fee: '.Money::format($payment->currency, Money::fromMinor($payment->surcharge_minor)))
                ->line('Total charged: '.Money::format($payment->currency, Money::fromMinor($payment->charged_amount_minor)));
        }

        return $message
            ->line('Paystack reference: '.$payment->reference)
            ->line('Keep this email as your receipt and quote the order number when contacting the academy.');
    }
}
