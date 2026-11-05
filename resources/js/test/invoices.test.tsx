import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Invoices from '@/pages/academies/invoices';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({})}</form>
    ),
}));

describe('Academy invoice register', () => {
    it('shows filtered summaries, invoice details and cancelled status styling', () => {
        render(
            <Invoices
                invoices={{
                    data: [
                        {
                            id: 1,
                            number: 'INV-001',
                            fee_name: 'Insurance',
                            fee_type: 'custom',
                            status: 'void',
                            issued_on: '2026-10-31',
                            currency: 'NGN',
                            billed: '25000.00',
                            paid: '0.00',
                            owed: '0.00',
                            written_off: '0.00',
                            payer_name: 'Amara Okafor',
                            player_id: 9,
                            application_id: null,
                            application_reference: null,
                            cycle_name: '2026 Active Cycle',
                            programme_name: 'Under 17',
                            branch_name: 'Gbagada',
                        },
                    ],
                    current_page: 1,
                    last_page: 1,
                    from: 1,
                    to: 1,
                    total: 1,
                    prev_page_url: null,
                    next_page_url: null,
                }}
                summary={{
                    currency: 'NGN',
                    billed: '150000.00',
                    paid: '100000.00',
                    owed: '40000.00',
                    written_off: '10000.00',
                }}
                cycles={[{ id: 5, name: '2026 Active Cycle' }]}
                programmes={[{ id: 2, name: 'Under 17' }]}
                branches={[{ id: 3, name: 'Gbagada' }]}
                fee_names={['Insurance']}
                filters={{
                    cycle_id: 5,
                    programme_id: null,
                    branch_id: null,
                    fee_name: '',
                    status: '',
                    date_from: null,
                    date_to: null,
                }}
            />,
        );

        expect(screen.queryByLabelText('Cycle')).not.toBeInTheDocument();
        const filtersButton = screen.getByRole('button', { name: /Filters/ });
        expect(filtersButton).toHaveAttribute('aria-expanded', 'false');
        expect(filtersButton).toHaveTextContent('1');
        fireEvent.click(filtersButton);
        expect(filtersButton).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByLabelText('Cycle')).toHaveValue('5');
        expect(
            screen.getByText('Total billed').parentElement,
        ).toHaveTextContent('₦150,000.00');
        expect(screen.getByText('Total paid').parentElement).toHaveTextContent(
            '₦100,000.00',
        );
        expect(screen.getByText('Total owed').parentElement).toHaveTextContent(
            '₦40,000.00',
        );
        expect(
            screen.getByText('Total written off').parentElement,
        ).toHaveTextContent('₦10,000.00');
        expect(
            screen.getByText('Total billed').closest('[data-slot="card"]'),
        ).toHaveClass('bg-blue-50');
        expect(
            screen.getByText('Total paid').closest('[data-slot="card"]'),
        ).toHaveClass('bg-emerald-50');
        expect(
            screen.getByText('Total owed').closest('[data-slot="card"]'),
        ).toHaveClass('bg-amber-50');
        expect(
            screen.getByText('Total written off').closest('[data-slot="card"]'),
        ).toHaveClass('bg-violet-50');
        expect(screen.getByRole('table')).toHaveTextContent('Insurance');
        expect(screen.getByRole('table')).toHaveTextContent('custom fee');
        expect(screen.getByRole('table')).toHaveTextContent('Under 17');
        expect(screen.getByRole('table')).toHaveTextContent('Gbagada');
        expect(
            screen.getByRole('link', { name: 'Amara Okafor' }),
        ).toHaveAttribute('href', '/app/players/9');
        expect(
            screen.getByRole('table').querySelector('[data-slot="badge"]'),
        ).toHaveClass('bg-destructive');
        fireEvent.click(filtersButton);
        expect(screen.queryByLabelText('Cycle')).not.toBeInTheDocument();
    });
});
