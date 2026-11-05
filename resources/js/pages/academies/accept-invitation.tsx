import { Form, Head, Link } from '@inertiajs/react';
import { Building2, CheckCircle2, LogIn, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    token: string;
    invitation: {
        academy_name: string;
        academy_type: string;
        email: string;
        role_label: string;
        expires_at: string;
    };
    existing_account: boolean;
    authenticated_as_invitee: boolean;
    authenticated_as_different_user: boolean;
    passwordRules: string;
};

export default function AcceptInvitation({
    token,
    invitation,
    existing_account,
    authenticated_as_invitee,
    authenticated_as_different_user,
    passwordRules,
}: Props) {
    const action = `/invites/${token}`;

    return (
        <>
            <Head title={`Join ${invitation.academy_name}`} />
            <div className="space-y-6">
                <div className="rounded-2xl border bg-muted/40 p-5">
                    <span className="grid size-11 place-items-center rounded-xl bg-primary text-primary-foreground">
                        <Building2 className="size-5" aria-hidden="true" />
                    </span>
                    <h1 className="mt-4 text-xl font-semibold">
                        Join {invitation.academy_name}
                    </h1>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        You were invited as{' '}
                        <strong className="text-foreground">
                            {invitation.role_label}
                        </strong>{' '}
                        using {invitation.email}.
                    </p>
                </div>

                {authenticated_as_different_user ? (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm">
                        Sign out and open this link as {invitation.email} to
                        protect both accounts.
                    </div>
                ) : existing_account && !authenticated_as_invitee ? (
                    <div className="space-y-4 text-center">
                        <p className="text-sm text-muted-foreground">
                            An account already exists for this email. Sign in to
                            accept safely.
                        </p>
                        <Button className="w-full" asChild>
                            <Link href="/login">
                                <LogIn className="size-4" />
                                Sign in to continue
                            </Link>
                        </Button>
                    </div>
                ) : authenticated_as_invitee ? (
                    <Form action={action} method="post" disableWhileProcessing>
                        {({ processing, errors }) => (
                            <>
                                <InputError
                                    message={errors.invitation}
                                    className="mb-3"
                                />
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Accept invitation
                                </Button>
                            </>
                        )}
                    </Form>
                ) : (
                    <Form
                        action={action}
                        method="post"
                        disableWhileProcessing
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Full name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="name"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        value={invitation.email}
                                        disabled
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        Create password
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        passwordrules={passwordRules}
                                    />
                                </div>
                                <InputError message={errors.invitation} />
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Create account and join team
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <p className="flex items-center justify-center gap-2 text-xs text-muted-foreground">
                    <ShieldCheck className="size-4" aria-hidden="true" />
                    The link is private, expiring and can only be used once.
                </p>
                <p className="flex items-center justify-center gap-2 text-xs text-muted-foreground">
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Your access remains scoped to this academy.
                </p>
            </div>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Accept academy invitation',
    description: 'Create your personal account or sign in to join the team.',
};
