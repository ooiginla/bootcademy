import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import PlayerDetails from '@/pages/academies/player-details';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({
        children,
        onSuccess,
    }: {
        children: (value: object) => ReactNode;
        onSuccess?: () => void;
    }) => (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                onSuccess?.();
            }}
        >
            {children({ processing: false, errors: {} })}
        </form>
    ),
    usePage: () => ({
        props: {
            academy: { terminology: { singular: 'Player', plural: 'Players' } },
        },
    }),
}));

describe('Player cycle account', () => {
    it('shows active and historical cycle balances, membership and payments', () => {
        render(
            <PlayerDetails
                player={{
                    id: 1,
                    internal_identifier: 'LRN-1',
                    name: 'Amara Okafor',
                    first_name: 'Amara',
                    middle_name: null,
                    last_name: 'Okafor',
                    date_of_birth: '2014-05-02',
                    age: 12,
                    gender: 'Female',
                    phone: '08011112222',
                    whatsapp_number: '08011112222',
                    home_address: '12 Academy Road',
                    city: 'Lagos',
                    state_region: 'Lagos',
                    country: 'Nigeria',
                    status: 'active',
                    currency: 'NGN',
                    total_billed: '200.00',
                    total_paid: '110.00',
                    total_owed: '90.00',
                    guardians: [
                        {
                            id: 7,
                            first_name: 'Ada',
                            last_name: 'Okafor',
                            name: 'Ada Okafor',
                            relationship: 'Mother',
                            email: 'ada@example.com',
                            phone: '08012345678',
                            whatsapp_number: '08012345678',
                            is_primary: true,
                            is_emergency_contact: true,
                            has_signature: false,
                        },
                    ],
                    custom_fields: [
                        { label: 'Playing position', value: 'Forward' },
                    ],
                    custom_field_values: { playing_position: 'Forward' },
                    cycles: [
                        {
                            id: 2,
                            name: '2026 Academic Year',
                            type: 'fixed',
                            status: 'active',
                            billed: '200.00',
                            paid: '110.00',
                            owed: '90.00',
                            enrollments: [
                                {
                                    id: 4,
                                    programme_id: 10,
                                    programme: 'Under 17',
                                    branch_id: 20,
                                    branch: 'Central',
                                    status: 'active',
                                    starts_on: '2026-10-20',
                                    ends_on: '2026-12-31',
                                },
                            ],
                            invoices: [
                                {
                                    id: 5,
                                    number: 'INV-001',
                                    purpose: 'programme',
                                    fee_name: 'Programme fee',
                                    programme: 'Under 17',
                                    status: 'issued',
                                    currency: 'NGN',
                                    total: '150.00',
                                    paid: '0.00',
                                    written_off: '0.00',
                                    balance: '150.00',
                                    cycle_type: 'fixed',
                                    cycle_starts_on: '2026-01-01',
                                    cycle_ends_on: '2026-12-31',
                                    cycle_duration: null,
                                    cycle_duration_unit: null,
                                    installments: [
                                        {
                                            name: 'First',
                                            amount: '60.00',
                                            paid: '60.00',
                                            due_on: '2026-10-20',
                                            due_offset_days: null,
                                        },
                                    ],
                                },
                                {
                                    id: 8,
                                    number: 'INV-CANCELLED',
                                    purpose: 'custom',
                                    fee_name: 'Insurance',
                                    programme: 'Under 17',
                                    status: 'void',
                                    currency: 'NGN',
                                    total: '25.00',
                                    paid: '0.00',
                                    written_off: '0.00',
                                    balance: '0.00',
                                    cycle_type: 'fixed',
                                    cycle_starts_on: '2026-01-01',
                                    cycle_ends_on: '2026-12-31',
                                    cycle_duration: null,
                                    cycle_duration_unit: null,
                                    installments: [],
                                },
                            ],
                            payments: [
                                {
                                    id: 6,
                                    reference: 'PAY-001',
                                    amount: '60.00',
                                    reversed: '0.00',
                                    net: '60.00',
                                    method: 'bank_transfer',
                                    status: 'verified',
                                    received_on: '2026-10-20',
                                },
                            ],
                        },
                    ],
                }}
                programmes={[{ id: 10, name: 'Under 17' }]}
                branches={[{ id: 20, name: 'Central' }]}
                custom_fields={[]}
                bookable_fees={[
                    {
                        id: 30,
                        name: 'Medical fee',
                        cycle_name: '2026 Academic Year',
                        amount: '25.00',
                        currency: 'NGN',
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Amara Okafor' }),
        ).toBeInTheDocument();
        const cycleHeading = screen.getByRole('heading', {
            name: '2026 Academic Year',
        });
        expect(cycleHeading).toBeInTheDocument();
        expect(screen.getByText('Fixed period')).toBeInTheDocument();
        expect(
            cycleHeading.compareDocumentPosition(
                screen.getByText('Total billed'),
            ) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(screen.getAllByText('Under 17').length).toBeGreaterThan(0);
        expect(screen.getByText('Playing position')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Edit profile' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Edit enrolment' }),
        ).toBeInTheDocument();

        fireEvent.click(screen.getByRole('tab', { name: 'Guardians (1)' }));
        expect(screen.getByText('Ada Okafor')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Edit guardian' }),
        ).toBeInTheDocument();

        fireEvent.click(screen.getByRole('tab', { name: 'Invoices (2)' }));
        expect(
            screen.getByRole('columnheader', { name: 'Actions' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('table')).toHaveTextContent('INV-001');
        expect(screen.getByRole('table')).toHaveTextContent('INV-CANCELLED');
        expect(screen.getByText('Cancelled')).toHaveClass(
            'bg-destructive',
            'text-white',
        );
        expect(screen.getByRole('table')).toHaveTextContent('Programme fee');
        expect(
            screen.getByRole('button', { name: 'Book fee' }),
        ).toBeInTheDocument();
        const actions = screen.getByRole('button', {
            name: 'Actions for INV-001',
        });
        actions.focus();
        fireEvent.keyDown(actions, { key: 'Enter' });
        expect(screen.getByText('Record payment')).toBeInTheDocument();
        expect(screen.getByText('Write off balance')).toBeInTheDocument();
        expect(screen.getByText('Cancel invoice')).toBeInTheDocument();
        fireEvent.click(screen.getByText('Write off balance'));
        expect(
            screen.getByRole('heading', {
                name: 'Write off invoice balance',
            }),
        ).toBeInTheDocument();
        const reason = screen.getByRole('dialog').querySelector('textarea');
        expect(reason).not.toBeNull();
        fireEvent.change(reason!, {
            target: { value: 'Approved adjustment' },
        });
        fireEvent.click(
            screen.getByRole('button', { name: 'Record write-off' }),
        );
        expect(
            screen.queryByRole('heading', {
                name: 'Write off invoice balance',
            }),
        ).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('tab', { name: 'Payments (1)' }));
        expect(screen.getByRole('table')).toHaveTextContent('PAY-001');
        expect(screen.getByRole('table')).toHaveTextContent('bank transfer');
    });
});
