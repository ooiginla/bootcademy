import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { SupportModeBanner } from '@/components/support-mode-banner';

let supportAccess: {
    academy_name: string;
} | null = {
    academy_name: 'Harmony Academy',
};

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { support_access: supportAccess } }),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false })}</form>
    ),
}));

describe('SupportModeBanner', () => {
    it('prominently identifies full owner access and provides exit', () => {
        render(<SupportModeBanner />);

        expect(
            screen.getByText('Support Mode · Harmony Academy'),
        ).toBeInTheDocument();
        expect(screen.getByText(/full owner access/i)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Exit Support Mode' }),
        ).toBeInTheDocument();
    });

    it('renders nothing outside support mode', () => {
        supportAccess = null;
        const { container } = render(<SupportModeBanner />);
        expect(container).toBeEmptyDOMElement();
    });
});
