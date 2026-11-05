import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Dashboard from '@/pages/dashboard';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({
        children,
        action,
    }: {
        children: (value: object) => ReactNode;
        action: string;
    }) => <form action={action}>{children({})}</form>,
    usePage: () => ({
        props: {
            auth: { user: { name: 'Ada Okafor' } },
            academy: {
                name: 'Harmony Arts Academy',
                terminology: { singular: 'Player', plural: 'Players' },
            },
        },
    }),
}));

const props = {
    operational: {
        currency: 'NGN',
        active_players: 20,
        new_enrollments: 3,
        sessions: 8,
        attendance_marked: 40,
        attendance_rate: 87.5,
        billed: '500000.00',
        outstanding: '120000.00',
        collected: '380000.00',
        reversed: '10000.00',
        net_collected: '370000.00',
        expenses: '70000.00',
        net_cash: '300000.00',
        aging: {
            not_due: '120000.00',
            days_1_30: '0.00',
            days_31_60: '0.00',
            days_61_plus: '0.00',
        },
        branch_metrics: [
            {
                id: 1,
                name: 'Central',
                active_players: 20,
                collected: '370000.00',
                expenses: '70000.00',
            },
        ],
    },
    branches: [{ id: 1, name: 'Central' }],
    filters: {
        date_from: '2026-11-01',
        date_to: '2026-11-05',
        branch_id: null,
    },
    today: '2026-11-05',
};

describe('Operational dashboard', () => {
    it('shows filter-aware operations, finance and branch metrics', () => {
        render(<Dashboard {...props} />);
        expect(
            screen.getByRole('heading', { name: 'Welcome, Ada' }),
        ).toBeVisible();
        expect(screen.getAllByText('Active players')).toHaveLength(2);
        expect(screen.getByText('87.5%')).toBeVisible();
        expect(screen.getAllByText('₦370,000.00')).toHaveLength(2);
        expect(screen.getAllByText('Central')).toHaveLength(2);
        expect(
            screen
                .getByRole('button', { name: 'Apply filters' })
                .closest('form'),
        ).toHaveAttribute('action', '/app/dashboard');
    });

    it('does not expose academy-wide finance metrics to non-admin roles', () => {
        render(<Dashboard {...props} operational={null} />);
        expect(screen.getByText('Your workspace is ready')).toBeVisible();
        expect(screen.queryByText('Net collected')).not.toBeInTheDocument();
    });
});
