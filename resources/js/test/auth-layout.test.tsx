import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AuthSimpleLayout from '@/layouts/auth/auth-simple-layout';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...props
    }: {
        children: ReactNode;
        href: string;
        'aria-label'?: string;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

vi.mock('@/routes', () => ({
    home: () => '/',
}));

describe('Bootcademy authentication layout', () => {
    it('applies the standard brand theme to every auth flow', () => {
        render(
            <AuthSimpleLayout
                title="Reset password"
                description="Choose a new password for your account."
            >
                <form>
                    <input aria-label="Password" />
                    <button type="submit">Continue</button>
                </form>
            </AuthSimpleLayout>,
        );

        const shell = screen.getByTestId('bootcademy-auth-shell');

        expect(shell).toHaveStyle({
            '--primary': '#FF6900',
            '--ring': '#0878D1',
        });
        expect(
            screen.getByRole('heading', { name: 'Reset password' }),
        ).toBeVisible();
        expect(screen.getAllByLabelText('Bootcademy home')).toHaveLength(2);
        expect(
            screen.getByText('Every academy. One platform.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Continue' })).toBeVisible();
    });
});
