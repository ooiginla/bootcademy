import { act, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AnimatedStatistic from '@/components/public-site/animated-statistic';

describe('Animated public statistics', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('counts from zero to the configured value when it enters the viewport', () => {
        let animationFrame: FrameRequestCallback | null = null;

        class VisibleObserver {
            readonly root = null;
            readonly rootMargin = '0px';
            readonly thresholds = [0.3];

            constructor(
                private readonly callback: IntersectionObserverCallback,
            ) {}

            observe(element: Element) {
                this.callback(
                    [
                        {
                            isIntersecting: true,
                            target: element,
                            intersectionRatio: 1,
                        } as IntersectionObserverEntry,
                    ],
                    this as unknown as IntersectionObserver,
                );
            }

            disconnect() {}
            unobserve() {}
            takeRecords() {
                return [];
            }
        }

        vi.stubGlobal('IntersectionObserver', VisibleObserver);
        vi.stubGlobal('matchMedia', () => ({ matches: false }));
        vi.stubGlobal(
            'requestAnimationFrame',
            (callback: FrameRequestCallback) => {
                animationFrame = callback;
                return 1;
            },
        );
        vi.stubGlobal('cancelAnimationFrame', vi.fn());

        render(<AnimatedStatistic value="1,250+" label="Learners" />);

        const value = screen.getByText('0+');
        expect(value).toBeInTheDocument();

        act(() => {
            animationFrame?.(performance.now() + 2000);
        });

        expect(screen.getByText('1,250+')).toBeInTheDocument();
        expect(screen.getByLabelText('1,250+ Learners')).toBeInTheDocument();
    });
});
