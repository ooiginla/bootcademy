import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Building2,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const capabilities = [
    {
        title: 'One clear workspace',
        text: 'See learners, programmes, attendance and finances without spreadsheet confusion.',
        icon: BarChart3,
    },
    {
        title: 'Built for every academy',
        text: 'Manage one or many branches across sports, education, language and skills training.',
        icon: Building2,
    },
    {
        title: 'Privacy by design',
        text: 'Role-based access and strict academy boundaries protect every learner and family.',
        icon: ShieldCheck,
    },
] as const;

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Academy operations, in one place" />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8">
                    <Link
                        href="/"
                        className="flex items-center gap-3 font-semibold"
                        aria-label={`${name} home`}
                    >
                        <span className="grid size-9 place-items-center rounded-xl bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-5" />
                        </span>
                        <span>{name}</span>
                    </Link>
                    <nav
                        className="flex items-center gap-2"
                        aria-label="Account navigation"
                    >
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Open dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:py-24">
                        <div>
                            <p className="mb-5 inline-flex items-center rounded-full border bg-card px-3 py-1 text-xs font-medium text-muted-foreground shadow-xs">
                                One platform for your whole academy
                            </p>
                            <h1 className="max-w-3xl text-4xl font-semibold tracking-[-0.04em] sm:text-5xl lg:text-6xl">
                                Run your academy with clarity and confidence.
                            </h1>
                            <p className="mt-6 max-w-2xl text-lg leading-8 text-muted-foreground">
                                A secure operational centre for
                                subscription-based academies of every
                                kind—designed for owners, staff, coaches,
                                teachers and families.
                            </p>
                            {!auth.user && (
                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Button size="lg" asChild>
                                        <Link href={register()}>
                                            Create your account
                                            <ArrowRight
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </Button>
                                    <Button size="lg" variant="outline" asChild>
                                        <Link href={login()}>Log in</Link>
                                    </Button>
                                </div>
                            )}
                        </div>

                        <div className="relative overflow-hidden rounded-[2rem] border bg-card p-5 shadow-xl shadow-primary/5 sm:p-7">
                            <div
                                className="absolute -top-24 -right-20 size-64 rounded-full bg-primary/15 blur-3xl"
                                aria-hidden="true"
                            />
                            <div className="relative rounded-2xl bg-primary p-6 text-primary-foreground sm:p-8">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-primary-foreground/70">
                                            Academy overview
                                        </p>
                                        <p className="mt-1 text-xl font-semibold">
                                            Everything important, together
                                        </p>
                                    </div>
                                    <UsersRound
                                        className="size-7 text-primary-foreground/80"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="mt-8 grid grid-cols-2 gap-3">
                                    {[
                                        'Learners',
                                        'Attendance',
                                        'Programmes',
                                        'Finances',
                                    ].map((label) => (
                                        <div
                                            key={label}
                                            className="rounded-xl border border-white/10 bg-white/10 p-4"
                                        >
                                            <div className="h-2 w-14 rounded-full bg-white/20" />
                                            <p className="mt-5 text-sm font-medium">
                                                {label}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        className="mx-auto max-w-7xl px-5 pb-20 sm:px-8 lg:pb-28"
                        aria-labelledby="capabilities-heading"
                    >
                        <h2 id="capabilities-heading" className="sr-only">
                            Platform capabilities
                        </h2>
                        <div className="grid gap-4 md:grid-cols-3">
                            {capabilities.map((item) => (
                                <article
                                    key={item.title}
                                    className="rounded-2xl border bg-card p-6 shadow-xs"
                                >
                                    <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <item.icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <h3 className="mt-5 font-semibold">
                                        {item.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {item.text}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
