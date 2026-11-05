import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AcademySubscription from '@/pages/academies/subscription';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: {
            academy: {
                terminology: { singular: 'Student', plural: 'Students' },
            },
        },
    }),
}));

describe('Academy subscription summary', () => {
    it('shows honest unassigned state without inventing a trial', () => {
        render(<AcademySubscription subscription={null} />);

        expect(
            screen.getByRole('heading', { name: 'No plan assigned' }),
        ).toBeInTheDocument();
        expect(screen.getByText(/must assign a plan/i)).toBeInTheDocument();
    });

    it('shows minor-unit pricing and current active learner limit', () => {
        render(
            <AcademySubscription
                subscription={{
                    status: 'active',
                    status_label: 'Active',
                    starts_at: '2026-10-28T00:00:00Z',
                    plan: {
                        name: 'Professional',
                        currency: 'NGN',
                        monthly_minor: 4_500_000,
                        quarterly_minor: 13_500_000,
                        annual_minor: 48_600_000,
                        active_player_limit: 300,
                    },
                }}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Professional' }),
        ).toBeInTheDocument();
        expect(screen.getByText(/₦45,000/)).toBeInTheDocument();
        expect(screen.getByText('300')).toBeInTheDocument();
    });
});
