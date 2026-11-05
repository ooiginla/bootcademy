<?php

namespace App\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscription;

class AcademyEntitlements
{
    private ?AcademySubscription $subscription = null;

    public function __construct(private readonly Academy $academy) {}

    public static function for(Academy $academy): self
    {
        return new self($academy);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription()?->status === SubscriptionStatus::Active;
    }

    public function activePlayerLimit(): int
    {
        return $this->hasActiveSubscription()
            ? $this->subscription()?->plan->active_player_limit ?? 0
            : 0;
    }

    public function allows(string $featureKey): bool
    {
        if (! $this->hasActiveSubscription()) {
            return false;
        }

        return $this->subscription()?->plan->features
            ->firstWhere('feature_key', $featureKey)?->enabled === true;
    }

    public function featureLimit(string $featureKey): ?int
    {
        if (! $this->allows($featureKey)) {
            return null;
        }

        return $this->subscription()?->plan->features
            ->firstWhere('feature_key', $featureKey)?->limit;
    }

    private function subscription(): ?AcademySubscription
    {
        return $this->subscription ??= $this->academy->subscription()
            ->with('plan.features')
            ->first();
    }
}
