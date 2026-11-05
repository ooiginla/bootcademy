import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Attendance from '@/pages/academies/attendance';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({
        children,
        action,
        method,
    }: {
        children: (value: object) => ReactNode;
        action: string;
        method: string;
    }) => (
        <form action={action} data-method={method}>
            {children({ processing: false, errors: {} })}
        </form>
    ),
}));

const session = {
    id: 14,
    title: 'Saturday training',
    programme: 'Under 17',
    branches: ['Central'],
    coaches: ['Ada Coach'],
    starts_at: 'Sat, 7 Nov 2026 · 8:00 AM',
    status: 'scheduled',
};

const roster = [
    {
        player_id: 21,
        name: 'Amara Player',
        identifier: 'PL-021',
        status: null,
        notes: null,
        corrections: 0,
    },
    {
        player_id: 22,
        name: 'Tayo Player',
        identifier: null,
        status: 'late' as const,
        notes: 'Traffic',
        corrections: 2,
    },
];

const summary = {
    total: 2,
    marked: 1,
    present: 0,
    absent: 0,
    late: 1,
    excused: 0,
};

describe('Mobile attendance register', () => {
    it('marks individual players and supports a one-tap mark-all action', () => {
        const { container } = render(
            <Attendance session={session} roster={roster} summary={summary} />,
        );

        expect(screen.getByText('Mobile attendance register')).toBeVisible();
        expect(screen.getByText('Corrected 2×')).toBeVisible();
        expect(container.querySelector('form')).toHaveAttribute(
            'action',
            '/app/sessions/14/attendance',
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Absent: Amara Player' }),
        );
        expect(
            container.querySelector<HTMLInputElement>(
                'input[name="attendance[0][status]"]',
            ),
        ).toHaveValue('absent');

        fireEvent.click(
            screen.getByRole('button', { name: 'Mark all present' }),
        );
        expect(
            container.querySelector<HTMLInputElement>(
                'input[name="attendance[0][status]"]',
            ),
        ).toHaveValue('present');

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Add note for Amara Player',
            }),
        );
        fireEvent.change(screen.getByLabelText('Note'), {
            target: { value: 'Left training early' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Done' }));
        expect(
            container.querySelector<HTMLInputElement>(
                'input[name="attendance[0][notes]"]',
            ),
        ).toHaveValue('Left training early');
        expect(
            screen.getByRole('button', {
                name: 'Edit note for Amara Player',
            }),
        ).toBeVisible();
        expect(
            container.querySelector<HTMLInputElement>(
                'input[name="attendance[1][status]"]',
            ),
        ).toHaveValue('present');
    });

    it('locks the register when the session is cancelled', () => {
        render(
            <Attendance
                session={{ ...session, status: 'cancelled' }}
                roster={roster}
                summary={summary}
            />,
        );

        expect(
            screen.getByText(
                'Attendance cannot be recorded for a cancelled session.',
            ),
        ).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Save attendance' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Mark all present' }),
        ).toBeDisabled();
    });
});
