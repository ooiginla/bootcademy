import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AcademyPlayers from '@/pages/academies/players-directory';

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

const guardian = {
    id: 1,
    first_name: 'Ada',
    last_name: 'Okafor',
    email: 'ada@example.com',
    phone: '08012345678',
    whatsapp_number: '08012345678',
    has_signature: false,
};
const player = {
    id: 2,
    internal_identifier: 'LRN-002',
    first_name: 'Amara',
    middle_name: null,
    last_name: 'Okafor',
    date_of_birth: '2014-05-02',
    gender: null,
    phone: null,
    whatsapp_number: null,
    home_address: null,
    city: null,
    state_region: null,
    country: null,
    status: 'active',
    guardians: [
        {
            id: 1,
            name: 'Ada Okafor',
            email: 'ada@example.com',
            phone: '08012345678',
            relationship: 'Mother',
        },
    ],
    enrollments: [
        {
            id: 3,
            programme_id: 4,
            programme_name: 'Under 13',
            branch_id: 5,
            branch_name: 'Central',
            status: 'active',
            starts_on: '2026-10-28',
            ends_on: null,
        },
    ],
    custom_fields: {},
};
const programmes = [{ id: 4, name: 'Under 13' }];
const branches = [{ id: 5, name: 'Central' }];
const customFields = [
    {
        id: 7,
        key: 'playing_position',
        label: 'Playing position',
        type: 'select' as const,
        options: ['Forward', 'Defender'],
        is_required: false,
        is_sensitive: false,
        is_active: true,
        sort_order: 0,
    },
];
const page = <T,>(data: T[]) => ({
    data,
    current_page: 1,
    last_page: 1,
    from: data.length ? 1 : null,
    to: data.length || null,
    total: data.length,
    prev_page_url: null,
    next_page_url: null,
});
const filters = { players_search: '', guardians_search: '' };

afterEach(() => vi.unstubAllGlobals());

describe('Academy player directories', () => {
    it('shows students in a table and keeps create/edit actions in dialogs', () => {
        render(
            <AcademyPlayers
                players={page([player])}
                guardians={page([guardian])}
                programmes={programmes}
                branches={branches}
                custom_fields={customFields}
                active_tab="students"
                filters={filters}
            />,
        );
        expect(screen.getByRole('table')).toHaveTextContent('Amara Okafor');
        expect(
            screen.getByRole('columnheader', { name: 'Branch' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('columnheader', { name: 'Guardians' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('table')).toHaveTextContent('Central');
        expect(screen.getByRole('table')).not.toHaveTextContent('Ada Okafor');
        expect(screen.getByRole('table')).toHaveTextContent('Under 13');
        fireEvent.click(screen.getByRole('button', { name: 'Add student' }));
        expect(
            screen.getByRole('dialog', { name: 'Add student' }),
        ).toBeInTheDocument();
    });

    it('shows a clear fallback when a student has no active branch', () => {
        render(
            <AcademyPlayers
                players={page([{ ...player, enrollments: [] }])}
                guardians={page([guardian])}
                programmes={programmes}
                branches={branches}
                custom_fields={customFields}
                active_tab="students"
                filters={filters}
            />,
        );

        expect(screen.getByRole('table')).toHaveTextContent(
            'No branch assigned',
        );
    });

    it('shows the guardian directory as a separate table tab', () => {
        render(
            <AcademyPlayers
                players={page([player])}
                guardians={page([guardian])}
                programmes={programmes}
                branches={branches}
                custom_fields={customFields}
                active_tab="guardians"
                filters={filters}
            />,
        );
        expect(
            screen.getByRole('tab', { name: 'Guardian directory' }),
        ).toHaveAttribute('aria-selected', 'true');
        expect(screen.getByRole('table')).toHaveTextContent('Ada Okafor');
        expect(
            screen.getByRole('searchbox', {
                name: 'Search guardians by name, email or phone',
            }),
        ).toBeInTheDocument();
    });

    it('shows custom fields in their own table tab', () => {
        render(
            <AcademyPlayers
                players={page([])}
                guardians={page([])}
                programmes={programmes}
                branches={branches}
                custom_fields={customFields}
                active_tab="fields"
                filters={filters}
            />,
        );
        expect(
            screen.getByRole('tab', { name: 'Custom fields' }),
        ).toHaveAttribute('aria-selected', 'true');
        expect(screen.getByRole('table')).toHaveTextContent('Playing position');
        expect(screen.getByRole('table')).toHaveTextContent('playing_position');
    });

    it('searches guardians remotely and selects only the requested guardian', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({
                    data: [
                        {
                            id: 9,
                            name: 'Theresa Iginla',
                            email: 'theresa@example.com',
                            phone: '08099999999',
                        },
                    ],
                }),
            }),
        );
        render(
            <AcademyPlayers
                players={page([])}
                guardians={page([])}
                programmes={programmes}
                branches={branches}
                custom_fields={[]}
                active_tab="students"
                filters={filters}
            />,
        );
        fireEvent.click(screen.getByRole('button', { name: 'Add student' }));
        fireEvent.change(
            screen.getByRole('textbox', { name: 'Search guardians to link' }),
            {
                target: { value: 'Theresa' },
            },
        );
        fireEvent.click(
            await screen.findByRole('button', { name: /Theresa Iginla/ }),
        );
        expect(screen.getByText('Theresa Iginla')).toBeInTheDocument();
        expect(
            screen.getByLabelText('Relationship to Theresa Iginla'),
        ).toHaveValue('Parent');
        expect(screen.queryByText('Ada Okafor')).not.toBeInTheDocument();
    });
});
