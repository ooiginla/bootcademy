import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import ApiIntegrations from '@/pages/academies/api-integrations';

const writeText = vi.fn().mockResolvedValue(undefined);
Object.assign(navigator, { clipboard: { writeText } });

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
}));

describe('API integrations', () => {
    it('shows a newly generated secret once and lists revocable keys', async () => {
        render(
            <ApiIntegrations
                new_api_secret="aca_private_secret"
                api_keys={[
                    {
                        id: 1,
                        name: 'Website',
                        prefix: 'aca_private_',
                        last_used_at: null,
                        created_at: '2026-10-28T12:00:00Z',
                        revoked_at: null,
                    },
                ]}
            />,
        );

        expect(screen.getByLabelText('New API secret')).toHaveValue(
            'aca_private_secret',
        );
        fireEvent.click(screen.getByRole('button', { name: 'Copy' }));
        expect(writeText).toHaveBeenCalledWith('aca_private_secret');
        expect(screen.getByText('Website')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Revoke' }),
        ).toBeInTheDocument();
    });
});
