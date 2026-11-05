import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Sessions from '@/pages/academies/sessions';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: {
            academy: {
                terminology: {
                    staff_singular: 'Instructor',
                    staff_plural: 'Instructors',
                },
            },
        },
    }),
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({
        children,
        className,
    }: {
        children: (value: object) => ReactNode;
        className?: string;
    }) => (
        <form className={className}>
            {children({ processing: false, errors: {} })}
        </form>
    ),
}));

const page = {
    data: [],
    current_page: 1,
    last_page: 1,
    from: null,
    to: null,
    total: 0,
    prev_page_url: null,
    next_page_url: null,
};

describe('Session scheduling', () => {
    it('keeps filters in one desktop row and supports all branches, multiple instructors and virtual links', () => {
        render(
            <Sessions
                sessions={page}
                calendar_sessions={[]}
                branches={[
                    { id: 1, name: 'Central' },
                    { id: 2, name: 'West' },
                ]}
                programmes={[{ id: 3, name: 'Aerobics' }]}
                coaches={[
                    { id: 4, name: 'Ada' },
                    { id: 5, name: 'Tunde' },
                ]}
                filters={{
                    branch_id: null,
                    programme_id: null,
                    coach_id: null,
                    month: '2026-11',
                }}
                active_tab="list"
                calendar={{
                    start: '2026-10-26',
                    end: '2026-12-06',
                    month: '2026-11',
                }}
                can_manage
            />,
        );
        expect(
            screen
                .getByRole('button', { name: 'Apply filters' })
                .closest('form'),
        ).toHaveClass('md:grid-cols-5');
        fireEvent.click(screen.getByRole('button', { name: 'Add session' }));
        expect(
            screen.getByRole('button', { name: 'Select branches' }),
        ).toHaveAttribute('aria-haspopup', 'menu');
        expect(
            screen.getByRole('button', { name: 'Select instructors' }),
        ).toHaveAttribute('aria-haspopup', 'menu');
        expect(screen.getByLabelText('Delivery mode')).toHaveValue('physical');
        expect(
            screen.getByLabelText('Meeting link (optional)'),
        ).toHaveAttribute('type', 'url');
    });
});
