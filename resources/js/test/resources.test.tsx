import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AcademyResources from '@/pages/academies/resources';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, ...props }: React.ComponentProps<'a'>) => (
        <a {...props}>{children}</a>
    ),
    useForm: (initial: Record<string, unknown>) => {
        const [data, setDataState] = useState(initial);
        return {
            data,
            setData: (key: string, value: unknown) =>
                setDataState((current) => ({ ...current, [key]: value })),
            errors: {},
            processing: false,
            post: vi.fn(),
            reset: vi.fn(),
        };
    },
}));

vi.mock('@/components/delete-record-button', () => ({
    default: ({ name }: { name: string }) => (
        <button type="button">Delete {name}</button>
    ),
}));

describe('Resource Library', () => {
    it('shows public-file guidance and an empty state', () => {
        render(
            <AcademyResources
                resources={{
                    data: [],
                    from: null,
                    to: null,
                    next_page_url: null,
                    prev_page_url: null,
                }}
                filters={{ search: '' }}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Resource Library' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Files uploaded here are public/),
        ).toBeInTheDocument();
        expect(screen.getByText('No resources yet')).toBeInTheDocument();
        expect(screen.getByLabelText('Choose file')).toHaveAttribute(
            'accept',
            expect.stringContaining('.pdf'),
        );
    });

    it('copies a resource public URL', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });

        render(
            <AcademyResources
                resources={{
                    data: [
                        {
                            id: '9d21e4bf-21ef-49a9-a74a-a52ae8dc22d5',
                            name: 'academy-brochure.pdf',
                            mime_type: 'application/pdf',
                            extension: 'pdf',
                            size_bytes: 1048576,
                            public_url:
                                'https://bootcademy.test/assets/resource-id',
                            uploaded_at: '2026-11-25T12:00:00+01:00',
                            is_image: false,
                        },
                    ],
                    from: 1,
                    to: 1,
                    next_page_url: null,
                    prev_page_url: null,
                }}
                filters={{ search: '' }}
            />,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Copy public URL for academy-brochure.pdf',
            }),
        );

        await waitFor(() =>
            expect(writeText).toHaveBeenCalledWith(
                'https://bootcademy.test/assets/resource-id',
            ),
        );
        expect(await screen.findByText('Copied')).toBeInTheDocument();
    });
});
