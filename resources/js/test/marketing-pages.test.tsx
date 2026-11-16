import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import MarketingPage from '@/pages/marketing/show';
import { marketingPages } from '@/pages/marketing/page-data';

vi.mock('@inertiajs/react', () => ({
    Head: ({ children }: { children: ReactNode }) => <>{children}</>,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={String(href)}>{children}</a>
    ),
    usePage: () => ({ props: { auth: { user: null } } }),
}));

describe('Bootcademy marketing pages', () => {
    it('defines a unique canonical path and substantial content for every page', () => {
        const definitions = Object.values(marketingPages);

        expect(new Set(definitions.map((page) => page.path)).size).toBe(
            definitions.length,
        );

        definitions.forEach((page) => {
            expect(page.title.length).toBeGreaterThan(20);
            expect(page.description.length).toBeGreaterThan(100);
            expect(page.outcomes).toHaveLength(4);
            expect(page.workflow).toHaveLength(3);
            expect(page.capabilities.length).toBeGreaterThanOrEqual(8);
            expect(page.faqs).toHaveLength(3);
        });
    });

    it('makes the free academy website offer clear on its landing page', () => {
        render(
            <MarketingPage
                page="academy-website"
                seo={{
                    canonical_url:
                        'https://bootcademy.com/features/academy-website',
                    image_url:
                        'https://bootcademy.com/images/bootcademy-academy-owner.jpg',
                }}
            />,
        );

        expect(
            screen.getAllByText('Free academy website included'),
        ).toHaveLength(2);
        expect(
            screen.getByRole('heading', {
                level: 1,
                name: /turn interest into applications/i,
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /start your academy workspace/i }),
        ).toBeInTheDocument();
    });
});
