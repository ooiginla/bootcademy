import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AcademyBranches from '@/pages/academies/branches';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Form: ({
        children,
    }: {
        children: (value: {
            processing: boolean;
            errors: Record<string, string>;
        }) => ReactNode;
    }) => <form>{children({ processing: false, errors: {} })}</form>,
}));

describe('Academy branches', () => {
    it('shows the location list first and opens creation in a dialog', () => {
        render(
            <AcademyBranches
                branches={[]}
                academy_timezone="Africa/Lagos"
                can_manage
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Branches' }),
        ).toBeInTheDocument();
        expect(screen.getByText('No branches yet')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Create branch' }),
        ).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Add branch' }));

        expect(
            screen.getByRole('dialog', { name: 'Add branch' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Create branch' }),
        ).toBeInTheDocument();
    });

    it('lets staff see branches without management controls', () => {
        render(
            <AcademyBranches
                academy_timezone="Africa/Lagos"
                can_manage={false}
                branches={[
                    {
                        id: 1,
                        name: 'North Centre',
                        code: null,
                        address_line: null,
                        city: 'Abuja',
                        state_region: 'FCT',
                        country_code: 'NG',
                        timezone: 'Africa/Lagos',
                        contact_email: null,
                        contact_phone: null,
                        is_active: true,
                    },
                ]}
            />,
        );

        expect(screen.getByText('North Centre')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Add branch' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Edit' }),
        ).not.toBeInTheDocument();

        fireEvent.change(
            screen.getByRole('searchbox', {
                name: 'Filter branches by name',
            }),
            { target: { value: 'South' } },
        );
        expect(screen.getByText('No matching branches')).toBeInTheDocument();
        expect(screen.queryByText('North Centre')).not.toBeInTheDocument();
    });

    it('opens branch editing in a dialog', () => {
        render(
            <AcademyBranches
                academy_timezone="Africa/Lagos"
                can_manage
                branches={[
                    {
                        id: 1,
                        name: 'North Centre',
                        code: 'NORTH',
                        address_line: null,
                        city: 'Abuja',
                        state_region: 'FCT',
                        country_code: 'NG',
                        timezone: 'Africa/Lagos',
                        contact_email: null,
                        contact_phone: null,
                        is_active: true,
                    },
                ]}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Edit' }));

        expect(
            screen.getByRole('dialog', { name: 'Edit North Centre' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save changes' }),
        ).toBeInTheDocument();
    });
});
