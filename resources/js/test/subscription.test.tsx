import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AcademySubscription from '@/pages/academies/subscription';
import type { PricedPlan } from '@/types/subscription';

const reload = vi.fn();
const loadPaystack = vi.fn(() => Promise.resolve());
const openPaystack = vi.fn((_code: string) => Promise.resolve('paid' as const));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { reload: () => reload() },
    usePage: () => ({
        props: {
            academy: {
                terminology: { singular: 'Player', plural: 'Players' },
            },
        },
    }),
}));
vi.mock('@/lib/paystack', () => ({
    loadPaystack: () => loadPaystack(),
    openPaystack: (code: string) => openPaystack(code),
}));

function plan(overrides: Partial<PricedPlan>): PricedPlan {
    const price = (amount: number) => ({
        amount_minor: amount,
        list_amount_minor: amount,
        fee_minor: 48224,
        total_minor: amount + 48224,
    });

    return {
        id: 1,
        code: 'essential',
        name: 'Essential',
        description: 'For small academies.',
        currency: 'NGN',
        is_recommended: false,
        active_player_limit: 75,
        branch_limit: 1,
        staff_account_limit: 5,
        migration_support: 'Self-service',
        support_level: 'Email',
        flags: { api_access: false, custom_domain: false },
        prices: {
            monthly: price(2_500_000),
            quarterly: price(7_125_000),
            annual: price(27_000_000),
        },
        has_custom_price: false,
        is_current: false,
        ...overrides,
    };
}

const baseProps = {
    current: null,
    access_ends_at: null,
    upcoming: [],
    plan_name: 'Essential',
    next_period_starts_at: '2026-11-18T10:00:00Z',
    can_pay: true,
    online_payment_available: true,
    plans: [
        plan({}),
        plan({
            id: 2,
            code: 'professional',
            name: 'Professional',
            is_recommended: true,
        }),
    ],
    comparison: {
        included: ['Attendance', 'Store and inventory'],
        flags: { api_access: 'API access', custom_domain: 'Custom domain' },
    },
    usage: { active_players: 10, branches: 1, staff_accounts: 2 },
    limits: { active_players: 75, branches: 1, staff_accounts: 5 },
    payments: [],
};

afterEach(() => {
    vi.restoreAllMocks();
    reload.mockReset();
});

describe('Academy subscription page', () => {
    it('asks a lapsed academy to choose a plan and shows prices for the chosen period', () => {
        render(<AcademySubscription {...baseProps} state="lapsed" />);

        expect(screen.getByRole('alert')).toHaveTextContent(
            /subscription has ended/i,
        );
        expect(
            screen.getByRole('heading', { name: 'Choose a plan' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Most popular')).toBeInTheDocument();
        expect(screen.getAllByText('₦270,000')).toHaveLength(2);

        fireEvent.click(screen.getByRole('radio', { name: 'Monthly' }));
        expect(screen.getAllByText('₦25,000')).toHaveLength(2);
        expect(
            screen.getAllByRole('button', {
                name: /Pay ₦25,482\.24 with Paystack/,
            }),
        ).toHaveLength(2);
        expect(screen.getAllByText('1 branch')).toHaveLength(2);
        expect(screen.getByText('Store and inventory')).toBeInTheDocument();
    });

    it('shows the current period and when it expires', () => {
        render(
            <AcademySubscription
                {...baseProps}
                state="active"
                access_ends_at="2026-12-18T10:00:00Z"
                current={{
                    id: 9,
                    kind: 'paid',
                    plan_name: 'Professional',
                    billing_interval: 'monthly',
                    billing_interval_label: 'Monthly',
                    starts_at: '2026-11-18T10:00:00Z',
                    ends_at: '2026-12-18T10:00:00Z',
                    note: null,
                }}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Professional' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Paid · Monthly')).toBeInTheDocument();
        expect(screen.getByText('Expires')).toBeInTheDocument();
        expect(screen.getAllByText('18 Dec 2026').length).toBeGreaterThan(0);
        expect(
            screen.getByRole('heading', { name: 'Renew or change plan' }),
        ).toBeInTheDocument();
        // Plans stay folded away while the academy is paid up.
        expect(screen.queryByText('Most popular')).not.toBeInTheDocument();
    });

    it('tells staff who cannot pay to ask the owner', () => {
        render(
            <AcademySubscription
                {...baseProps}
                state="lapsed"
                can_pay={false}
                plans={[]}
                usage={null}
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            /ask your academy owner/i,
        );
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('pays through Paystack and confirms with the server', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValueOnce(
                new Response(
                    JSON.stringify({
                        access_code: 'AC_1',
                        verify_url: '/verify/1',
                    }),
                ),
            )
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ status: 'verified' })),
            );
        render(<AcademySubscription {...baseProps} state="trialing" />);

        fireEvent.click(
            screen.getAllByRole('button', { name: /with Paystack/ })[1],
        );

        await waitFor(() =>
            expect(
                screen.getByText(/Your subscription is active/),
            ).toBeInTheDocument(),
        );
        expect(openPaystack).toHaveBeenCalledWith('AC_1');
        expect(JSON.parse(fetchMock.mock.calls[0][1]?.body as string)).toEqual({
            plan_id: 2,
            billing_interval: 'annual',
        });
        expect(fetchMock.mock.calls[1][0]).toBe('/verify/1');
        expect(reload).toHaveBeenCalled();
    });
});
