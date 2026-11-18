<?php

namespace App\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscription;
use App\Models\AcademySubscriptionPeriod;
use App\Models\Plan;
use App\Models\PlatformSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What an academy may use right now.
 *
 * Access comes from subscription periods (trial, paid or granted). An academy has access while a
 * period covers the current time and the platform has not suspended it. Once access lapses the
 * academy falls back to the post-trial plan (Essential by default) for display and limits, and the
 * staff app is limited to the subscription page until it pays.
 */
class AcademyEntitlements
{
    private bool $loaded = false;

    private ?AcademySubscription $subscription = null;

    /** @var Collection<int, AcademySubscriptionPeriod> */
    private Collection $periods;

    public function __construct(private readonly Academy $academy)
    {
        $this->periods = new Collection;
    }

    public static function for(Academy $academy): self
    {
        return new self($academy);
    }

    public function subscription(): ?AcademySubscription
    {
        $this->load();

        return $this->subscription;
    }

    public function isSuspended(): bool
    {
        return $this->subscription()?->status === SubscriptionStatus::Suspended;
    }

    /** The period in force now: the one that started most recently among those covering now. */
    public function currentPeriod(): ?AcademySubscriptionPeriod
    {
        $this->load();
        $now = now();

        return $this->periods
            ->filter(fn (AcademySubscriptionPeriod $period): bool => $period->starts_at->lte($now) && $period->ends_at->gt($now))
            ->sortByDesc(fn (AcademySubscriptionPeriod $period): int => $period->starts_at->getTimestamp())
            ->first();
    }

    public function hasAccess(): bool
    {
        return $this->subscription() !== null && ! $this->isSuspended() && $this->currentPeriod() !== null;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->hasAccess();
    }

    /** suspended, trialing, active or lapsed. */
    public function state(): string
    {
        if ($this->isSuspended()) {
            return 'suspended';
        }
        $current = $this->currentPeriod();
        if ($current === null) {
            return 'lapsed';
        }

        return $current->kind === 'trial' ? 'trialing' : 'active';
    }

    /** The plan in force, or the post-trial plan once access has lapsed. */
    public function plan(): ?Plan
    {
        $current = $this->currentPeriod();
        if ($current !== null) {
            return $current->plan;
        }
        $settings = PlatformSetting::current();

        return ($settings->post_trial_plan_id ? Plan::query()->with('features')->find($settings->post_trial_plan_id) : null)
            ?? $this->subscription()?->plan;
    }

    /** When access ends, following back-to-back periods (for example a renewal queued after this one). */
    public function accessEndsAt(): ?CarbonInterface
    {
        $current = $this->currentPeriod();
        if ($current === null) {
            return null;
        }
        $end = $current->ends_at;
        do {
            $next = $this->periods->first(fn (AcademySubscriptionPeriod $period): bool => $period->starts_at->lte($end) && $period->ends_at->gt($end));
            if ($next !== null) {
                $end = $next->ends_at;
            }
        } while ($next !== null);

        return $end;
    }

    /** The latest end of any paid or granted period, which is where a new paid period starts. */
    public function paidThrough(): ?CarbonInterface
    {
        $this->load();

        return $this->periods
            ->filter(fn (AcademySubscriptionPeriod $period): bool => $period->kind !== 'trial')
            ->max(fn (AcademySubscriptionPeriod $period): CarbonInterface => $period->ends_at);
    }

    /** @return Collection<int, AcademySubscriptionPeriod> */
    public function upcomingPeriods(): Collection
    {
        $this->load();

        return $this->periods->filter(fn (AcademySubscriptionPeriod $period): bool => $period->starts_at->gt(now()))
            ->sortBy(fn (AcademySubscriptionPeriod $period): int => $period->starts_at->getTimestamp())
            ->values();
    }

    public function activePlayerLimit(): int
    {
        return $this->hasAccess() ? (int) $this->plan()?->active_player_limit : 0;
    }

    public function allows(string $featureKey): bool
    {
        if (! $this->hasAccess()) {
            return false;
        }

        return $this->plan()?->features->firstWhere('feature_key', $featureKey)?->enabled === true;
    }

    public function featureLimit(string $featureKey): ?int
    {
        if (! $this->allows($featureKey)) {
            return null;
        }

        return $this->plan()?->features->firstWhere('feature_key', $featureKey)?->limit;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        $this->subscription = $this->academy->subscription()->with('plan.features')->first();
        if ($this->subscription !== null) {
            $this->periods = $this->subscription->periods()
                ->with('plan.features')
                ->where('ends_at', '>', now())
                ->get();
        }
    }
}
