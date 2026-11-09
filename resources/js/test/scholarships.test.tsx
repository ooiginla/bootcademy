import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Scholarships from '@/pages/academies/scholarships';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: (data: object) => ({
        data,
        errors: {},
        processing: false,
        setData: vi.fn(),
        transform: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        reset: vi.fn(),
    }),
}));

describe('Scholarships and sponsorships', () => {
    it('explains split responsibility and displays financial summary', () => {
        render(
            <Scholarships
                summary={{
                    currency: 'NGN',
                    beneficiaries: 3,
                    awarded: '150000.00',
                    academy_concessions: '50000.00',
                    sponsor_outstanding: '75000.00',
                }}
                sponsors={[]}
                schemes={[]}
                awards={[]}
                players={[]}
                enrollments={[]}
                invoices={[]}
                cycles={[]}
                programmes={[]}
                branches={[]}
                payment_methods={[]}
                today="2026-11-09"
            />,
        );

        expect(
            screen.getByRole('heading', {
                name: 'Scholarships and sponsorships',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('₦150,000.00')).toBeInTheDocument();
        expect(screen.getByText('₦75,000.00')).toBeInTheDocument();
        expect(screen.getByText('Sponsor receivable')).toBeInTheDocument();
    });
});
