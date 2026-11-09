import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Store from '@/pages/academies/store';

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');
    return {
        Head: () => null,
        router: { patch: vi.fn() },
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, update] = React.useState(initial);
            return {
                data,
                errors: {},
                processing: false,
                setData: (key: keyof T | T, value?: unknown) =>
                    update((current) =>
                        typeof key === 'object'
                            ? key
                            : { ...current, [key]: value },
                    ),
                reset: () => update(initial),
                post: vi.fn(),
                patch: vi.fn(),
                transform: vi.fn(),
            };
        },
    };
});

const product = {
    id: 1,
    name: 'Training Jersey',
    sku: 'JER-1',
    description: 'Official jersey',
    status: 'active',
    currency: 'NGN',
    price: '12000.00',
    category_id: 1,
    category: 'Uniforms',
    track_inventory: true,
    allow_backorder: false,
    inventory_total: 10,
    tag_ids: [],
    image_url: null,
    variants: [
        {
            id: 2,
            name: 'Medium',
            sku: 'JER-M',
            attributes: {},
            price: null,
            is_active: true,
            stocks: [
                {
                    branch_id: 3,
                    branch: 'Main Branch',
                    on_hand: 10,
                    reserved: 2,
                    available: 8,
                },
            ],
        },
    ],
};

const props = {
    currency: 'NGN',
    can_manage_catalog: true,
    products: [product],
    categories: [
        {
            id: 1,
            name: 'Uniforms',
            description: null,
            products_count: 1,
        },
    ],
    tags: [],
    branches: [
        { id: 3, name: 'Main Branch' },
        { id: 4, name: 'Second Branch' },
    ],
    orders: [],
    summary: {
        products: 1,
        stock_units: 10,
        unpaid_orders: 0,
        sales: '0.00',
    },
};

describe('Store administration', () => {
    it('links each order to its full details page', () => {
        render(
            <Store
                {...props}
                active_tab="orders"
                orders={[
                    {
                        id: 44,
                        number: 'ORD-0044',
                        source: 'storefront',
                        customer_name: 'Store Customer',
                        customer_email: 'customer@example.test',
                        customer_phone: '08010000000',
                        member_name: null,
                        branch: 'Main Branch',
                        currency: 'NGN',
                        total: '12000.00',
                        paid: '0.00',
                        payment_status: 'unpaid',
                        payment_preference: 'pay_later',
                        fulfillment_status: 'unfulfilled',
                        placed_at: '2026-11-09T10:00:00Z',
                        items: [],
                    },
                ]}
            />,
        );

        expect(screen.getByRole('link', { name: 'ORD-0044' })).toHaveAttribute(
            'href',
            '/app/store/orders/44',
        );
        expect(screen.getByRole('link', { name: 'View' })).toHaveAttribute(
            'href',
            '/app/store/orders/44',
        );
    });

    it('collects the product image during product creation and explains backorders', () => {
        render(<Store {...props} active_tab="products" />);
        fireEvent.click(screen.getByRole('button', { name: 'Add product' }));
        expect(screen.getByLabelText('Product image')).toHaveAttribute(
            'type',
            'file',
        );
        expect(
            screen.getByText(
                /Accept orders when available stock is insufficient/i,
            ),
        ).toBeVisible();
    });

    it('can add inventory for a product option even when it has no existing row', () => {
        render(<Store {...props} active_tab="inventory" />);

        fireEvent.click(screen.getByRole('button', { name: 'Add inventory' }));

        expect(screen.getByText('Add inventory stock')).toBeVisible();
        expect(screen.getByText('Training Jersey · Medium')).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Add inventory' }),
        ).toBeVisible();
    });

    it('uses explicit add or remove stock controls with structured reasons', () => {
        render(<Store {...props} active_tab="inventory" />);
        expect(screen.getByText('Main Branch')).toBeVisible();
        expect(screen.queryByText('Second Branch')).not.toBeInTheDocument();
        expect(
            screen.queryByText('All branches (combined)'),
        ).not.toBeInTheDocument();
        fireEvent.click(screen.getAllByRole('button', { name: 'Adjust' })[0]);
        expect(screen.getByText('Add stock')).toBeInTheDocument();
        expect(screen.getByText('Remove stock')).toBeInTheDocument();
        expect(screen.getByText('Damaged item')).toBeInTheDocument();
        expect(screen.getByText('Other')).toBeInTheDocument();
    });

    it('shows the combined inventory total and branch breakdown on products', () => {
        render(<Store {...props} active_tab="products" />);

        expect(screen.getAllByText('10')).toHaveLength(2);
        fireEvent.click(
            screen.getByRole('button', {
                name: 'View inventory details for Training Jersey: 10 units',
            }),
        );

        expect(
            screen.getByText('Inventory details · Training Jersey'),
        ).toBeVisible();
        expect(
            screen.getByText('Total units across all branches'),
        ).toBeVisible();
        expect(screen.getByText('Main Branch')).toBeVisible();
        expect(screen.queryByText('Second Branch')).not.toBeInTheDocument();
    });

    it('offers a branch-to-branch stock transfer dialog', () => {
        render(<Store {...props} active_tab="inventory" />);
        fireEvent.click(
            screen.getAllByRole('button', { name: /Transfer$/ })[0],
        );
        expect(screen.getByText('From location')).toBeVisible();
        expect(screen.getByText('To location')).toBeVisible();
        expect(
            screen.getByText(/Reserved units cannot be transferred/i),
        ).toBeVisible();
    });
});
