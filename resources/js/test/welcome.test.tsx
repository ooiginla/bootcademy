import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Welcome from '@/pages/welcome';

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children: ReactNode }) => <>{children}</>,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    usePage: () => ({ props: { auth: { user: null } } }),
}));

vi.mock('@/routes', () => ({
    dashboard: () => '/app/dashboard',
    login: () => '/login',
    register: () => '/register',
}));

const plans = [
    {
        code: 'essential',
        name: 'Essential',
        currency: 'NGN',
        monthly_price: '25000.00',
        annual_price: '270000.00',
        active_player_limit: 100,
    },
    {
        code: 'professional',
        name: 'Professional',
        currency: 'NGN',
        monthly_price: '45000.00',
        annual_price: '486000.00',
        active_player_limit: 300,
    },
    {
        code: 'enterprise',
        name: 'Enterprise',
        currency: 'NGN',
        monthly_price: '85000.00',
        annual_price: '918000.00',
        active_player_limit: 1000,
    },
];

describe('Bootcademy marketing homepage', () => {
    it('presents product positioning, active pricing and original imagery', () => {
        render(
            <Welcome
                plans={plans}
                seo={{
                    canonical_url: 'https://bootcademy.com/',
                    image_url:
                        'https://bootcademy.com/images/bootcademy-academy-owner.jpg',
                }}
            />,
        );

        expect(
            screen.getByRole('heading', {
                name: /Run your academy\. Grow with clarity\./i,
            }),
        ).toBeVisible();
        expect(screen.getByText('₦25,000')).toBeVisible();
        expect(screen.getByText('₦45,000')).toBeVisible();
        expect(screen.getByText('₦85,000')).toBeVisible();
        expect(
            screen.getByAltText(
                'Academy owner coordinating sports and learning programmes with Bootcademy',
            ),
        ).toHaveAttribute('src', '/images/bootcademy-academy-owner.jpg');
        expect(
            document.querySelector('meta[name="description"]'),
        ).toHaveAttribute(
            'content',
            expect.stringContaining('academy management software'),
        );
    });

    it('switches pricing to the annual values stored for each plan', () => {
        render(
            <Welcome
                plans={plans}
                seo={{
                    canonical_url: 'https://bootcademy.com/',
                    image_url:
                        'https://bootcademy.com/images/bootcademy-academy-owner.jpg',
                }}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: /Annual save 10%/i }),
        );

        expect(screen.getByText('₦270,000')).toBeVisible();
        expect(screen.getByText('₦486,000')).toBeVisible();
        expect(screen.getByText('₦918,000')).toBeVisible();
    });
});
