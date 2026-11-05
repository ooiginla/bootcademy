import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import CycleDetailsPage from '@/pages/academies/cycle-details';

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

describe('Programme cycle fees', () => {
    it('shows currency-formatted application, programme and combined totals', () => {
        render(
            <CycleDetailsPage
                currency="NGN"
                programmes={[{ id: 1, name: 'Under 17' }]}
                branches={[{ id: 2, name: 'Gbagada' }]}
                cycle_fees={[
                    {
                        id: 9,
                        name: 'Medical fee',
                        category: 'medical',
                        amount: '25000.00',
                        currency: 'NGN',
                        is_compulsory: false,
                        applies_to_all_programmes: true,
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
                    offerings: [],
                }}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Add programme fees' }),
        );
        expect(screen.getByDisplayValue('Programme fee')).toBeInTheDocument();
        const amountInputs = screen.getAllByPlaceholderText('0.00');
        fireEvent.change(amountInputs[0], { target: { value: '5000' } });
        fireEvent.change(amountInputs[1], { target: { value: '150000' } });

        expect(amountInputs[0]).toHaveValue('5,000');
        expect(amountInputs[1]).toHaveValue('150,000');
        expect(screen.getByText('₦5,000.00')).toBeInTheDocument();
        expect(screen.getByText('₦150,000.00')).toBeInTheDocument();
        expect(screen.getByText('₦155,000.00')).toBeInTheDocument();
        expect(
            screen.getByText(/Full payment is always available/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'All branches (default)' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Gbagada' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Other bookable fees')).toBeInTheDocument();
        expect(screen.getByText('Medical fee')).toBeInTheDocument();
        expect(screen.getByText('₦25,000.00')).toBeInTheDocument();
        expect(screen.getByText('Optional')).toBeInTheDocument();
    });
});
