import { render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AcademyBranding from '@/pages/academies/branding';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: (initial: Record<string, unknown>) => {
        const [data, setDataState] = useState(initial);
        return {
            data,
            setData: (key: string, value: unknown) =>
                setDataState((current) => ({ ...current, [key]: value })),
            errors: {},
            processing: false,
            isDirty: false,
            post: vi.fn(),
            patch: vi.fn(),
            reset: vi.fn(),
        };
    },
}));

describe('Academy settings', () => {
    it('shows the tenant bank details form with existing values', () => {
        render(
            <AcademyBranding
                branding={{
                    name: 'Example Academy',
                    logo_url: null,
                    theme: {
                        primary: '#5B5BD6',
                        primary_foreground: '#FFFFFF',
                        secondary: '#1F2937',
                        secondary_foreground: '#FFFFFF',
                    },
                    terminology: {
                        singular: 'Student',
                        plural: 'Students',
                        staff_singular: 'Instructor',
                        staff_plural: 'Instructors',
                        guardian_singular: 'Next of Kin',
                        guardian_plural: 'Next of Kin',
                        collect_guardian_information: false,
                    },
                    bank_details: {
                        bank_code: null,
                        bank_name: 'Access Bank',
                        account_name: 'Example Academy Limited',
                        account_number: '0123456789',
                    },
                    settlement: {
                        status: 'none',
                        subaccount_code: null,
                        error: null,
                        synced_at: null,
                        accepts_online_payments: false,
                    },
                }}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Academy profile' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Academy name')).toHaveValue(
            'Example Academy',
        );
        expect(
            screen.getByRole('heading', { name: 'Bank details' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Bank name')).toHaveValue('Access Bank');
        expect(screen.getByLabelText('Account name')).toHaveValue(
            'Example Academy Limited',
        );
        expect(screen.getByLabelText('Account number')).toHaveValue(
            '0123456789',
        );
        expect(
            screen.getByLabelText('Coach/instructor singular label'),
        ).toHaveValue('Instructor');
        expect(
            screen.getByLabelText('Coach/instructor plural label'),
        ).toHaveValue('Instructors');
        expect(
            screen.getByLabelText('Guardian/contact singular label'),
        ).toHaveValue('Next of Kin');
        expect(
            screen.getByText(
                'Collect guardian/contact information on applications',
            ),
        ).toBeInTheDocument();
    });

    it('picks the bank from Paystack and shows the settlement subaccount', () => {
        render(
            <AcademyBranding
                uses_paystack
                banks={[
                    { code: '044', name: 'Access Bank' },
                    { code: '058', name: 'Guaranty Trust Bank' },
                ]}
                branding={{
                    name: 'Example Academy',
                    logo_url: null,
                    theme: {
                        primary: '#2563EB',
                        primary_foreground: '#FFFFFF',
                        secondary: '#0F766E',
                        secondary_foreground: '#FFFFFF',
                    },
                    terminology: {
                        singular: 'Student',
                        plural: 'Students',
                        staff_singular: 'Coach',
                        staff_plural: 'Coaches',
                        guardian_singular: 'Guardian',
                        guardian_plural: 'Guardians',
                        collect_guardian_information: true,
                    },
                    bank_details: {
                        bank_code: '058',
                        bank_name: 'Guaranty Trust Bank',
                        account_name: 'EXAMPLE ACADEMY LTD',
                        account_number: '0123456789',
                    },
                    settlement: {
                        status: 'active',
                        subaccount_code: 'ACCT_abc123',
                        error: null,
                        synced_at: '2026-11-12T10:00:00+00:00',
                        accepts_online_payments: true,
                    },
                }}
            />,
        );

        expect(screen.getByLabelText('Bank')).toHaveValue('058');
        expect(screen.getByLabelText('Account name')).toHaveValue(
            'EXAMPLE ACADEMY LTD',
        );
        expect(screen.getByLabelText('Account name')).toHaveAttribute(
            'readonly',
        );
        expect(screen.getByText('ACCT_abc123')).toBeInTheDocument();
        expect(screen.getByText('Active')).toBeInTheDocument();
        expect(
            screen.getByText(/settle straight to this account/),
        ).toBeInTheDocument();
    });
});
