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

function plan(
    code: string,
    name: string,
    monthly: number,
    limits: [number, number, number],
    extra: Partial<{ is_recommended: boolean; api: boolean }> = {},
) {
    return {
        id: limits[0],
        code,
        name,
        description: `${name} description`,
        currency: 'NGN',
        is_recommended: extra.is_recommended ?? false,
        active_player_limit: limits[0],
        branch_limit: limits[1],
        staff_account_limit: limits[2],
        migration_support: 'Guided',
        support_level: 'Email',
        flags: { api_access: extra.api ?? false, custom_domain: false },
        prices: {
            monthly: monthly,
            quarterly: monthly * 3 * 0.95,
            annual: monthly * 12 * 0.9,
        },
    };
}

const plans = [
    plan('essential', 'Essential', 2_500_000, [75, 1, 5]),
    plan('professional', 'Professional', 5_000_000, [250, 3, 20], {
        is_recommended: true,
    }),
    plan('enterprise', 'Enterprise', 10_000_000, [750, 10, 60], { api: true }),
];

const props = {
    plans,
    comparison: {
        included: ['Attendance', 'Store and inventory'],
        flags: { api_access: 'API access', custom_domain: 'Custom domain' },
    },
    trial: { days: 30, plan_name: 'Enterprise' },
    seo: {
        canonical_url: 'https://bootcademy.com/',
        image_url: 'https://bootcademy.com/images/bootcademy-academy-owner.jpg',
    },
};

describe('Bootcademy marketing homepage', () => {
    it('presents product positioning, active pricing and original imagery', () => {
        render(<Welcome {...props} />);

        expect(
            screen.getByRole('heading', {
                name: /Academy management software that runs the whole academy\./i,
            }),
        ).toBeVisible();
        expect(screen.getByText('₦25,000')).toBeVisible();
        expect(screen.getByText('₦50,000')).toBeVisible();
        expect(screen.getByText('₦100,000')).toBeVisible();
        expect(
            screen.getByText('Start with a free 30-day Enterprise trial'),
        ).toBeVisible();
        expect(screen.getByText('Most popular')).toBeVisible();
        expect(screen.getByText('1 branch')).toBeVisible();
        expect(screen.getByText('60 staff accounts')).toBeVisible();
        expect(screen.getAllByText('API access')).toHaveLength(3);
        expect(screen.getByText('Store and inventory')).toBeVisible();
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

    it('switches pricing to the quarterly and annual values stored for each plan', () => {
        render(<Welcome {...props} />);

        fireEvent.click(
            screen.getByRole('radio', { name: /Quarterly save 5%/i }),
        );
        expect(screen.getByText('₦71,250')).toBeVisible();
        expect(screen.getByText('₦142,500')).toBeVisible();

        fireEvent.click(
            screen.getByRole('radio', { name: /Annual save 10%/i }),
        );
        expect(screen.getByText('₦270,000')).toBeVisible();
        expect(screen.getByText('₦540,000')).toBeVisible();
        expect(screen.getByText('₦1,080,000')).toBeVisible();
    });
});
