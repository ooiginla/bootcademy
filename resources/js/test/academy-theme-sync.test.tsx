import { render } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AcademyThemeSync } from '@/components/academy-theme-sync';

const usePage = vi.fn();

vi.mock('@inertiajs/react', () => ({ usePage: () => usePage() }));

describe('AcademyThemeSync', () => {
    afterEach(() => {
        document.documentElement.removeAttribute('style');
        document.getElementById('active-academy-favicon')?.remove();
    });

    it('keeps the Bootcademy admin theme instead of applying academy website colours', () => {
        usePage.mockReturnValue({
            props: {
                academy: {
                    theme: {
                        primary: '#123456',
                        primary_foreground: '#FFFFFF',
                        secondary: '#FEDCBA',
                        secondary_foreground: '#111827',
                    },
                },
            },
        });

        render(<AcademyThemeSync />);

        expect(document.documentElement).not.toHaveStyle({
            '--primary': '#123456',
        });
        expect(document.documentElement).not.toHaveStyle({
            '--sidebar': '#123456',
        });
    });

    it('does not introduce academy tokens when there is no active academy', () => {
        usePage.mockReturnValue({ props: { academy: null } });

        render(<AcademyThemeSync />);

        expect(
            document.documentElement.style.getPropertyValue('--primary'),
        ).toBe('');
    });

    it('uses the active academy logo as the favicon and restores the fallback without one', () => {
        const fallback = document.createElement('link');
        fallback.rel = 'icon';
        fallback.href = '/favicon.svg';
        document.head.append(fallback);

        usePage.mockReturnValue({
            props: {
                academy: {
                    logo_url: '/app/academy/logo?v=123',
                    theme: {
                        primary: '#123456',
                        primary_foreground: '#FFFFFF',
                        secondary: '#FEDCBA',
                        secondary_foreground: '#111827',
                    },
                },
            },
        });

        const { rerender } = render(<AcademyThemeSync />);
        expect(
            document.getElementById('active-academy-favicon'),
        ).toHaveAttribute('href', '/app/academy/logo?v=123');

        usePage.mockReturnValue({
            props: {
                academy: {
                    logo_url: null,
                    theme: {
                        primary: '#123456',
                        primary_foreground: '#FFFFFF',
                        secondary: '#FEDCBA',
                        secondary_foreground: '#111827',
                    },
                },
            },
        });
        rerender(<AcademyThemeSync />);

        expect(
            document.getElementById('active-academy-favicon'),
        ).not.toBeInTheDocument();
        expect(document.querySelector('link[href="/favicon.svg"]')).not.toBe(
            null,
        );
        fallback.remove();
    });
});
