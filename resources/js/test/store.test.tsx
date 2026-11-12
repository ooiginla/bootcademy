import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { describe, expect, it, vi } from 'vitest';
import Store from '@/pages/public/store';

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');
    return {
        usePage: () => ({ props: { recaptcha_site_key: null } }),
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setDataState] = React.useState(initial);
            return {
                data,
                errors: {},
                processing: false,
                setData: (key: keyof T, value: unknown) =>
                    setDataState((current) => ({
                        ...current,
                        [key]: value,
                    })),
                transform: vi.fn(),
                post: (_url: string, options?: { onSuccess?: () => void }) =>
                    options?.onSuccess?.(),
                reset: () => setDataState(initial),
            };
        },
    };
});
vi.mock('@/components/public-site/public-site-shell', () => ({
    default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const props = {
    academy: {
        name: 'Demo Academy',
        type: 'sports',
        logo_url: null,
        member_singular: 'Student',
        member_plural: 'Students',
        currency: 'NGN',
        theme: {
            primary: '#2563eb',
            primary_foreground: '#ffffff',
            secondary: '#f59e0b',
            secondary_foreground: '#111827',
        },
    },
    site: {
        subdomain: 'demo',
        theme_key: 'classic',
        sections: {
            store: {
                enabled: true,
                title: 'Academy shop',
                intro: 'Official items',
            },
        },
    },
    base_url: '/sites/demo',
    categories: [{ id: 1, name: 'Uniforms', slug: 'uniforms' }],
    locations: [],
    bank_details: { bank_name: null, account_name: null, account_number: null },
    paystack_ready: true,
    products: [
        {
            id: 4,
            name: 'Training Jersey',
            description: 'Official jersey',
            category: 'Uniforms',
            tags: ['New'],
            currency: 'NGN',
            price: '12500.00',
            image_url: null,
            variants: [
                {
                    id: 8,
                    name: 'Medium',
                    price: '12500.00',
                    attributes: { size: 'M' },
                    track_inventory: true,
                    availability: { '0': 5 },
                },
            ],
        },
    ],
};

describe('Public academy store', () => {
    it('shows server-provided prices and adds a product option to the cart', () => {
        render(<Store {...props} />);
        expect(screen.getByText('Academy shop')).toBeVisible();
        expect(screen.getByText('₦12,500.00')).toBeVisible();
        fireEvent.click(screen.getByRole('button', { name: /Add Medium/i }));
        expect(toast.success).toHaveBeenCalledWith(
            'Training Jersey added to cart.',
        );
        expect(screen.getByText('Complete your purchase')).toBeVisible();
        expect(screen.getAllByText('Training Jersey')).toHaveLength(2);
        expect(screen.getByText('Details')).toBeVisible();
        expect(screen.getByText('Notes')).toBeVisible();
        expect(screen.getByText('Payment')).toBeVisible();
        fireEvent.click(screen.getByRole('button', { name: 'Checkout' }));
        expect(
            screen.getByText('Customer and fulfilment details'),
        ).toBeVisible();
    });

    it('disables an inventory-tracked option when no units are available', () => {
        render(
            <Store
                {...props}
                products={[
                    {
                        ...props.products[0],
                        variants: [
                            {
                                ...props.products[0].variants[0],
                                availability: { '0': 0 },
                            },
                        ],
                    },
                ]}
            />,
        );
        expect(
            screen.getByRole('button', { name: 'Out of stock · Medium' }),
        ).toBeDisabled();
    });

    it('clears the cart after a successful staged checkout', () => {
        render(<Store {...props} />);
        fireEvent.click(screen.getByRole('button', { name: /Add Medium/i }));
        fireEvent.click(screen.getByRole('button', { name: 'Checkout' }));

        const details = screen.getAllByRole('textbox');
        fireEvent.change(details[0], { target: { value: 'Parent Name' } });
        fireEvent.change(details[1], {
            target: { value: 'parent@example.test' },
        });
        fireEvent.change(details[2], { target: { value: '08010000000' } });
        fireEvent.click(
            screen.getByRole('button', { name: 'Continue to notes' }),
        );
        expect(screen.getByText('Order notes')).toBeVisible();
        fireEvent.click(
            screen.getByRole('button', { name: 'Continue to payment' }),
        );
        fireEvent.click(
            screen.getByRole('button', {
                name: 'Place order and continue to Paystack',
            }),
        );

        expect(screen.getByRole('button', { name: 'Cart (0)' })).toBeVisible();
        expect(screen.queryByText('Complete your purchase')).toBeNull();
    });

    it('shows a clear error instead of silently stopping checkout', () => {
        render(<Store {...props} />);
        fireEvent.click(screen.getByRole('button', { name: /Add Medium/i }));
        fireEvent.click(screen.getByRole('button', { name: 'Checkout' }));
        fireEvent.click(
            screen.getByRole('button', { name: 'Continue to notes' }),
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Enter your name, a valid email and phone number before continuing.',
        );
        expect(toast.error).toHaveBeenCalledWith(
            'Enter your name, a valid email and phone number before continuing.',
        );
    });
});
