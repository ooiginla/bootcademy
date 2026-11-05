import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Coaches from '@/pages/academies/coaches';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: {
            academy: {
                terminology: {
                    singular: 'Player',
                    plural: 'Players',
                    staff_singular: 'Instructor',
                    staff_plural: 'Instructors',
                },
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

const field = {
    id: 5,
    key: 'certification',
    label: 'Certification',
    type: 'text' as const,
    options: [],
    is_required: true,
    is_sensitive: false,
    is_active: true,
    sort_order: 0,
};
const coach = {
    membership_id: 9,
    name: 'Tunde Bello',
    email: 'tunde@example.test',
    status: 'active',
    branches: ['Gbagada'],
    profile: {
        first_name: 'Tunde',
        middle_name: null,
        last_name: 'Bello',
        date_of_birth: '1988-04-12',
        gender: 'male',
        phone: '08010000000',
        whatsapp_number: null,
        home_address: '12 Lagos Street',
        city: 'Ikeja',
        state_region: 'Lagos',
        country: 'Nigeria',
        years_of_experience: 12,
        biography: 'Youth coach',
        custom_fields: { certification: 'UEFA B' },
    },
};
const paginator = {
    data: [coach],
    current_page: 1,
    last_page: 1,
    from: 1,
    to: 1,
    total: 1,
    prev_page_url: null,
    next_page_url: null,
};

describe('Coach directory', () => {
    it('lists coaches and opens their biodata dialog with custom values', () => {
        render(
            <Coaches
                coaches={paginator}
                custom_fields={[field]}
                active_tab="coaches"
                filters={{ search: '' }}
                countries={['Nigeria', 'Ghana']}
            />,
        );

        expect(screen.getByRole('table')).toHaveTextContent('Tunde Bello');
        expect(screen.getByRole('table')).toHaveTextContent('Gbagada');
        expect(screen.getByRole('table')).toHaveTextContent('12 years');
        fireEvent.click(
            screen.getByRole('button', { name: 'Edit Tunde Bello' }),
        );
        expect(
            screen.getByRole('dialog', { name: 'Edit instructor biodata' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Years of experience')).toHaveValue(12);
        expect(screen.getByLabelText('Certification *')).toHaveValue('UEFA B');
        expect(screen.getByLabelText('Country')).toHaveValue('Nigeria');
    });

    it('shows coach-specific custom fields in their own tab', () => {
        render(
            <Coaches
                coaches={paginator}
                custom_fields={[field]}
                active_tab="fields"
                filters={{ search: '' }}
                countries={['Nigeria']}
            />,
        );
        expect(screen.getByRole('table')).toHaveTextContent('Certification');
        expect(screen.getByRole('table')).toHaveTextContent('certification');
        expect(
            screen.getByRole('button', { name: 'Add custom field' }),
        ).toBeInTheDocument();
    });
});
