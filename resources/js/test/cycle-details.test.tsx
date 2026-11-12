import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import CycleDetailsPage from '@/pages/academies/cycle-details';

vi.mock('@inertiajs/react', () => ({
    Form: () => null,
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

describe('Programme cycle fees', () => {
    it('lists each pricing scope and keeps prices out of the programme fee form', () => {
        render(
            <CycleDetailsPage
                currency="NGN"
                programmes={[{ id: 1, name: 'Under 17' }]}
                branches={[{ id: 2, name: 'Gbagada' }]}
                cohorts={[]}
                cycle_fees={[
                    {
                        id: 9,
                        name: 'Medical fee',
                        category: 'medical',
                        amount: '25000.00',
                        currency: 'NGN',
                        is_compulsory: false,
                        applies_to_all_programmes: true,
                        academy_cycle_term_id: null,
                        term_name: null,
                        cohort_id: null,
                        cohort_name: null,
                        is_active: true,
                        programmes: [],
                    },
                    {
                        id: 10,
                        name: 'Term materials',
                        category: 'books',
                        amount: '10000.00',
                        currency: 'NGN',
                        is_compulsory: true,
                        applies_to_all_programmes: true,
                        academy_cycle_term_id: 5,
                        term_name: 'Term 1',
                        cohort_id: null,
                        cohort_name: null,
                        is_active: true,
                        programmes: [],
                    },
                ]}
                cycle={{
                    id: 1,
                    name: '2027 Academic Year',
                    type: 'fixed',
                    type_label: 'Fixed dates',
                    starts_on: '2027-01-01',
                    ends_on: '2027-12-31',
                    duration_value: null,
                    duration_unit: null,
                    status: 'active',
                    programme_cycles_count: 0,
                    enrollments_count: 0,
                    terms_count: 1,
                    offerings: [
                        {
                            id: 20,
                            programme_id: 1,
                            fee_name: 'Programme fee',
                            branch_id: null,
                            branch_name: null,
                            programme_name: 'Under 17',
                            currency: 'NGN',
                            application_fee: '5000.00',
                            fee_total: '180000.00',
                            payment_mode: 'one_off',
                            is_active: true,
                            fee_items: [],
                            scopes: [
                                {
                                    academy_cycle_term_id: null,
                                    term_name: null,
                                    cohort_id: null,
                                    cohort_name: null,
                                    plans_count: 2,
                                    public_plans_count: 1,
                                    default_plan_name: 'Session registration',
                                    default_total: '30000.00',
                                },
                                {
                                    academy_cycle_term_id: 5,
                                    term_name: 'Term 1',
                                    cohort_id: null,
                                    cohort_name: null,
                                    plans_count: 1,
                                    public_plans_count: 1,
                                    default_plan_name: 'Term tuition',
                                    default_total: '150000.00',
                                },
                            ],
                            installments: [],
                        },
                    ],
                    terms: [
                        {
                            id: 5,
                            name: 'Term 1',
                            starts_on: '2027-01-01',
                            ends_on: '2027-04-30',
                            sort_order: 1,
                            is_default: true,
                            is_active: true,
                        },
                    ],
                }}
            />,
        );

        expect(
            screen
                .getAllByRole('link', { name: 'Details' })
                .some(
                    (link) =>
                        link.getAttribute('href') ===
                        '/app/finance/cycles/1/programmes/20?term_id=5',
                ),
        ).toBe(true);
        expect(screen.getByText('₦30,000.00')).toBeInTheDocument();
        expect(screen.getByText('₦150,000.00')).toBeInTheDocument();
        expect(screen.getAllByText('1 on website')).toHaveLength(2);

        fireEvent.click(
            screen.getByRole('button', { name: 'Add programme fees' }),
        );
        expect(screen.getByDisplayValue('Programme fee')).toBeInTheDocument();
        const amountInputs = screen.getAllByPlaceholderText('0.00');
        fireEvent.change(amountInputs[0], { target: { value: '5000' } });
        expect(amountInputs[0]).toHaveValue('5,000');
        expect(
            screen.queryByText('Standard one-off payment plan'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText('Fee 1 description'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'After saving, you will add the payment plans that set this fee’s prices.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'All branches (default)' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Gbagada' }),
        ).toBeInTheDocument();
        expect(screen.getByText('General cycle fees')).toBeInTheDocument();
        expect(screen.getByText('Programme cycle fees')).toBeInTheDocument();
        expect(screen.getByText('Session registration')).toBeInTheDocument();
        expect(screen.getByText('Programme term fees')).toBeInTheDocument();
        expect(screen.getByText('Term tuition')).toBeInTheDocument();
        expect(screen.getByText('Term materials')).toBeInTheDocument();
        expect(screen.getByText('Medical fee')).toBeInTheDocument();
        expect(screen.getByText('₦25,000.00')).toBeInTheDocument();
        expect(screen.queryByText('₦155,000.00')).not.toBeInTheDocument();
        expect(screen.getByText('Optional')).toBeInTheDocument();
    });

    it('uses cycle-level fees without term controls for a rolling cycle', () => {
        render(
            <CycleDetailsPage
                currency="NGN"
                programmes={[{ id: 1, name: 'Under 17' }]}
                branches={[]}
                cohorts={[]}
                cycle_fees={[]}
                cycle={{
                    id: 2,
                    name: 'Six-week membership',
                    type: 'rolling',
                    type_label: 'Rolling duration',
                    starts_on: null,
                    ends_on: null,
                    duration_value: 6,
                    duration_unit: 'weeks',
                    status: 'active',
                    programme_cycles_count: 0,
                    enrollments_count: 0,
                    terms_count: 0,
                    offerings: [],
                    terms: [],
                }}
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'Add term' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Cycle fees' }),
        ).toBeInTheDocument();
        expect(screen.queryByText('Terms')).not.toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', { name: 'Add programme fees' }),
        );
        expect(
            screen.queryByRole('combobox', { name: 'Fee 1 term' }),
        ).not.toBeInTheDocument();
    });
});
