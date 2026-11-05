import { Form, Head } from '@inertiajs/react';
import { Building2, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    defaults: {
        timezone: string;
        currency: string;
    };
};

export default function AcademyOnboarding({ defaults }: Props) {
    return (
        <>
            <Head title="Set up your academy" />
            <main className="grid min-h-screen bg-background lg:grid-cols-[0.9fr_1.1fr]">
                <section className="hidden bg-primary p-12 text-primary-foreground lg:flex lg:flex-col lg:justify-between">
                    <div className="flex items-center gap-3 text-lg font-semibold">
                        <span className="grid size-10 place-items-center rounded-xl bg-primary-foreground/10">
                            <AppLogoIcon className="size-6" />
                        </span>
                        Bootcademy
                    </div>
                    <div className="max-w-lg">
                        <Building2
                            className="mb-6 size-10"
                            aria-hidden="true"
                        />
                        <h1 className="text-4xl font-semibold tracking-tight">
                            Create a workspace that feels like your academy.
                        </h1>
                        <p className="mt-5 text-lg leading-8 text-primary-foreground/75">
                            Your programmes, terminology, team and branding
                            remain independent from every other academy on the
                            platform.
                        </p>
                    </div>
                    <p className="flex items-center gap-2 text-sm text-primary-foreground/70">
                        <ShieldCheck className="size-4" aria-hidden="true" />
                        Academy data is isolated by membership and server-side
                        policy.
                    </p>
                </section>

                <section className="flex items-center justify-center px-5 py-12 sm:px-8">
                    <div className="w-full max-w-xl">
                        <div className="mb-8">
                            <p className="text-sm font-medium text-primary">
                                Workspace setup
                            </p>
                            <h2 className="mt-2 text-3xl font-semibold tracking-tight">
                                Tell us about your academy
                            </h2>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                These details can represent a sports,
                                educational, language, music or any other
                                subscription-based academy.
                            </p>
                        </div>

                        <Form
                            action="/app/onboarding"
                            method="post"
                            disableWhileProcessing
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            Academy name
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            placeholder="e.g. Bright Futures Academy"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="academy_type">
                                            Academy type
                                        </Label>
                                        <Input
                                            id="academy_type"
                                            name="academy_type"
                                            required
                                            placeholder="e.g. Football, language, coding or music"
                                        />
                                        <InputError
                                            message={errors.academy_type}
                                        />
                                    </div>

                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="timezone">
                                                Timezone
                                            </Label>
                                            <Input
                                                id="timezone"
                                                name="timezone"
                                                required
                                                defaultValue={defaults.timezone}
                                            />
                                            <InputError
                                                message={errors.timezone}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="currency">
                                                Currency code
                                            </Label>
                                            <Input
                                                id="currency"
                                                name="currency"
                                                required
                                                maxLength={3}
                                                defaultValue={defaults.currency}
                                                className="uppercase"
                                            />
                                            <InputError
                                                message={errors.currency}
                                            />
                                        </div>
                                    </div>

                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Create academy workspace
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </section>
            </main>
        </>
    );
}
