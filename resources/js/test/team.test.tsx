import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AcademyInvitations from '@/pages/academies/invitations';
import AcademyTeam from '@/pages/academies/team';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Form: ({
        children,
        action,
        method,
    }: {
        children: ((value: object) => ReactNode) | ReactNode;
        action: string;
        method: string;
    }) => (
        <form action={action} method={method}>
            {typeof children === 'function'
                ? children({ processing: false, errors: {} })
                : children}
        </form>
    ),
    router: { post },
}));

const roles = [
    { value: 'owner_admin', label: 'Academy Owner / Admin' },
    { value: 'coach', label: 'Coach / Instructor' },
];

describe('Academy team', () => {
    it('shows the team list first and protects the current admin row', () => {
        render(
            <AcademyTeam
                roles={roles}
                branches={[]}
                team={[
                    {
                        id: 1,
                        user_id: 1,
                        name: 'Ada Owner',
                        email: 'ada@example.test',
                        role: 'owner_admin',
                        role_label: 'Academy Owner / Admin',
                        status: 'active',
                        is_current_user: true,
                        branches: [],
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Team and roles' }),
        ).toBeInTheDocument();
        expect(screen.getByText(/your access/i)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Invite member' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Create account' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen
                .getByText('Academy Owner / Admin')
                .closest('[data-slot="badge"]'),
        ).toHaveClass('rounded-full', 'bg-violet-50');

        fireEvent.change(
            screen.getByRole('searchbox', { name: 'Filter team by name' }),
            { target: { value: 'Missing Person' } },
        );
        expect(
            screen.getByText('No matching team members'),
        ).toBeInTheDocument();
        expect(screen.queryByText('Ada Owner')).not.toBeInTheDocument();
    });

    it('opens invitation and account creation in dialogs', () => {
        const props = {
            roles,
            branches: [],
            team: [
                {
                    id: 1,
                    user_id: 1,
                    name: 'Ada Owner',
                    email: 'ada@example.test',
                    role: 'owner_admin',
                    role_label: 'Academy Owner / Admin',
                    status: 'active' as const,
                    is_current_user: true,
                    branches: [],
                },
            ],
        };
        const first = render(<AcademyTeam {...props} />);

        fireEvent.click(screen.getByRole('button', { name: 'Invite member' }));
        expect(
            screen.getByRole('dialog', { name: 'Invite team member' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Send invitation' }),
        ).toBeInTheDocument();

        first.unmount();
        render(<AcademyTeam {...props} />);
        fireEvent.click(screen.getByRole('button', { name: 'Create account' }));
        expect(
            screen.getByRole('dialog', { name: 'Create active account' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Create member' }),
        ).toBeInTheDocument();
    });

    it('opens role and status editing in a dialog', () => {
        render(
            <AcademyTeam
                roles={roles}
                branches={[{ id: 5, name: 'Central Campus', is_active: true }]}
                team={[
                    {
                        id: 2,
                        user_id: 2,
                        name: 'Chidi Coach',
                        email: 'chidi@example.test',
                        role: 'coach',
                        role_label: 'Coach / Instructor',
                        status: 'active',
                        is_current_user: false,
                        branches: [
                            { id: 5, name: 'Central Campus', is_active: true },
                        ],
                    },
                ]}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Edit' }));
        expect(
            screen.getByRole('dialog', { name: 'Edit Chidi Coach' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save changes' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('checkbox', { name: 'Central Campus' }),
        ).toBeChecked();
    });

    it('accepts an in-app academy invitation', () => {
        render(
            <AcademyInvitations
                invitations={[
                    {
                        id: 22,
                        academy_name: 'Harmony Academy',
                        academy_type: 'Music',
                        role_label: 'Coach / Instructor',
                    },
                ]}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Accept invitation' }),
        );
        expect(post).toHaveBeenCalledWith('/app/invitations/22/accept');
    });
});
