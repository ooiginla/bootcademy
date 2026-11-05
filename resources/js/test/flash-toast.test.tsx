import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useFlashToast } from '@/hooks/use-flash-toast';

const mocks = vi.hoisted(() => ({
    on: vi.fn(),
    success: vi.fn(),
    error: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { on: mocks.on },
}));

vi.mock('sonner', () => ({
    toast: {
        success: mocks.success,
        error: mocks.error,
    },
}));

describe('useFlashToast', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.on.mockReturnValue(() => undefined);
    });

    it('shows Inertia ephemeral flash events used by successful forms', () => {
        let listener: ((event: CustomEvent) => void) | undefined;
        mocks.on.mockImplementation(
            (_event: string, callback: (event: CustomEvent) => void) => {
                listener = callback;
                return () => undefined;
            },
        );

        renderHook(() => useFlashToast());
        mocks.success.mockClear();

        act(() => {
            listener?.({
                detail: {
                    flash: {
                        toast: {
                            type: 'success',
                            message: 'Profile updated.',
                        },
                    },
                },
            } as CustomEvent);
        });

        expect(mocks.success).toHaveBeenCalledWith('Profile updated.');
    });
});
