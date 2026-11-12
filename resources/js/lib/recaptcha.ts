import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

type Grecaptcha = {
    ready: (callback: () => void) => void;
    execute: (siteKey: string, options: { action: string }) => Promise<string>;
};

declare global {
    interface Window {
        grecaptcha?: Grecaptcha;
    }
}

const loaders = new Map<string, Promise<Grecaptcha>>();

function loadRecaptcha(siteKey: string): Promise<Grecaptcha> {
    const existing = loaders.get(siteKey);
    if (existing) {
        return existing;
    }

    const loader = new Promise<Grecaptcha>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
        script.async = true;
        script.defer = true;
        script.onload = () => {
            const grecaptcha = window.grecaptcha;
            if (grecaptcha) {
                grecaptcha.ready(() => resolve(grecaptcha));
            } else {
                reject(new Error('reCAPTCHA did not load.'));
            }
        };
        script.onerror = () => {
            loaders.delete(siteKey);
            reject(new Error('reCAPTCHA could not be loaded.'));
        };
        document.head.appendChild(script);
    });
    loaders.set(siteKey, loader);

    return loader;
}

/** Tokens expire after two minutes, so refresh well before that. */
const REFRESH_MS = 90_000;

/**
 * Keeps a fresh reCAPTCHA v3 token for `action`. Tokens can be used once, so
 * call `refresh()` after each submission. When the platform has no site key,
 * `enabled` is false and the token stays empty.
 */
export function useRecaptchaToken(action: string) {
    const siteKey = usePage().props.recaptcha_site_key;
    const [token, setToken] = useState('');
    const [failed, setFailed] = useState(false);

    const refresh = useCallback(async (): Promise<string> => {
        if (!siteKey) {
            return '';
        }

        try {
            const grecaptcha = await loadRecaptcha(siteKey);
            const next = await grecaptcha.execute(siteKey, { action });
            setToken(next);
            setFailed(false);

            return next;
        } catch {
            setFailed(true);

            return '';
        }
    }, [siteKey, action]);

    useEffect(() => {
        if (!siteKey) {
            return;
        }

        void refresh();
        const timer = window.setInterval(() => void refresh(), REFRESH_MS);

        return () => window.clearInterval(timer);
    }, [siteKey, refresh]);

    return { enabled: Boolean(siteKey), token, failed, refresh };
}
