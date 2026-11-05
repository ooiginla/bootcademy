import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import PlayerImport from '@/pages/academies/player-import';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
    usePage: () => ({
        props: {
            academy: { terminology: { singular: 'Player', plural: 'Players' } },
        },
    }),
}));

describe('Player CSV import', () => {
    it('shows a template download and private CSV upload step', () => {
        render(
            <PlayerImport
                token={null}
                preview={null}
                template_headers={['first_name', 'last_name']}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Import players from CSV' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Download CSV template/ }),
        ).toHaveAttribute('href', '/app/players/import/template');
        expect(screen.getByLabelText('CSV file')).toHaveAttribute(
            'accept',
            '.csv,text/csv',
        );
    });

    it('shows validation results and blocks the import action when rows have errors', () => {
        render(
            <PlayerImport
                token="private-token"
                template_headers={[]}
                preview={{
                    file_name: 'players.csv',
                    total_rows: 2,
                    valid_rows: 1,
                    error_rows: 1,
                    file_errors: [],
                    can_import: false,
                    is_truncated: false,
                    rows: [
                        {
                            line: 2,
                            label: 'Amara Okafor',
                            errors: [],
                            internal_identifier: 'LRN-1',
                            date_of_birth: '2014-05-02',
                            programme: 'Under 17',
                            branch: 'Central',
                            guardian: 'Ada Okafor',
                        },
                        {
                            line: 3,
                            label: 'Duplicate Player',
                            errors: ['This row duplicates another row.'],
                            internal_identifier: 'LRN-1',
                            date_of_birth: null,
                            programme: null,
                            branch: null,
                            guardian: '',
                        },
                    ],
                }}
            />,
        );

        expect(screen.getByRole('table')).toHaveTextContent('Amara Okafor');
        expect(screen.getByText('1 with errors')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Download error report/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Import 2 profiles/ }),
        ).not.toBeInTheDocument();
    });
});
