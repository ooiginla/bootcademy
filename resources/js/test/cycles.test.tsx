import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Cycles from '@/pages/academies/cycles';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
}));

describe('Academy billing cycles', () => {
    it('lists historical cycles and supports fixed or rolling creation', () => {
        render(
            <Cycles
                cycles={[
                    {
                        id: 1,
                        name: '2026 Academic Year',
                        type: 'fixed',
                        type_label: 'Fixed dates',
                        starts_on: '2026-01-01',
                        ends_on: '2026-12-31',
                        duration_value: null,
                        duration_unit: null,
                        status: 'active',
                        programme_cycles_count: 2,
                        enrollments_count: 24,
                    },
                ]}
            />,
        );

        expect(screen.getByRole('table')).toHaveTextContent(
            '2026 Academic Year',
        );
        expect(screen.getByRole('table')).toHaveTextContent('24');
        fireEvent.click(screen.getByRole('button', { name: 'Add cycle' }));
        expect(
            screen.getByRole('dialog', { name: 'Create billing cycle' }),
        ).toBeInTheDocument();
        fireEvent.change(screen.getByRole('combobox', { name: 'Cycle type' }), {
            target: { value: 'rolling' },
        });
        expect(screen.getByText('Duration unit')).toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Weeks' }),
        ).toBeInTheDocument();
    });
});
