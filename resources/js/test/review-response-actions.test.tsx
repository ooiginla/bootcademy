import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import ReviewResponse from '@/pages/academies/reviews/response';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({
        children,
    }: {
        children: ReactNode | ((value: object) => ReactNode);
    }) => (
        <form>
            {typeof children === 'function'
                ? children({ processing: false, errors: {} })
                : children}
        </form>
    ),
}));

describe('Review response actions', () => {
    it('offers a permanent delete action for an individual response', () => {
        render(
            <ReviewResponse
                review={{
                    id: 7,
                    title: 'End of term review',
                    description: null,
                    audience: 'guardians',
                    audience_label: 'Parents / guardians',
                    status: 'published',
                    status_label: 'Published',
                    collect_respondent_name: true,
                    responses_count: 1,
                    created_at: null,
                    updated_at: null,
                    fields: [
                        {
                            id: 'comment',
                            label: 'Comments',
                            description: null,
                            type: 'long_text',
                            required: false,
                            options: [],
                            max_rating: 5,
                        },
                    ],
                }}
                response={{
                    id: 11,
                    respondent_name: 'Ada Parent',
                    submitted_at: '2026-11-18T10:00:00Z',
                    answers: { comment: 'Excellent communication.' },
                }}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Delete review response Ada Parent',
            }),
        );

        expect(
            screen.getByText(
                'This permanently removes the submitted answers and updates the review summary. This cannot be undone.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Delete review response' }),
        ).toBeInTheDocument();
    });
});
