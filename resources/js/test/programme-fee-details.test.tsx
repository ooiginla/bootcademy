import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import ProgrammeFeeDetails from '@/pages/academies/programme-fee-details';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    useForm: (initial: Record<string, unknown>) => {
        const [data, setDataState] = useState(initial);
        return {
            data,
            setData: (key: string, value: unknown) =>
                setDataState((current) => ({ ...current, [key]: value })),
            errors: {},
            processing: false,
            post: vi.fn(),
            patch: vi.fn(),
        };
    },
}));

describe('Programme fee payment plans', () => {
    it('shows plan totals, repayment counts and an intuitive plan builder', () => {
        render(
            <ProgrammeFeeDetails
                offering={{
                    id: 5,
                    fee_name: 'Annual tuition',
                    programme_name: 'Under 17',
                    branch_name: null,
                    currency: 'NGN',
                    is_active: true,
                }}
                cycle={{
                    id: 2,
                    name: '2028 Academy Year',
                    type: 'fixed',
                    starts_on: '2028-01-01',
                    ends_on: '2028-12-31',
                    duration_value: null,
                    duration_unit: null,
                }}
                scope={{
                    academy_cycle_term_id: null,
                    term_name: null,
                    starts_on: null,
                    ends_on: null,
                    cohort_id: null,
                    cohort_name: null,
                }}
                base_fee_items={[
                    {
                        name: 'Annual programme fee',
                        description: 'One annual payment',
                        amount: '100000.00',
                    },
                ]}
                plans={[
                    {
                        id: 9,
                        name: 'Two payments',
                        description: 'Two equal repayments.',
                        sort_order: 20,
                        currency: 'NGN',
                        total: '110000.00',
                        is_default: true,
                        is_active: true,
                        is_legacy: false,
                        items: [
                            {
                                name: 'Annual programme fee',
                                description: '₦50,000 every six months',
                                amount: '100000.00',
                            },
                            {
                                name: 'Plan adjustment',
                                amount: '10000.00',
                            },
                        ],
                        installments: [
                            {
                                name: 'First payment',
                                amount: '55000.00',
                                due_on: '2028-01-15',
                                due_offset_days: '',
                            },
                            {
                                name: 'Second payment',
                                amount: '55000.00',
                                due_on: '2028-07-15',
                                due_offset_days: '',
                            },
                        ],
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('heading', {
                name: 'Annual tuition payment plans',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('₦110,000.00')).toBeInTheDocument();
        expect(screen.getByText('Number of repayments')).toBeInTheDocument();
        expect(screen.getByText('Two equal repayments.')).toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', { name: 'New payment plan' }),
        );
        expect(
            screen.getByRole('dialog', { name: 'Create payment plan' }),
        ).toBeInTheDocument();
        expect(screen.getAllByText('Contracted total').length).toBeGreaterThan(
            0,
        );
        expect(screen.getByText('Totals match')).toBeInTheDocument();
        expect(screen.getByLabelText('Fee item 1 name')).toHaveValue(
            'Annual programme fee',
        );
        expect(screen.getByLabelText('Fee item 1 description')).toHaveValue(
            'One annual payment',
        );
        expect(screen.getByLabelText('Display order')).toHaveValue(30);
    });
});
