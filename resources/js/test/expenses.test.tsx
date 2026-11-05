import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Expenses from '@/pages/academies/expenses';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({
        children,
        action,
        className,
    }: {
        children: (value: object) => ReactNode;
        action: string;
        className?: string;
    }) => (
        <form action={action} className={className}>
            {children({ processing: false, errors: {} })}
        </form>
    ),
}));

const expense = {
    id: 8,
    incurred_on: '2026-11-01',
    category: 'Equipment',
    description: 'Training cones',
    vendor: 'Sports Market',
    reference: 'R-100',
    branch_name: 'Central',
    cycle_name: '2026 Cycle',
    currency: 'NGN',
    amount: '1250.50',
    source: 'manual' as const,
    attachments: [{ id: 3, name: 'receipt.pdf', size: 1024 }],
};

const props = {
    expenses: {
        data: [expense],
        current_page: 1,
        last_page: 1,
        from: 1,
        to: 1,
        total: 1,
        prev_page_url: null,
        next_page_url: null,
    },
    summary: {
        count: 1,
        total: '1250.50',
        manual: '1250.50',
        write_offs: '0.00',
        currency: 'NGN',
    },
    categories: [
        { id: 4, name: 'Equipment', is_active: true, expenses_count: 1 },
    ],
    vendors: [
        {
            id: 7,
            name: 'Sports Market',
            email: 'sales@example.test',
            phone: '08010000000',
            address: 'Lagos',
            expenses_count: 1,
        },
    ],
    branches: [{ id: 5, name: 'Central' }],
    cycles: [{ id: 6, name: '2026 Cycle' }],
    filters: {
        category_id: null,
        branch_id: null,
        cycle_id: null,
        date_from: null,
        date_to: null,
    },
    today: '2026-11-05',
    active_tab: 'expenses' as const,
};

describe('Expense register', () => {
    it('shows filter-aware summaries and a private evidence link', () => {
        render(<Expenses {...props} />);

        expect(screen.getAllByText('₦1,250.50')).toHaveLength(3);
        expect(screen.getByText('Training cones')).toBeVisible();
        expect(
            screen.getByRole('link', { name: /receipt.pdf/i }),
        ).toHaveAttribute('href', '/app/finance/expense-attachments/3');
        expect(
            screen
                .getByRole('button', { name: 'Apply filters' })
                .closest('form'),
        ).toHaveAttribute('action', '/app/finance/expenses');
    });

    it('opens the expense dialog with branch, cycle and multiple evidence fields', () => {
        render(<Expenses {...props} />);

        fireEvent.click(screen.getByRole('button', { name: 'Record expense' }));
        expect(screen.getByLabelText('Category')).toHaveValue('');
        expect(screen.getByLabelText('Branch (optional)')).toHaveValue('');
        expect(screen.getByLabelText('Cycle (optional)')).toHaveValue('');
        expect(screen.getByLabelText('Saved vendor (optional)')).toHaveValue(
            '',
        );
        expect(screen.getByLabelText('Attachments (optional)')).toHaveAttribute(
            'multiple',
        );
        expect(screen.getByLabelText('Date')).toHaveValue('2026-11-05');
    });

    it('shows editable categories and prevents deletion while they are used', () => {
        render(<Expenses {...props} active_tab="categories" />);

        expect(screen.getByText('Equipment')).toBeVisible();
        expect(screen.getByRole('button', { name: 'Edit' })).toBeVisible();
        expect(screen.getByRole('button', { name: 'Delete' })).toBeDisabled();
    });
});
