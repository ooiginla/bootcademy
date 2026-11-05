import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import ProgrammeDetails from '@/pages/academies/programme-details';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: {
            academy: {
                terminology: { singular: 'Student', plural: 'Students' },
            },
        },
    }),
    Link: ({
        children,
        href,
        ...props
    }: {
        children: ReactNode;
        href: string;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
}));

const branches = [
    { id: 1, name: 'Central Campus', is_active: true },
    { id: 2, name: 'Island Campus', is_active: true },
];
const programme = {
    id: 10,
    name: 'Under 17',
    code: 'U17',
    description: 'Advanced youth programme.',
    is_active: true,
    branches,
};
const members = {
    data: [
        {
            id: 3,
            player_id: 4,
            player_name: 'Amara Okafor',
            internal_identifier: 'LRN-4',
            player_status: 'active',
            branch_name: 'Central Campus',
            status: 'active',
            starts_on: '2026-10-28',
            ends_on: null,
        },
    ],
    current_page: 1,
    last_page: 1,
    from: 1,
    to: 1,
    total: 1,
    prev_page_url: null,
    next_page_url: null,
};

describe('Programme details', () => {
    it('shows basic details in the first tab', () => {
        render(
            <ProgrammeDetails
                programme={programme}
                members={members}
                branch_options={branches}
                selected_branch_id={null}
                active_tab="details"
                can_manage
            />,
        );
        expect(
            screen.getByRole('tab', { name: 'Basic details' }),
        ).toHaveAttribute('aria-selected', 'true');
        expect(
            screen.getByText('Advanced youth programme.'),
        ).toBeInTheDocument();
        expect(screen.getByText('Island Campus')).toBeInTheDocument();
    });

    it('shows programme members in a branch-filterable table', () => {
        render(
            <ProgrammeDetails
                programme={programme}
                members={members}
                branch_options={branches}
                selected_branch_id={1}
                active_tab="members"
                can_manage={false}
            />,
        );
        expect(
            screen.getByRole('tab', { name: 'Students (1)' }),
        ).toHaveAttribute('aria-selected', 'true');
        expect(screen.getByRole('combobox', { name: 'Branch' })).toHaveValue(
            '1',
        );
        expect(screen.getByRole('table')).toHaveTextContent('Amara Okafor');
        expect(screen.getByRole('table')).toHaveTextContent('Central Campus');
        expect(
            screen.queryByRole('button', { name: 'Edit' }),
        ).not.toBeInTheDocument();
    });
});
