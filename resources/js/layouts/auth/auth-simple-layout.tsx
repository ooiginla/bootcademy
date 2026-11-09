import { Link } from '@inertiajs/react';
import { Check, ShieldCheck } from 'lucide-react';
import type { CSSProperties } from 'react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const authTheme = {
    '--background': '#F3F7FB',
    '--foreground': '#071D49',
    '--card': '#FFFFFF',
    '--card-foreground': '#071D49',
    '--popover': '#FFFFFF',
    '--popover-foreground': '#071D49',
    '--primary': '#FF6900',
    '--primary-foreground': '#FFFFFF',
    '--secondary': '#EAF5FD',
    '--secondary-foreground': '#071D49',
    '--muted': '#E8EEF5',
    '--muted-foreground': '#58708F',
    '--accent': '#EAF5FD',
    '--accent-foreground': '#071D49',
    '--border': '#D9E2EC',
    '--input': '#C8D5E3',
    '--ring': '#0878D1',
} as CSSProperties;

function BootcademyBrand({ light = false }: { light?: boolean }) {
    return (
        <span className="flex items-center gap-3">
            <img
                src="/images/bootcademy-mark.png"
                alt=""
                width="48"
                height="48"
                className="size-12 rounded-2xl object-cover shadow-lg shadow-black/10"
            />
            <span className="text-2xl font-black tracking-[-0.045em]">
                <span className={light ? 'text-white' : 'text-[#071D49]'}>
                    Boot
                </span>
                <span className="text-[#FF6900]">cademy</span>
            </span>
        </span>
    );
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div
            data-testid="bootcademy-auth-shell"
            style={authTheme}
            className="min-h-svh bg-[#F3F7FB] text-[#071D49]"
        >
            <div className="grid min-h-svh lg:grid-cols-[minmax(380px,0.82fr)_minmax(560px,1.18fr)]">
                <aside className="relative hidden min-h-svh overflow-hidden bg-[#071D49] px-10 py-10 text-white lg:flex lg:flex-col xl:px-14 xl:py-12">
                    <div
                        aria-hidden="true"
                        className="absolute -top-32 -right-32 size-96 rounded-full border-[72px] border-[#0878D1]/20"
                    />
                    <div
                        aria-hidden="true"
                        className="absolute -bottom-36 -left-28 size-96 rounded-full bg-[#12B8B0]/12 blur-3xl"
                    />
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 [background-image:linear-gradient(to_right,#fff_1px,transparent_1px),linear-gradient(to_bottom,#fff_1px,transparent_1px)] [background-size:44px_44px] opacity-[0.06]"
                    />

                    <Link
                        href={home()}
                        aria-label="Bootcademy home"
                        className="relative z-10 w-fit"
                    >
                        <BootcademyBrand light />
                    </Link>

                    <div className="relative z-10 my-auto max-w-lg py-16">
                        <p className="text-xs font-black tracking-[0.18em] text-[#12B8B0] uppercase">
                            Every academy. One platform.
                        </p>
                        <h2 className="mt-5 text-4xl leading-[1.06] font-black tracking-[-0.05em] text-balance xl:text-5xl">
                            Your academy operations, connected.
                        </h2>
                        <p className="mt-6 max-w-md text-base leading-7 text-white/65">
                            Manage people, programmes, attendance, fees,
                            payments and branches from one secure workspace.
                        </p>

                        <ul className="mt-9 grid gap-4 text-sm font-semibold text-white/80">
                            {[
                                'One clear view across your academy',
                                'Role-based access for your whole team',
                                'Records protected within your academy',
                            ].map((item) => (
                                <li
                                    key={item}
                                    className="flex items-center gap-3"
                                >
                                    <span className="grid size-7 shrink-0 place-items-center rounded-full bg-[#12B8B0]/15 text-[#5CE1DA]">
                                        <Check
                                            className="size-3.5"
                                            strokeWidth={3}
                                            aria-hidden="true"
                                        />
                                    </span>
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative z-10 flex items-center gap-2 text-xs text-white/45">
                        <ShieldCheck className="size-4 text-[#12B8B0]" />
                        Secure, tenant-isolated academy management
                    </p>
                </aside>

                <main className="relative flex min-h-svh items-start justify-center overflow-hidden px-5 py-8 sm:px-8 sm:py-12 lg:items-center lg:px-12 lg:py-16">
                    <div
                        aria-hidden="true"
                        className="absolute top-0 right-0 size-72 translate-x-1/2 -translate-y-1/2 rounded-full bg-[#0878D1]/8 blur-3xl"
                    />
                    <div className="relative z-10 w-full max-w-lg">
                        <Link
                            href={home()}
                            aria-label="Bootcademy home"
                            className="mb-10 block w-fit lg:hidden"
                        >
                            <BootcademyBrand />
                        </Link>

                        <div className="rounded-[1.75rem] border border-[#071D49]/8 bg-white p-6 shadow-2xl shadow-[#071D49]/8 sm:p-9 lg:p-10">
                            <div className="mb-8">
                                <span className="inline-flex rounded-full bg-[#EAF5FD] px-3 py-1.5 text-[11px] font-black tracking-[0.12em] text-[#0878D1] uppercase">
                                    Bootcademy account
                                </span>
                                <h1 className="mt-4 text-3xl leading-tight font-black tracking-[-0.04em] text-[#071D49]">
                                    {title}
                                </h1>
                                <p className="mt-2 text-sm leading-6 text-[#58708F]">
                                    {description}
                                </p>
                            </div>

                            <div className="[&_a]:text-[#0878D1] [&_button[type='submit']]:min-h-11 [&_button[type='submit']]:rounded-xl [&_input]:min-h-11 [&_input]:rounded-xl">
                                {children}
                            </div>
                        </div>

                        <p className="mt-6 text-center text-xs leading-5 text-[#58708F]">
                            By continuing, you are accessing Bootcademy’s secure
                            academy management platform.
                        </p>
                    </div>
                </main>
            </div>
        </div>
    );
}
