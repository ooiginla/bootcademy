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
                        bank_name: 'Access Bank',
                        account_name: 'Example Academy Limited',
                        account_number: '0123456789',
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
});
