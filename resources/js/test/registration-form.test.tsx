import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Register from '@/pages/auth/register';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
    Form: ({ children }: { children: (state: unknown) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
}));

vi.mock('@/lib/recaptcha', () => ({
    useRecaptchaToken: () => ({
        token: '',
        setToken: vi.fn(),
        refresh: vi.fn(),
    }),
}));

vi.mock('@/components/recaptcha-field', () => ({
    default: () => null,
}));

describe('Registration form', () => {
    it('orders academy details first and applies editable country defaults', () => {
        const { container } = render(
            <Register
                passwordRules="minlength:8"
                countries={[
                    {
                        code: 'NG',
                        name: 'Nigeria',
                        timezone: 'Africa/Lagos',
                        currency: 'NGN',
                    },
                    {
                        code: 'GH',
                        name: 'Ghana',
                        timezone: 'Africa/Accra',
                        currency: 'GHS',
                    },
                ]}
                timezones={['Africa/Lagos', 'Africa/Accra']}
                currencies={[
                    { code: 'NGN', name: 'Nigerian Naira' },
                    { code: 'GHS', name: 'Ghanaian Cedi' },
                ]}
            />,
        );

        const labels = Array.from(container.querySelectorAll('label')).map(
            (label) => label.textContent?.trim(),
        );
        expect(labels[0]).toBe('Academy name');
        expect(labels.indexOf('Full Name') + 1).toBe(
            labels.indexOf('Password'),
        );

        fireEvent.change(screen.getByLabelText('Country'), {
            target: { value: 'GH' },
        });
        expect(screen.getByLabelText('Timezone')).toHaveValue('Africa/Accra');
        expect(screen.getByLabelText('Currency')).toHaveValue('GHS');

        fireEvent.change(screen.getByLabelText('Timezone'), {
            target: { value: 'Africa/Lagos' },
        });
        expect(screen.getByLabelText('Timezone')).toHaveValue('Africa/Lagos');
    });
});
