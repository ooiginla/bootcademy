import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AcademyProgrammes from '@/pages/academies/programmes';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: {
            academy: {
                terminology: { singular: 'Student', plural: 'Students' },
            },
        },
    }),
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
    useForm: () => ({
        data: { image: null },
        errors: {},
        processing: false,
        setData: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(),
    }),
}));

const branch = { id: 1, name: 'Central Campus', is_active: true };
const programme = {
    id: 10,
    name: 'Beginner French',
    code: 'FR-BEG',
    description: 'An introduction to speaking and reading French.',
    is_active: true,
    branches: [branch],
};

describe('Academy programmes', () => {
    it('shows the list first and opens creation in a dialog', () => {
        render(
            <AcademyProgrammes
                programmes={[]}
                branches={[branch]}
                can_manage
            />,
        );

        expect(screen.getByText('No programmes yet')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Create programme' }),
        ).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Add programme' }));
        expect(
            screen.getByRole('dialog', { name: 'Add programme' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Create programme' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('Public programme page'),
        ).not.toBeInTheDocument();
    });

    it('shows programme and branch information to staff without controls', () => {
        render(
            <AcademyProgrammes
                programmes={[programme]}
                branches={[branch]}
                can_manage={false}
            />,
        );

        expect(screen.getByText('Beginner French')).toBeInTheDocument();
        expect(screen.getByRole('table')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Beginner French' }),
        ).toHaveAttribute('href', '/app/programmes/10');
        expect(screen.getByText('Central Campus')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Add programme' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Edit' }),
        ).not.toBeInTheDocument();

        fireEvent.change(
            screen.getByRole('searchbox', {
                name: 'Filter programmes by name',
            }),
            { target: { value: 'Aerobics' } },
        );
        expect(screen.getByText('No matching programmes')).toBeInTheDocument();
        expect(screen.queryByText('Beginner French')).not.toBeInTheDocument();
    });

    it('opens programme editing in a dialog', () => {
        render(
            <AcademyProgrammes
                programmes={[programme]}
                branches={[branch]}
                can_manage
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Edit' }));
        expect(
            screen.getByRole('dialog', { name: 'Edit Beginner French' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save changes' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Public programme page')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('e.g. 4 Weeks')).toBeInTheDocument();
        expect(
            screen.getByRole('textbox', { name: 'Curriculum' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('toolbar', { name: 'Curriculum formatting' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Programme hero image')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Choose image' }),
        ).toBeInTheDocument();
    });
});
