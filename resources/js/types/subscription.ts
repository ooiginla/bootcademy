export type BillingInterval = 'monthly' | 'quarterly' | 'annual';

export type SubscriptionPeriod = {
    id: number;
    kind: 'trial' | 'paid' | 'granted';
    plan_name: string;
    billing_interval: BillingInterval | null;
    billing_interval_label: string | null;
    starts_at: string;
    ends_at: string;
    note: string | null;
};

export type PlanPrice = {
    amount_minor: number;
    list_amount_minor: number;
    fee_minor: number;
    total_minor: number;
};

export type PlanDetails = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    currency: string;
    is_recommended: boolean;
    active_player_limit: number;
    branch_limit: number | null;
    staff_account_limit: number | null;
    migration_support: string | null;
    support_level: string | null;
    flags: Record<string, boolean>;
};

export type PricedPlan = PlanDetails & {
    prices: Record<BillingInterval, PlanPrice>;
    has_custom_price: boolean;
    is_current: boolean;
};

export type PlanComparison = {
    included: string[];
    flags: Record<string, string>;
};

export type SubscriptionPaymentRow = {
    id: number;
    reference: string;
    plan_name: string;
    billing_interval_label: string;
    currency: string;
    amount_minor: number;
    surcharge_minor: number;
    platform_fee_minor: number;
    charged_amount_minor: number;
    status: string;
    created_at: string | null;
    verified_at: string | null;
    period_starts_at: string | null;
    period_ends_at: string | null;
};

export const intervalLabels: Record<BillingInterval, string> = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    annual: 'Annual',
};

export const intervalMonths: Record<BillingInterval, number> = {
    monthly: 1,
    quarterly: 3,
    annual: 12,
};

export const paymentStatusLabels: Record<string, string> = {
    pending: 'Pending',
    verified: 'Paid',
    failed: 'Failed',
    expired: 'Expired',
    review_required: 'Needs review',
};

export function formatDate(value: string | null): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-NG', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function minorToMoney(currency: string, minor: number): string {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency,
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: minor % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(minor / 100);
}

export function limitLabel(limit: number | null, noun: string): string {
    return limit === null
        ? `Unlimited ${noun}`
        : `${limit.toLocaleString('en-NG')} ${noun}`;
}
