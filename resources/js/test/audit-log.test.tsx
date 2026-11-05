import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AuditLog from '@/pages/academies/audit-log';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({})}</form>
    ),
}));

describe('Academy audit log', () => {
    it('shows filterable immutable entries and metadata details', () => {
        render(
            <AuditLog
                logs={{
                    data: [
                        {
                            id: 42,
                            actor: {
                                id: 1,
                                name: 'Academy Owner',
                                email: 'owner@example.test',
                            },
                            action: 'academy.branch.updated',
                            action_label: 'Academy Branch Updated',
                            subject_type: 'App\\Models\\Branch',
                            subject_label: 'Branch',
                            subject_id: 7,
                            metadata: {
                                changed_fields: ['name'],
                            },
                            ip_address: '127.0.0.1',
                            created_at: 'Nov 4, 2026 11:00 AM',
                        },
                    ],
                    current_page: 1,
                    last_page: 1,
                    from: 1,
                    to: 1,
                    total: 1,
                    prev_page_url: null,
                    next_page_url: null,
                }}
                actors={[
                    {
                        id: 1,
                        name: 'Academy Owner',
                        email: 'owner@example.test',
                    },
                ]}
                actions={[
                    {
                        value: 'academy.branch.updated',
                        label: 'Academy Branch Updated',
                    },
                ]}
                subject_types={[
                    { value: 'App\\Models\\Branch', label: 'Branch' },
                ]}
                filters={{
                    actor_user_id: 1,
                    action: 'academy.branch.updated',
                    subject_type: 'App\\Models\\Branch',
                    subject_id: 7,
                    date_from: '2026-11-04',
                    date_to: '2026-11-04',
                }}
            />,
        );

        expect(screen.getByLabelText('User')).toHaveValue('1');
        expect(screen.getByLabelText('Action')).toHaveValue(
            'academy.branch.updated',
        );
        expect(screen.getByRole('table')).toHaveTextContent('Academy Owner');
        expect(screen.getByRole('table')).toHaveTextContent('Branch #7');
        expect(screen.getByRole('table')).toHaveTextContent('127.0.0.1');

        fireEvent.click(screen.getByRole('button', { name: 'View' }));
        expect(
            screen.getByRole('dialog', { name: 'Audit entry #42' }),
        ).toHaveTextContent('changed_fields');
        expect(screen.getByRole('dialog')).toHaveTextContent('name');
    });
});
