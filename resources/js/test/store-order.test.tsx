import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import StoreOrder from '@/pages/public/store-order';

vi.mock('@/components/public-site/public-site-shell', () => ({
    default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));
vi.mock('sonner', () => ({ toast: { error: vi.fn() } }));

const props = {
    academy: {
        name: 'Demo Academy',
        type: 'sports',
        logo_url: null,
        member_singular: 'Player',
        member_plural: 'Players',
        currency: 'NGN',
        theme: {
            primary: '#2563eb',
            primary_foreground: '#ffffff',
            secondary: '#f59e0b',
            secondary_foreground: '#111827',
        },
    },
    site: { subdomain: 'demo', theme_key: 'classic', sections: {} },
    base_url: '/sites/demo',
    order_token: 'private-token',
    payment_preference: 'paystack' as const,
    paystack_ready: true,
    bank_details: {
        bank_name: 'Example Bank',
        account_name: 'Demo Academy',
        account_number: '0123456789',
    },
    order: {
        number: 'ORD-001',
        customer_name: 'Parent Name',
        customer_email: 'parent@example.test',
        member_name: 'Player Name',
        currency: 'NGN',
        total: '12500.00',
        paid: '0.00',
        outstanding: '12500.00',
        processing_fee: '300.00',
        online_total: '12800.00',
        payment_status: 'unpaid',
        fulfillment_status: 'unfulfilled',
        delivery_method: 'pickup',
        branch: null,
        items: [
            {
                name: 'Training Jersey',
                variant: 'Medium',
                quantity: 1,
                unit_price: '12500.00',
            },
        ],
    },
};

describe('Public store order', () => {
    it('shows the student name and online or bank payment choices', () => {
        render(<StoreOrder {...props} />);

        expect(screen.getByText('Player Name')).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Pay with Paystack' }),
        ).toBeVisible();
        expect(screen.getByLabelText('Payment and receipt email')).toHaveValue(
            'parent@example.test',
        );
        expect(screen.getByText('0123456789')).toBeVisible();
        expect(screen.getByText('ORDER ORD-001')).toBeVisible();
    });

    it('explains why Paystack cannot proceed when the email is invalid', () => {
        render(
            <StoreOrder
                {...props}
                order={{ ...props.order, customer_email: 'invalid-email' }}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Pay with Paystack' }),
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Enter a valid payment email address before opening Paystack.',
        );
    });
});
