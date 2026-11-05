<?php

namespace App\Notifications;

use App\Models\AcademyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AcademyInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AcademyInvitation $invitation,
        private readonly string $token,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to '.$this->invitation->academy->name)
            ->greeting('You have a new academy invitation')
            ->line($this->invitation->inviter->name.' invited you to join '.$this->invitation->academy->name.'.')
            ->line('Your role will be '.$this->invitation->academy->roleLabel($this->invitation->role).'.')
            ->action('View and accept invitation', route('academy.invites.show', $this->token))
            ->line('This private link expires '.$this->invitation->expires_at->diffForHumans().'. If you were not expecting it, you can ignore this email.');
    }
}
