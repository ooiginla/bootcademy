import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import ApplicationDetailsPage from '@/pages/academies/application-details';

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
    source: 'admin',
    external_reference: null,
    internal_identifier: null,
    first_name: 'Amara',
    middle_name: null,
    last_name: 'Okafor',
    date_of_birth: '2014-05-02',
    age: 12,
    gender: 'Female',
    phone: '08011112222',
    whatsapp_number: null,
    home_address: '12 Academy Road',
    city: 'Lagos',
    state_region: 'Lagos',
    country: 'Nigeria',
    guardian_name: 'Ada Okafor',
    guardian_first_name: 'Ada',
    guardian_last_name: 'Okafor',
    guardian_email: null,
    guardian_phone: '08012345678',
    guardian_whatsapp_number: null,
    guardian_relationship: 'Mother',
    has_guardian_signature: false,
    guardian_consent_at: null,
    privacy_policy_version: null,
    programme_id: 2,
    programme_cycle_id: 4,
    programme_name: 'Under 17',
    cycle_id: 5,
    cycle_name: '2027 Academic Year',
    branch_id: 3,
    branch_name: 'Central',
    custom_fields: {},
    status: 'submitted',
    review_notes: null,
    created_at: '2026-10-29',
    invoices: [
        {
            id: 8,
            number: 'INV-001',
            purpose: 'application' as const,
            status: 'issued',
            currency: 'NGN',
            cycle_type: 'fixed' as const,
            cycle_starts_on: '2027-01-01',
            cycle_ends_on: '2027-12-31',
            cycle_duration: null,
            cycle_duration_unit: null,
            total: '5000.00',
            paid: '0.00',
            balance: '5000.00',
            items: [{ description: 'Application fee', amount: '5000.00' }],
            installments: [],
        },
    ],
    payments: [],
};

describe('Application details', () => {
    it('shows cycle fee context, editing and bank details while recording payment', () => {
        render(
            <ApplicationDetailsPage
                application={application}
                programmes={[{ id: 2, name: 'Under 17' }]}
                branches={[{ id: 3, name: 'Central' }]}
                active_cycles={[{ id: 5, name: '2027 Academic Year' }]}
                programme_cycles={[
                    {
                        id: 4,
                        programme_id: 2,
                        cycle_id: 5,
                        cycle_name: '2027 Academic Year',
                        name: 'Under 17',
                        branch_id: null,
                        branch_name: null,
                    },
                ]}
                countries={['Nigeria']}
                custom_fields={[]}
                bank_details={{
                    bank_name: 'Access Bank',
                    account_name: 'Example Academy',
                    account_number: '0123456789',
                }}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Amara Okafor' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Under 17 · 2027 Academic Year/),
        ).toBeInTheDocument();
        expect(document.body).toHaveTextContent('₦5,000.00');
        expect(screen.getByText('INV-001')).toBeInTheDocument();
        expect(screen.getByText('Billed ₦5,000.00')).toBeInTheDocument();
        expect(screen.getByText('Paid ₦0.00')).toHaveClass('text-emerald-700');
        expect(screen.getByText('Owing ₦5,000.00')).toHaveClass(
            'text-amber-800',
        );

        fireEvent.click(screen.getByRole('button', { name: 'Edit' }));
        expect(
            screen.getByRole('dialog', { name: 'Edit player application' }),
        ).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        fireEvent.click(screen.getByRole('button', { name: 'Record payment' }));
        expect(screen.getByText('Access Bank')).toBeInTheDocument();
        expect(screen.getByText('0123456789')).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Cheque' }),
        ).toBeInTheDocument();
    });
});
