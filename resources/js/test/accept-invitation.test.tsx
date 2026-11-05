import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AcceptInvitation from '@/pages/academies/accept-invitation';
import ChangeTemporaryPassword from '@/pages/auth/change-temporary-password';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
}));

const invitation = {
    academy_name: 'Harmony Academy',
    academy_type: 'Music',
    email: 'new.member@example.test',
    role_label: 'Coach / Instructor',
    expires_at: '2026-11-04T10:00:00Z',
};

describe('Accept academy invitation', () => {
    it('collects personal information for a new invited user', () => {
        render(
            <AcceptInvitation
                token="private-token"
                invitation={invitation}
                existing_account={false}
                authenticated_as_invitee={false}
                authenticated_as_different_user={false}
                passwordRules="minlength:8;"
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Join Harmony Academy' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Full name')).toBeRequired();
        expect(screen.getByLabelText('Create password')).toBeRequired();
        expect(
            screen.getByRole('button', {
                name: 'Create account and join team',
            }),
        ).toBeInTheDocument();
    });

    it('requires an existing invited user to sign in', () => {
        render(
            <AcceptInvitation
                token="private-token"
                invitation={invitation}
                existing_account
                authenticated_as_invitee={false}
                authenticated_as_different_user={false}
                passwordRules="minlength:8;"
            />,
        );

        expect(
            screen.getByRole('link', { name: /sign in to continue/i }),
        ).toHaveAttribute('href', '/login');
        expect(
            screen.queryByLabelText('Create password'),
        ).not.toBeInTheDocument();
    });
});

describe('Temporary password replacement', () => {
    it('requires the admin-created user to choose a personal password', () => {
        render(<ChangeTemporaryPassword passwordRules="minlength:8;" />);

        expect(
            screen.getByRole('heading', {
                name: 'Create your personal password',
            }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('New password')).toBeRequired();
        expect(
            screen.getByRole('button', { name: 'Save personal password' }),
        ).toBeInTheDocument();
    });
});
