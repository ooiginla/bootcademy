<?php

use App\Academies\Actions\ReconcileReferralRewards;
use App\Academies\Actions\SendAcademyCommunication;
use App\Models\AcademyCommunicationSetting;
use App\Models\ReferralSetting;
use App\Payments\RequeryPendingPayments;
use App\Support\CalendarDays;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $disk = Storage::disk('local');
    foreach ($disk->allFiles('imports') as $path) {
        if ($disk->lastModified($path) < now()->subHours(25)->timestamp) {
            $disk->delete($path);
        }
    }
})->hourly()->name('delete-expired-player-imports')->withoutOverlapping();

Schedule::call(function (SendAcademyCommunication $send): void {
    AcademyCommunicationSetting::query()->with('academy')->chunkById(100, function ($settings) use ($send): void {
        foreach ($settings as $setting) {
            $academy = $setting->academy;
            $today = now($academy->timezone)->startOfDay();
            if ((int) $today->format('G') !== 8) {
                continue;
            }
            if ($setting->automatic_payment_reminders) {
                $academy->invoices()->whereIn('status', ['issued', 'partially_paid'])
                    ->whereRaw('total_minor > (paid_minor - sponsor_paid_minor) + written_off_minor + scholarship_concession_minor + sponsor_responsibility_minor')->with(['player.guardians', 'installments'])
                    ->chunkById(100, function ($invoices) use ($setting, $today, $academy, $send): void {
                        foreach ($invoices as $invoice) {
                            $installment = $invoice->installments->filter(fn ($item) => $item->amount_minor > ($item->paid_minor - $item->sponsor_paid_minor) + $item->written_off_minor + $item->scholarship_concession_minor + $item->sponsor_responsibility_minor)->sortBy('due_on')->first();
                            $dueOn = $installment?->due_on ?? $invoice->due_on;
                            if ($dueOn !== null) {
                                $days = CalendarDays::until($today, $dueOn);
                                if (in_array($days, $setting->payment_reminder_days ?? [], true)) {
                                    $send->invoice($academy, $invoice, $days);
                                }
                            }
                        }
                    });
            }
            if ($setting->automatic_renewal_reminders) {
                $academy->enrollments()->where('status', 'active')->whereNotNull('ends_on')->with(['player.guardians', 'programme'])
                    ->chunkById(100, function ($enrollments) use ($setting, $today, $academy, $send): void {
                        foreach ($enrollments as $enrollment) {
                            $days = CalendarDays::until($today, $enrollment->ends_on);
                            if (in_array($days, $setting->renewal_reminder_days ?? [], true)) {
                                $send->renewal($academy, $enrollment, $days);
                            }
                        }
                    });
            }
        }
    });
})->hourly()->name('academy-automated-reminders')->withoutOverlapping();

Schedule::call(function (ReconcileReferralRewards $reconcile): void {
    ReferralSetting::query()->where('is_enabled', true)->with('academy')->chunkById(100, function ($settings) use ($reconcile): void {
        foreach ($settings as $setting) {
            $reconcile->handle($setting->academy);
        }
    });
})->dailyAt('01:30')->name('academy-referral-reward-reconciliation')->withoutOverlapping();

// Paystack has no webhook for this app, so unfinished payments are confirmed by asking Paystack.
Schedule::call(fn (RequeryPendingPayments $requery) => $requery->handle())
    ->everyFiveMinutes()->name('paystack-requery-pending-payments')->withoutOverlapping();

Artisan::command('payments:requery', function (RequeryPendingPayments $requery): void {
    $totals = $requery->handle();
    $this->info("Checked {$totals['checked']}, verified {$totals['verified']}, expired {$totals['expired']}.");
})->purpose('Confirm unfinished Paystack payments with Paystack');
