<?php

namespace App\Notifications;

use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AcademyCommunicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $lines */
    public function __construct(private readonly NotificationDelivery $delivery, private readonly string $subject, private readonly array $lines)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject)->greeting('Hello '.($this->delivery->recipient_name ?: 'there').',');
        foreach ($this->lines as $line) {
            $message->line($line);
        }

        return $message;
    }

    public function delivery(): NotificationDelivery
    {
        return $this->delivery;
    }
}
