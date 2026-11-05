import { Form, Head } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function ChangeTemporaryPassword({
    passwordRules,
}: {
    passwordRules: string;
}) {
    return (
        <>
            <Head title="Choose your password" />
            <div className="space-y-6">
                <div className="rounded-2xl border bg-muted/40 p-5">
                    <span className="grid size-11 place-items-center rounded-xl bg-primary text-primary-foreground">
                        <KeyRound className="size-5" aria-hidden="true" />
                    </span>
                    <h1 className="mt-4 text-xl font-semibold">
                        Create your personal password
                    </h1>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        Your academy admin created a temporary password. Replace
                        it before accessing academy information.
                    </p>
                </div>

                <Form
                    action="/account/required-password"
                    method="put"
                    disableWhileProcessing
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoFocus
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm new password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                            </div>
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Save personal password
                            </Button>
                        </>
                    )}
                </Form>

                <p className="flex items-center justify-center gap-2 text-xs text-muted-foreground">
                    <ShieldCheck className="size-4" aria-hidden="true" />
                    The academy admin will no longer know your password.
                </p>
            </div>
        </>
    );
}

ChangeTemporaryPassword.layout = {
    title: 'Secure your account',
    description: 'Replace the temporary password to continue.',
};
