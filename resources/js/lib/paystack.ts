type PaystackCallbacks = {
    onSuccess?: (transaction: { reference?: string }) => void;
    onCancel?: () => void;
    onError?: (error: { message?: string }) => void;
};

declare global {
    interface Window {
        PaystackPop?: new () => {
            resumeTransaction: (
                accessCode: string,
                callbacks?: PaystackCallbacks,
            ) => void;
        };
    }
}

export function loadPaystack(): Promise<void> {
    if (window.PaystackPop) return Promise.resolve();

    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            'script[data-bootcademy-paystack]',
        );
        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener(
                'error',
                () => reject(new Error('Unable to load Paystack.')),
                { once: true },
            );
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://js.paystack.co/v2/inline.js';
        script.async = true;
        script.dataset.bootcademyPaystack = 'true';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Unable to load Paystack.'));
        document.head.appendChild(script);
    });
}

/**
 * Opens the Paystack popup and resolves once the customer pays or closes it.
 *
 * Check the payment with the server only after 'paid'. Until the customer finishes,
 * Paystack reports the transaction as "abandoned", which is not a failure.
 */
export function openPaystack(accessCode: string): Promise<'paid' | 'closed'> {
    return new Promise((resolve, reject) => {
        if (!window.PaystackPop) {
            reject(new Error('Paystack did not load correctly. Please retry.'));
            return;
        }
        new window.PaystackPop().resumeTransaction(accessCode, {
            onSuccess: () => resolve('paid'),
            onCancel: () => resolve('closed'),
            onError: (error) =>
                reject(
                    new Error(
                        error?.message ??
                            'Paystack could not open. Please retry.',
                    ),
                ),
        });
    });
}
