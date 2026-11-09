<?php

namespace App\Providers;

use App\Academies\AcademyContext;
use App\Notifications\AcademyCommunicationNotification;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\PaystackPaymentProvider;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AcademyContext::class, fn (): AcademyContext => new AcademyContext);
        $this->app->bind(PaymentProvider::class, PaystackPaymentProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->notification instanceof AcademyCommunicationNotification) {
                $event->notification->delivery()->update(['status' => 'sent', 'sent_at' => now(), 'failure_reason' => null]);
            }
        });
        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if ($event->notification instanceof AcademyCommunicationNotification) {
                $event->notification->delivery()->update(['status' => 'failed', 'failure_reason' => 'The mail provider rejected this delivery.']);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
