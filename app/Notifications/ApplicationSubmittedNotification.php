<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Application $application)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->application->loadMissing([
            'academy', 'programme', 'programmeCycle.cycle', 'programmeCycle.branch',
        ]);
        $academy = $application->academy;
        $applicant = trim($application->first_name.' '.$application->middle_name.' '.$application->last_name);

        $message = (new MailMessage)
            ->subject('Application received · '.$academy->name)
            ->greeting('Application successfully submitted')
            ->line('Hello '.$applicant.',')
            ->line($academy->name.' has received your application.')
            ->line('Application reference: '.$application->reference)
            ->line('Programme: '.($application->programme?->name ?? 'Not specified'))
            ->line('Cycle: '.($application->programmeCycle?->cycle?->name ?? 'Not specified'))
            ->line('Location: '.($application->programmeCycle?->branch?->name ?? 'All locations'))
            ->line('Submitted: '.$application->created_at?->timezone($academy->timezone)->format('j F Y, g:i A'));

        if ($application->guardian_first_name !== null) {
            $message->line('Guardian/contact: '.trim($application->guardian_first_name.' '.$application->guardian_last_name));
        }

        return $message
            ->line('Payment is handled separately and does not affect the successful submission of this application.')
            ->line('Keep your application reference for future correspondence.');
    }
}
