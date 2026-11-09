import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import StoreOrderDetails from '@/pages/academies/store-order';

vi.mock('@inertiajs/react', () => ({ Head: () => null }));

const props = {
    order_academy: { name: 'Example Academy', currency: 'NGN' },
    order: {
        id: 10,
        number: 'ORD-1001',
        source: 'storefront',
        customer_name: 'Parent Customer',
        customer_email: 'parent@example.test',
        customer_phone: '08010000000',
        member_name: 'Student Name',
        collection_branch: 'Main Branch',
        delivery_method: 'pickup',
        delivery_address: null,
        notes: 'Saturday collection',
        currency: 'NGN',
        subtotal: '3000.00',
        discount: '0.00',
        delivery: '0.00',
        total: '3000.00',
        paid: '1000.00',
        outstanding: '2000.00',
        payment_status: 'partially_paid',
        payment_preference: 'bank_transfer',
        fulfillment_status: 'processing',
        placed_at: '2026-11-09T10:00:00Z',
        paid_at: null,
        fulfilled_at: null,
        cancelled_at: null,
        items: [
            {
                id: 1,
                name: 'Academy Jersey',
                variant: 'Medium',
                sku: 'JER-M',
                unit_price: '1000.00',
                quantity: 3,
                line_total: '3000.00',
                allocations: [{ branch: 'Main Branch', quantity: 3 }],
            },
        ],
        payments: [
            {
                id: 4,
                reference: 'PAY-001',
                external_reference: 'BANK-001',
                method: 'transfer',
                status: 'verified',
                amount: '1000.00',
                surcharge: '0.00',
                charged_amount: '1000.00',
                provider_fee: '0.00',
                received_on: '2026-11-09',
                verified_at: '2026-11-09T10:05:00Z',
                created_at: '2026-11-09T10:05:00Z',
                failure_reason: null,
                notes: null,
                recorded_by: 'Academy Owner',
            },
        ],
    },
};

describe('Store order details', () => {
    afterEach(() => vi.restoreAllMocks());

    it('shows the complete order, verified payment and outstanding balance', () => {
        render(<StoreOrderDetails {...props} />);

        expect(
            screen.getByRole('heading', { name: 'Order ORD-1001' }),
        ).toBeVisible();
        expect(screen.getByText('Student Name')).toBeVisible();
        expect(screen.getByText('Academy Jersey')).toBeVisible();
        expect(screen.getByText('Main Branch (3)')).toBeVisible();
        expect(screen.getByText('BANK-001')).toBeVisible();
        expect(screen.getByText('₦2,000.00')).toBeVisible();
    });

    it('prints the order from the details page', () => {
        const print = vi
            .spyOn(window, 'print')
            .mockImplementation(() => undefined);
        render(<StoreOrderDetails {...props} />);

        fireEvent.click(screen.getByRole('button', { name: /Print order/i }));
        expect(print).toHaveBeenCalledOnce();
    });
});
