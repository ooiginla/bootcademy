<?php

namespace App\Platform\Subscriptions;

/**
 * Launch plan values from docs/pricing_context.md. They are seed data: the platform admin
 * edits them afterwards on the plan screen, and nothing reads this class at runtime.
 */
class DefaultPlanCatalog
{
    /** @return list<array<string, mixed>> */
    public static function plans(): array
    {
        return [
            [
                'code' => 'essential',
                'name' => 'Essential',
                'description' => 'For small academies moving away from spreadsheets.',
                'monthly_price_minor' => 2_500_000,
                'quarterly_price_minor' => 7_125_000,
                'annual_price_minor' => 27_000_000,
                'active_player_limit' => 75,
                'branch_limit' => 1,
                'staff_account_limit' => 5,
                'is_recommended' => false,
                'migration_support' => 'Self-service',
                'support_level' => 'Email',
                'sort_order' => 10,
                'features' => ['api_access' => false, 'custom_domain' => false],
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'description' => 'For growing academies running several programmes.',
                'monthly_price_minor' => 5_000_000,
                'quarterly_price_minor' => 14_250_000,
                'annual_price_minor' => 54_000_000,
                'active_player_limit' => 250,
                'branch_limit' => 3,
                'staff_account_limit' => 20,
                'is_recommended' => true,
                'migration_support' => 'Guided',
                'support_level' => 'Priority email',
                'sort_order' => 20,
                'features' => ['api_access' => false, 'custom_domain' => false],
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For larger, multi-branch organisations.',
                'monthly_price_minor' => 10_000_000,
                'quarterly_price_minor' => 28_500_000,
                'annual_price_minor' => 108_000_000,
                'active_player_limit' => 750,
                'branch_limit' => 10,
                'staff_account_limit' => 60,
                'is_recommended' => false,
                'migration_support' => 'Included',
                'support_level' => 'Priority onboarding and support',
                'sort_order' => 30,
                'features' => ['api_access' => true, 'custom_domain' => true],
            ],
        ];
    }
}
