<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\NotificationDelivery;
use App\Notifications\AcademyCommunicationNotification;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;

class SendAcademyCommunication
{
    public function invoice(Academy $academy, AcademyInvoice $invoice, int $days): int
    {
        abort_unless($invoice->academy_id === $academy->id, 404);
        $invoice->loadMissing('player.guardians');
        $dueOn = $invoice->installments()
            ->whereRaw('amount_minor > (paid_minor - sponsor_paid_minor) + written_off_minor + scholarship_concession_minor + sponsor_responsibility_minor')
            ->orderBy('due_on')->value('due_on') ?? $invoice->due_on;
        $lines = [
            'This is a payment reminder from '.$academy->name.'.',
            'Invoice: '.$invoice->number.' · '.($invoice->fee_name ?? 'Academy fee'),
            'Outstanding: '.Money::format($invoice->currency, Money::fromMinor($invoice->personalBalanceMinor())),
            'Due date: '.($dueOn ? date('j F Y', strtotime((string) $dueOn)) : 'Contact the academy'),
            'Please contact the academy if you have already paid or need assistance.',
        ];

        return $this->toPlayerGuardians($academy, $invoice->player, 'payment_reminder', 'invoice-'.$invoice->id.'-days-'.$days.'-'.now()->toDateString(), 'Payment reminder · '.$academy->name, $lines, invoice: $invoice);
    }

    public function renewal(Academy $academy, Enrollment $enrollment, int $days): int
    {
        abort_unless($enrollment->academy_id === $academy->id, 404);
        $enrollment->loadMissing(['player.guardians', 'programme']);
        $lines = [
            $enrollment->player->first_name.'\'s '.$enrollment->programme->name.' enrolment is approaching renewal.',
            'Current enrolment ends: '.$enrollment->ends_on?->format('j F Y'),
            'Please contact '.$academy->name.' to confirm the next programme, cycle and applicable fees.',
        ];

        return $this->toPlayerGuardians($academy, $enrollment->player, 'renewal_reminder', 'enrollment-'.$enrollment->id.'-days-'.$days.'-'.now()->toDateString(), 'Upcoming renewal · '.$academy->name, $lines, enrollment: $enrollment);
    }

    /** @param iterable<int, array{email:string,name:string}> $recipients */
    public function announcement(Academy $academy, Announcement $announcement, iterable $recipients): int
    {
        $count = 0;
        foreach ($recipients as $recipient) {
            $count += $this->queue($academy, 'announcement', 'announcement-'.$announcement->id.'-'.sha1(mb_strtolower($recipient['email'])), $recipient, $announcement->title.' · '.$academy->name, [$announcement->body], announcement: $announcement) ? 1 : 0;
        }

        return $count;
    }

    private function toPlayerGuardians(Academy $academy, $player, string $type, string $key, string $subject, array $lines, ?AcademyInvoice $invoice = null, ?Enrollment $enrollment = null): int
    {
        if ($player === null) {
            return 0;
        }
        $count = 0;
        foreach ($player->guardians->whereNotNull('email')->unique(fn ($guardian) => mb_strtolower($guardian->email)) as $guardian) {
            $recipient = ['email' => $guardian->email, 'name' => trim($guardian->first_name.' '.$guardian->last_name)];
            $count += $this->queue($academy, $type, $key.'-'.sha1(mb_strtolower($guardian->email)), $recipient, $subject, $lines, invoice: $invoice, enrollment: $enrollment) ? 1 : 0;
        }

        return $count;
    }

    private function queue(Academy $academy, string $type, string $key, array $recipient, string $subject, array $lines, ?Announcement $announcement = null, ?AcademyInvoice $invoice = null, ?Enrollment $enrollment = null): bool
    {
        $delivery = NotificationDelivery::query()->firstOrCreate(
            ['academy_id' => $academy->id, 'idempotency_key' => $key],
            ['announcement_id' => $announcement?->id, 'academy_invoice_id' => $invoice?->id, 'enrollment_id' => $enrollment?->id, 'type' => $type, 'channel' => 'mail', 'recipient_email' => $recipient['email'], 'recipient_name' => $recipient['name'], 'status' => 'queued', 'queued_at' => now()],
        );
        if (! $delivery->wasRecentlyCreated) {
            return false;
        }
        Notification::route('mail', $recipient['email'])->notify(new AcademyCommunicationNotification($delivery, $subject, $lines));

        return true;
    }
}
