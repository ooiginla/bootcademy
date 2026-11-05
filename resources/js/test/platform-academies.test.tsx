import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import PlatformAcademies from '@/pages/platform/academies/index';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Form: ({ children }: { children: ReactNode }) => <form>{children}</form>,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
}));

describe('Platform academy directory', () => {
    it('shows the server-backed academy name filter and clear action', () => {
        render(
            <PlatformAcademies
                filters={{ search: 'Harmony' }}
                academies={{
                    data: [],
                    current_page: 1,
                    last_page: 1,
                    prev_page_url: null,
                    next_page_url: null,
                }}
            />,
        );

        expect(
            screen.getByRole('searchbox', {
                name: 'Filter academies by name',
            }),
        ).toHaveValue('Harmony');
        expect(screen.getByRole('link', { name: 'Clear' })).toHaveAttribute(
            'href',
            '/platform/academies',
        );
        expect(screen.getByText('No matching academies')).toBeInTheDocument();
    });
});
