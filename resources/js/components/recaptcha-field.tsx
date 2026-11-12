import InputError from '@/components/input-error';

type RecaptchaFieldProps = {
    token: string;
    enabled: boolean;
    failed: boolean;
    error?: string;
};

/**
 * Hidden reCAPTCHA token for a form, plus the notice Google requires and any
 * verification error from the server.
 */
export default function RecaptchaField({
    token,
    enabled,
    failed,
    error,
}: RecaptchaFieldProps) {
    if (!enabled) {
        return null;
    }

    return (
        <div className="space-y-1">
            <input type="hidden" name="recaptcha_token" value={token} />
            <InputError
                message={
                    error ??
                    (failed
                        ? 'Spam protection could not load. Check your connection or disable content blockers, then refresh.'
                        : undefined)
                }
            />
            <p className="text-xs text-muted-foreground">
                Protected by reCAPTCHA. Google&apos;s{' '}
                <a
                    href="https://policies.google.com/privacy"
                    target="_blank"
                    rel="noreferrer"
                    className="underline"
                >
                    Privacy Policy
                </a>{' '}
                and{' '}
                <a
                    href="https://policies.google.com/terms"
                    target="_blank"
                    rel="noreferrer"
                    className="underline"
                >
                    Terms
                </a>{' '}
                apply.
            </p>
        </div>
    );
}
