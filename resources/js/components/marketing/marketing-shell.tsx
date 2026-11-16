import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Menu, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { dashboard, login, register } from '@/routes';

const navigation = [
    ['Product', '/academy-management-software'],
    ['Features', '/features'],
    ['For sports', '/sports-academy-management-software'],
    ['Free website', '/features/academy-website'],
    ['Pricing', '/#pricing'],
] as const;

export function MarketingBrand({ light = false }: { light?: boolean }) {
    return (
        <span className="flex items-center gap-2.5">
            <img
                src="/images/bootcademy-mark.png"
                alt=""
                className="size-11 rounded-xl object-cover"
                width="44"
                height="44"
            />
            <span className="text-xl font-black tracking-[-0.04em]">
                <span className={light ? 'text-white' : 'text-[#071D49]'}>
                    Boot
                </span>
                <span className="text-[#FF6900]">cademy</span>
            </span>
        </span>
    );
}

export default function MarketingShell({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const workspaceUrl = auth.user ? dashboard() : register();

    return (
        <div className="min-h-screen bg-white font-sans text-[#071D49]">
            <header className="sticky top-0 z-50 border-b border-[#071D49]/8 bg-white/95 backdrop-blur-xl">
                <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8">
                    <Link href="/" aria-label="Bootcademy home">
                        <MarketingBrand />
                    </Link>
                    <nav
                        className="hidden items-center gap-6 text-sm font-bold text-[#071D49]/65 lg:flex"
                        aria-label="Main navigation"
                    >
                        {navigation.map(([label, href]) => (
                            <Link
                                key={href}
                                href={href}
                                className="transition hover:text-[#0878D1]"
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                    <div className="hidden items-center gap-2 sm:flex">
                        {!auth.user && (
                            <Link
                                href={login()}
                                className="rounded-full px-4 py-3 text-sm font-black text-[#0878D1]"
                            >
                                Log in
                            </Link>
                        )}
                        <Link
                            href={workspaceUrl}
                            className="inline-flex items-center gap-2 rounded-full bg-[#FF6900] px-5 py-3 text-sm font-black text-white shadow-lg shadow-orange-500/20"
                        >
                            {auth.user ? 'Open dashboard' : 'Start now'}
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <button
                        type="button"
                        className="grid size-11 place-items-center rounded-xl border border-[#071D49]/10 sm:hidden"
                        aria-label={
                            menuOpen ? 'Close navigation' : 'Open navigation'
                        }
                        aria-expanded={menuOpen}
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        {menuOpen ? (
                            <X className="size-5" />
                        ) : (
                            <Menu className="size-5" />
                        )}
                    </button>
                </div>
                {menuOpen && (
                    <nav
                        className="border-t bg-white px-5 py-5 sm:hidden"
                        aria-label="Mobile navigation"
                    >
                        <div className="grid gap-1">
                            {navigation.map(([label, href]) => (
                                <Link
                                    key={href}
                                    href={href}
                                    onClick={() => setMenuOpen(false)}
                                    className="rounded-xl px-3 py-3 text-sm font-bold hover:bg-[#F3F7FB]"
                                >
                                    {label}
                                </Link>
                            ))}
                            <Link
                                href={login()}
                                className="mt-2 rounded-xl px-3 py-3 text-sm font-bold text-[#0878D1]"
                            >
                                Log in
                            </Link>
                            <Link
                                href={workspaceUrl}
                                className="mt-1 rounded-xl bg-[#FF6900] px-4 py-3 text-center text-sm font-black text-white"
                            >
                                {auth.user
                                    ? 'Open dashboard'
                                    : 'Create your account'}
                            </Link>
                        </div>
                    </nav>
                )}
            </header>

            {children}

            <footer className="bg-[#071D49] px-5 py-12 text-white sm:px-8">
                <div className="mx-auto grid max-w-7xl gap-10 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto]">
                    <div>
                        <MarketingBrand light />
                        <p className="mt-4 max-w-md text-sm leading-6 text-white/50">
                            Academy management software for websites,
                            applications, learners, programmes, branches,
                            payments and day-to-day operations.
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-black tracking-[0.12em] text-white/40 uppercase">
                            Product
                        </p>
                        <div className="mt-4 grid gap-3 text-sm text-white/65">
                            {navigation.slice(0, 4).map(([label, href]) => (
                                <Link
                                    key={href}
                                    href={href}
                                    className="hover:text-white"
                                >
                                    {label}
                                </Link>
                            ))}
                        </div>
                    </div>
                    <div>
                        <p className="text-xs font-black tracking-[0.12em] text-white/40 uppercase">
                            Account
                        </p>
                        <div className="mt-4 grid gap-3 text-sm text-white/65">
                            <Link href={login()} className="hover:text-white">
                                Log in
                            </Link>
                            <Link
                                href={register()}
                                className="hover:text-white"
                            >
                                Create account
                            </Link>
                            <Link href="/#pricing" className="hover:text-white">
                                Pricing
                            </Link>
                        </div>
                    </div>
                </div>
                <div className="mx-auto mt-10 max-w-7xl border-t border-white/10 pt-6 text-xs text-white/35">
                    © {new Date().getFullYear()} Bootcademy. Every academy. One
                    platform.
                </div>
            </footer>
        </div>
    );
}
