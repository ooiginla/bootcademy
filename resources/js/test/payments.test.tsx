import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Payments from '@/pages/academies/payments';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({})}</form>
    ),
}));

describe('Academy payments', () => {
    it('lists payer, fee type, programme, cycle and formatted amount', () => {
        render(
            <Payments
                payments={{
                    data: [
                        {
                            id: 1,
                            reference: 'PAY-001',
                            external_reference: 'TRF-100',
                            received_on: '2026-10-29',
                            currency: 'NGN',
                            amount: '5000.00',
                            reversed: '0.00',
                            net: '5000.00',
                            adjustments_count: 0,
                            method: 'bank_transfer',
                            status: 'verified',
                            payer_name: 'Amara Okafor',
                            application_id: 4,
                            application_reference: 'APP-ABC123',
                            player_id: 9,
                            invoice_number: 'INV-001',
                            payment_type: 'application',
                            cycle_name: '2027 Academic Year',
                            programme_name: 'Under 17',
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
                cycles={[{ id: 5, name: '2027 Academic Year' }]}
                programmes={[{ id: 2, name: 'Under 17' }]}
                filters={{ cycle_id: null, programme_id: null, method: '' }}
                today="2026-11-05"
            />,
        );

        expect(screen.getByRole('table')).toHaveTextContent('Amara Okafor');
        expect(screen.getByRole('table')).toHaveTextContent('application');
        expect(screen.getByRole('table')).toHaveTextContent('Under 17');
        expect(screen.getByRole('table')).toHaveTextContent(
            '2027 Academic Year',
        );
        expect(screen.getByRole('table')).toHaveTextContent('₦5,000.00');
        expect(
            screen.getByRole('link', { name: 'Amara Okafor' }),
        ).toHaveAttribute('href', '/app/players/9');
    });
});
