import { Head } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronDown,
    GraduationCap,
    Mail,
    MapPin,
    Menu,
    MessageCircle,
    Phone,
    X,
} from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { useState } from 'react';
import type { PublicAcademy, PublicSite } from '@/types/public-site';
import { parseRows, resolveSiteUrl, safeUrl } from './utils';

const menuSections = [
    ['about', 'about'],
    ['programmes', 'programmes'],
    ['store', 'store'],
    ['locations', 'locations'],
    ['experience', 'experience'],
    ['welcome', 'welcome'],
    ['events', 'events'],
    ['news', 'news'],
    ['statistics', 'statistics'],
    ['team', 'team'],
    ['testimonials', 'testimonials'],
    ['alumni', 'alumni'],
    ['gallery', 'gallery'],
    ['affiliations', 'partners'],
    ['sponsors', 'partners'],
    ['faq', 'faq'],
] as const;

export default function PublicSiteShell({
    academy,
    site,
    baseUrl,
    title,
    children,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    baseUrl: string;
    title?: string;
    children: ReactNode;
}) {
    const [menuOpen, setMenuOpen] = useState(false);
    const { sections } = site;
    const topbar = sections.topbar;
    const announcement = sections.announcement;
    const header = sections.header;
    const footer = sections.footer;
    const whatsapp = sections.whatsapp;
    const socials = parseRows(topbar?.socials, 2);
    const quickLinks = parseRows(topbar?.quick_links, 2);
    const contactPhone = String(topbar?.phone ?? '');
    const contactEmail = String(topbar?.email ?? '');
    const whatsappPhone = String(whatsapp?.phone || contactPhone).replace(
        /\D/g,
        '',
    );
    const whatsappUrl = whatsappPhone
        ? `https://wa.me/${whatsappPhone}?text=${encodeURIComponent(String(whatsapp?.message ?? ''))}`
        : null;
    const homeUrl = baseUrl || '/';
    const enabledMenuItems = menuSections
        .filter(([key]) => sections[key]?.enabled === true)
        .map(([key, anchor]) => ({
            key,
            label: String(sections[key]?.title ?? key),
            href:
                key === 'programmes' || key === 'store'
                    ? `${baseUrl}/${key}`
                    : `${homeUrl === '/' ? '' : homeUrl}/#${anchor}`,
        }));
    const primaryMenuItems = enabledMenuItems.slice(0, 4);
    const secondaryMenuItems = enabledMenuItems.slice(4);
    const style = {
        '--site-primary': academy.theme.primary,
        '--site-primary-fg': academy.theme.primary_foreground,
        '--site-secondary': academy.theme.secondary,
        '--site-secondary-fg': academy.theme.secondary_foreground,
    } as CSSProperties;

    return (
        <div
            style={style}
            className="min-h-screen bg-[#f7f7f2] font-sans text-slate-950"
        >
            <Head title={title ?? academy.name}>
                {academy.logo_url && (
                    <link rel="icon" href={academy.logo_url} />
                )}
            </Head>

            {topbar?.enabled && (
                <div className="bg-slate-950 text-white">
                    <div className="mx-auto flex min-h-10 max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 py-2 text-xs sm:px-8">
                        <div className="flex flex-wrap items-center gap-4 text-white/75">
                            {contactPhone && (
                                <a
                                    href={`tel:${contactPhone}`}
                                    className="flex items-center gap-1.5 hover:text-white"
                                >
                                    <Phone className="size-3.5" />
                                    {contactPhone}
                                </a>
                            )}
                            {contactEmail && (
                                <a
                                    href={`mailto:${contactEmail}`}
                                    className="flex items-center gap-1.5 hover:text-white"
                                >
                                    <Mail className="size-3.5" />
                                    {contactEmail}
                                </a>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center gap-4">
                            {socials.map(([label, url]) => (
                                <a
                                    key={`${label}-${url}`}
                                    href={safeUrl(url)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-white/65 hover:text-white"
                                >
                                    {label}
                                </a>
                            ))}
                            {quickLinks.map(([label, url]) => (
                                <a
                                    key={`${label}-${url}`}
                                    href={resolveSiteUrl(baseUrl, url)}
                                    className="font-medium text-white hover:underline"
                                >
                                    {label}
                                </a>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {announcement?.enabled && announcement.text && (
                <div className="overflow-hidden bg-[var(--site-primary)] py-2.5 text-[var(--site-primary-fg)]">
                    <div className="bootcademy-marquee flex w-max items-center gap-12 text-xs font-semibold tracking-[0.13em] whitespace-nowrap uppercase">
                        {[0, 1, 2].map((copy) => (
                            <span
                                key={copy}
                                className="flex items-center gap-12"
                            >
                                <span>{String(announcement.text)}</span>
                                <span aria-hidden="true">✦</span>
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {header?.enabled && (
                <header className="sticky top-0 z-40 border-b border-black/5 bg-[#f7f7f2]/95 backdrop-blur-xl">
                    <div className="mx-auto flex h-24 max-w-7xl items-center justify-between gap-5 px-5 sm:px-8">
                        <a
                            href={homeUrl}
                            className="flex min-w-0 items-center gap-3"
                            aria-label={`${academy.name} home`}
                        >
                            {academy.logo_url ? (
                                <img
                                    src={academy.logo_url}
                                    alt=""
                                    className="size-16 rounded-2xl object-contain"
                                />
                            ) : (
                                <span className="grid size-16 place-items-center rounded-2xl bg-[var(--site-primary)] text-[var(--site-primary-fg)]">
                                    <GraduationCap className="size-8" />
                                </span>
                            )}
                            <span className="max-w-52 truncate text-xl font-extrabold tracking-tight sm:max-w-72 sm:text-2xl">
                                {academy.name}
                            </span>
                        </a>

                        <nav
                            className="hidden items-center gap-6 lg:flex"
                            aria-label="Main navigation"
                        >
                            {primaryMenuItems.map((item) => (
                                <a
                                    key={item.key}
                                    href={item.href}
                                    className="text-sm font-medium text-slate-600 transition hover:text-slate-950"
                                >
                                    {item.label}
                                </a>
                            ))}
                            {secondaryMenuItems.length > 0 && (
                                <details className="group relative">
                                    <summary className="flex cursor-pointer list-none items-center gap-1.5 text-sm font-medium text-slate-600 transition hover:text-slate-950">
                                        Explore
                                        <ChevronDown className="size-4 transition group-open:rotate-180" />
                                    </summary>
                                    <div className="absolute top-full right-0 z-50 mt-4 grid min-w-60 gap-1 rounded-2xl border border-black/5 bg-white p-2 shadow-2xl">
                                        {secondaryMenuItems.map((item) => (
                                            <a
                                                key={item.key}
                                                href={item.href}
                                                className="rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950"
                                            >
                                                {item.label}
                                            </a>
                                        ))}
                                    </div>
                                </details>
                            )}
                        </nav>

                        <div className="flex items-center gap-2">
                            <a
                                href={`${baseUrl}/apply`}
                                className="hidden items-center gap-2 rounded-full bg-[var(--site-primary)] px-5 py-2.5 text-sm font-bold text-[var(--site-primary-fg)] shadow-sm transition hover:-translate-y-0.5 sm:flex"
                            >
                                {String(
                                    header.application_label ?? 'Apply now',
                                )}
                                <ArrowUpRight className="size-4" />
                            </a>
                            <button
                                type="button"
                                className="grid size-10 place-items-center rounded-full border lg:hidden"
                                onClick={() => setMenuOpen((open) => !open)}
                                aria-expanded={menuOpen}
                                aria-label="Toggle navigation"
                            >
                                {menuOpen ? (
                                    <X className="size-5" />
                                ) : (
                                    <Menu className="size-5" />
                                )}
                            </button>
                        </div>
                    </div>
                    {menuOpen && (
                        <nav
                            className="border-t bg-[#f7f7f2] px-5 py-5 lg:hidden"
                            aria-label="Mobile navigation"
                        >
                            <div className="mx-auto grid max-w-7xl gap-1">
                                {primaryMenuItems.map((item) => (
                                    <a
                                        key={item.key}
                                        href={item.href}
                                        className="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-black/5"
                                    >
                                        {item.label}
                                    </a>
                                ))}
                                {secondaryMenuItems.length > 0 && (
                                    <details className="group rounded-xl border border-black/5">
                                        <summary className="flex cursor-pointer list-none items-center justify-between px-3 py-2.5 text-sm font-semibold">
                                            Explore
                                            <ChevronDown className="size-4 transition group-open:rotate-180" />
                                        </summary>
                                        <div className="grid gap-1 border-t p-2">
                                            {secondaryMenuItems.map((item) => (
                                                <a
                                                    key={item.key}
                                                    href={item.href}
                                                    className="rounded-lg px-3 py-2.5 text-sm text-slate-600 hover:bg-black/5 hover:text-slate-950"
                                                >
                                                    {item.label}
                                                </a>
                                            ))}
                                        </div>
                                    </details>
                                )}
                                <a
                                    href={`${baseUrl}/apply`}
                                    className="mt-2 rounded-lg bg-[var(--site-primary)] px-3 py-3 text-center text-sm font-bold text-[var(--site-primary-fg)]"
                                >
                                    {String(
                                        header.application_label ?? 'Apply now',
                                    )}
                                </a>
                            </div>
                        </nav>
                    )}
                </header>
            )}

            {children}

            {footer?.enabled && (
                <footer className="bg-slate-950 text-white">
                    <div className="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:px-8 md:grid-cols-[1.2fr_0.8fr_0.8fr]">
                        <div>
                            <div className="flex items-center gap-3">
                                {academy.logo_url ? (
                                    <img
                                        src={academy.logo_url}
                                        alt=""
                                        className="size-16 rounded-2xl bg-white object-contain p-1"
                                    />
                                ) : (
                                    <span className="grid size-16 place-items-center rounded-2xl bg-[var(--site-primary)] text-[var(--site-primary-fg)]">
                                        <GraduationCap className="size-8" />
                                    </span>
                                )}
                                <span className="text-xl font-bold sm:text-2xl">
                                    {academy.name}
                                </span>
                            </div>
                            <p className="mt-5 max-w-md text-sm leading-7 text-white/60">
                                {String(footer.summary ?? '')}
                            </p>
                        </div>
                        <div>
                            <h2 className="text-sm font-bold tracking-wider uppercase">
                                Quick links
                            </h2>
                            <div className="mt-5 grid gap-3 text-sm text-white/60">
                                {quickLinks.map(([label, url]) => (
                                    <a
                                        key={`${label}-${url}`}
                                        href={resolveSiteUrl(baseUrl, url)}
                                        className="hover:text-white"
                                    >
                                        {label}
                                    </a>
                                ))}
                                <a
                                    href={`${baseUrl}/programmes`}
                                    className="hover:text-white"
                                >
                                    {String(
                                        sections.programmes?.title ??
                                            'Programmes',
                                    )}
                                </a>
                            </div>
                        </div>
                        <div>
                            <h2 className="text-sm font-bold tracking-wider uppercase">
                                Contact
                            </h2>
                            <div className="mt-5 grid gap-3 text-sm text-white/60">
                                {contactPhone && (
                                    <a
                                        href={`tel:${contactPhone}`}
                                        className="flex items-start gap-2 hover:text-white"
                                    >
                                        <Phone className="mt-0.5 size-4" />
                                        {contactPhone}
                                    </a>
                                )}
                                {contactEmail && (
                                    <a
                                        href={`mailto:${contactEmail}`}
                                        className="flex items-start gap-2 hover:text-white"
                                    >
                                        <Mail className="mt-0.5 size-4" />
                                        {contactEmail}
                                    </a>
                                )}
                                <span className="flex items-start gap-2">
                                    <MapPin className="mt-0.5 size-4" />
                                    Visit our locations page for directions.
                                </span>
                            </div>
                        </div>
                    </div>
                    <div className="border-t border-white/10">
                        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-5 py-5 text-xs text-white/45 sm:px-8">
                            <span>
                                {String(footer.copyright) ||
                                    `© ${new Date().getFullYear()} ${academy.name}.`}
                            </span>
                            <span>
                                Website powered by{' '}
                                <strong className="text-white/75">
                                    Bootcademy
                                </strong>
                            </span>
                        </div>
                    </div>
                </footer>
            )}

            {whatsapp?.enabled && whatsappUrl && (
                <div className="fixed right-5 bottom-5 z-50 flex items-center gap-3 sm:right-7 sm:bottom-7">
                    <span className="hidden rounded-full bg-white px-4 py-2 text-xs font-bold shadow-xl sm:block">
                        {String(whatsapp.label ?? 'Chat with us')}
                    </span>
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        aria-label="Chat with the academy on WhatsApp"
                        className="relative grid size-14 place-items-center rounded-full bg-[#25D366] text-white shadow-xl"
                    >
                        <span className="absolute inset-0 -z-10 animate-ping rounded-full bg-[#25D366]/45" />
                        <span className="absolute -inset-2 -z-20 rounded-full border border-[#25D366]/35" />
                        <MessageCircle className="size-7" />
                    </a>
                </div>
            )}
        </div>
    );
}
