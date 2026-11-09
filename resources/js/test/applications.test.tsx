import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Applications from '@/pages/academies/applications';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
    usePage: () => ({
        props: {
            academy: { terminology: { singular: 'Player', plural: 'Players' } },
        },
    }),
}));

const application = {
    id: 1,
    reference: 'APP-ABC123',
    source: 'api',
    external_reference: 'website-1001',
    internal_identifier: 'LRN-001',
    first_name: 'Amara',
    middle_name: null,
    last_name: 'Okafor',
    date_of_birth: '2014-05-02',
    age: 12,
    gender: 'Female',
    phone: '08011112222',
    whatsapp_number: '08011112223',
    home_address: '12 Academy Road',
    city: 'Lagos',
    state_region: 'Lagos',
    country: 'Nigeria',
    guardian_name: 'Ada Okafor',
    guardian_first_name: 'Ada',
    guardian_last_name: 'Okafor',
    guardian_email: 'ada@example.test',
    guardian_phone: '08012345678',
    guardian_whatsapp_number: '08087654321',
    guardian_relationship: 'Mother',
    has_guardian_signature: true,
    guardian_consent_at: '2026-10-28T12:00:00+01:00',
    privacy_policy_version: '2026-10',
    programme_id: 2,
    programme_cycle_id: null,
    cycle_id: null,
    programme_name: 'Under 17',
    cycle_name: null,
    branch_id: 3,
    branch_name: 'Central',
    custom_fields: {},
    status: 'submitted',
    review_notes: null,
    created_at: '2026-10-28',
    invoices: [],
};
const applications = {
    data: [application],
    current_page: 1,
    last_page: 1,
    from: 1,
    to: 1,
    total: 1,
    prev_page_url: null,
    next_page_url: null,
};

describe('Applications and prospects', () => {
    it('lists prospects and opens creation in a dialog using academy terminology', () => {
        render(
            <Applications
                applications={applications}
                programmes={[{ id: 2, name: 'Under 17' }]}
                branches={[{ id: 3, name: 'Central' }]}
                countries={['Ghana', 'Nigeria', 'South Africa']}
                programme_cycles={[
                    {
                        id: 4,
                        programme_id: 2,
                        cycle_id: 5,
                        cycle_name: '2026',
                        name: 'Under 17',
                        branch_id: null,
                        branch_name: null,
                    },
                    {
                        id: 6,
                        programme_id: 2,
                        cycle_id: 5,
                        cycle_name: '2026',
                        name: 'Under 17',
                        branch_id: 3,
                        branch_name: 'Central',
                    },
                ]}
                active_cycles={[{ id: 5, name: '2026' }]}
                cohorts={[
                    {
                        id: 8,
                        programme_cycle_id: 6,
                        programme_id: 2,
                        programme_name: 'Under 17',
                        cycle_name: '2026',
                        branch_id: 3,
                        branch_name: 'Central',
                        name: 'January cohort',
                        code: 'JAN-26',
                        starts_on: '2026-01-10',
                        ends_on: '2026-06-30',
                        capacity: 30,
                        delivery_mode: 'physical',
                        status: 'open',
                        is_available_for_applications: true,
                    },
                ]}
                custom_fields={[]}
                filters={{ search: '', status: 'all' }}
            />,
        );
        expect(screen.getByRole('table')).toHaveTextContent('Amara Okafor');
        fireEvent.click(
            screen.getByRole('button', { name: 'Add application' }),
        );
        expect(
            screen.getByRole('dialog', { name: 'New player application' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Home address (optional)')).toBeInTheDocument();
        expect(screen.getByText('Signature (optional)')).toBeInTheDocument();
        expect(document.querySelector('select[name="country"]')).toHaveValue(
            'Nigeria',
        );
        fireEvent.change(document.querySelector('select[name="cohort_id"]')!, {
            target: { value: '8' },
        });
        expect(
            document.querySelector('input[name="programme_cycle_id"]'),
        ).toHaveValue('6');
        expect(
            document.querySelector('input[name="academy_cycle_id"]'),
        ).toHaveValue('5');
        expect(document.querySelector('input[name="branch_id"]')).toHaveValue(
            '3',
        );
        expect(
            Array.from(
                document.querySelectorAll('select[name="gender"] option'),
            ).map((option) => option.getAttribute('value')),
        ).toEqual(['', 'Male', 'Female']);
    });

    it('shows the application status and links to the review details page', () => {
        render(
            <Applications
                applications={applications}
                programmes={[{ id: 2, name: 'Under 17' }]}
                branches={[{ id: 3, name: 'Central' }]}
                countries={['Ghana', 'Nigeria', 'South Africa']}
                programme_cycles={[
                    {
                        id: 4,
                        programme_id: 2,
                        cycle_id: 5,
                        cycle_name: '2026',
                        name: 'Under 17',
                        branch_id: null,
                        branch_name: null,
                    },
                ]}
                active_cycles={[{ id: 5, name: '2026' }]}
                custom_fields={[]}
                filters={{ search: '', status: 'all' }}
            />,
        );
        expect(screen.getByRole('table')).toHaveTextContent('Submitted');
        expect(screen.getByRole('link', { name: 'Details' })).toHaveAttribute(
            'href',
            '/app/applications/1',
        );
    });

    it('links each application to its full details page', () => {
        render(
            <Applications
                applications={applications}
                programmes={[{ id: 2, name: 'Under 17' }]}
                branches={[{ id: 3, name: 'Central' }]}
                countries={['Ghana', 'Nigeria', 'South Africa']}
                programme_cycles={[]}
                active_cycles={[]}
                custom_fields={[]}
                filters={{ search: '', status: 'all' }}
            />,
        );

        expect(screen.getByRole('link', { name: 'Details' })).toHaveAttribute(
            'href',
            '/app/applications/1',
        );
    });

    it('manages application availability directly from the cohort tab', () => {
        render(
            <Applications
                applications={applications}
                programmes={[{ id: 2, name: 'Under 17' }]}
                branches={[{ id: 3, name: 'Central' }]}
                countries={['Nigeria']}
                programme_cycles={[
                    {
                        id: 4,
                        programme_id: 2,
                        cycle_id: 5,
                        cycle_name: '2026',
                        name: 'Under 17',
                        branch_id: 3,
                        branch_name: 'Central',
                    },
                ]}
                active_cycles={[{ id: 5, name: '2026' }]}
                custom_fields={[]}
                filters={{ search: '', status: 'all' }}
                active_tab="cohorts"
                cohorts={[
                    {
                        id: 8,
                        programme_cycle_id: 4,
                        programme_id: 2,
                        programme_name: 'Under 17',
                        cycle_name: '2026',
                        branch_id: 3,
                        branch_name: 'Central',
                        name: 'January cohort',
                        code: 'JAN-26',
                        starts_on: '2026-01-10',
                        ends_on: '2026-06-30',
                        capacity: 30,
                        delivery_mode: 'hybrid',
                        status: 'open',
                        is_receiving_applications: true,
                        applications_open_on: '2026-01-01',
                        applications_close_on: '2026-06-01',
                        is_open_for_applications: true,
                        applications_count: 4,
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Cohorts' }),
        ).toBeInTheDocument();
        expect(screen.getByText('January cohort')).toBeInTheDocument();
        expect(screen.getByText('Open now')).toBeInTheDocument();
        expect(screen.getByText(/4 submitted/)).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Application settings' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Add cohort' }),
        ).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Add cohort' }));
        expect(
            document.querySelector('select[name="is_receiving_applications"]'),
        ).toHaveValue('0');
        expect(
            document.querySelector('input[name="applications_open_on"]'),
        ).toBeInTheDocument();
    });
});
