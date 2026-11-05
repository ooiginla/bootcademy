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

    it('applies the active academy colours to shared workspace tokens', () => {
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

        expect(
            document.documentElement.style.getPropertyValue('--primary'),
        ).toBe('#123456');
        expect(
            document.documentElement.style.getPropertyValue('--sidebar'),
        ).toBe('#123456');
        expect(
            document.documentElement.style.getPropertyValue('--secondary'),
        ).toBe('#FEDCBA');
    });

    it('removes academy tokens when there is no active academy', () => {
        document.documentElement.style.setProperty('--primary', '#123456');
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
