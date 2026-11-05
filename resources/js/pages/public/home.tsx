import {
    ArrowRight,
    Award,
    BookOpen,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Compass,
    Lightbulb,
    Mail,
    MapPin,
    Phone,
    Quote,
    Sparkles,
    Target,
    UsersRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import AnimatedStatistic from '@/components/public-site/animated-statistic';
import {
    initials,
    parseRows,
    resolveSiteUrl,
    safeUrl,
} from '@/components/public-site/utils';
import type {
    PublicAcademy,
    PublicProgramme,
    PublicSection,
    PublicSite,
} from '@/types/public-site';

type Location = {
    id: number;
    name: string;
    address: string;
    email: string | null;
    phone: string | null;
};

function SectionHeading({
    section,
    intro,
    align = 'left',
}: {
    section: PublicSection;
    intro?: string;
    align?: 'left' | 'center';
}) {
    return (
        <div
            className={
                align === 'center' ? 'mx-auto max-w-2xl text-center' : ''
            }
        >
            <p className="text-xs font-extrabold tracking-[0.2em] text-[var(--site-primary)] uppercase">
                {section.title}
            </p>
            <h2 className="mt-3 text-3xl font-black tracking-[-0.035em] text-balance sm:text-4xl">
                {section.title}
            </h2>
            {intro && (
                <p className="mt-4 text-base leading-7 text-slate-600">
                    {intro}
                </p>
            )}
        </div>
    );
}

function ImageOrInitial({
    image,
    name,
    className,
}: {
    image: string;
    name: string;
    className: string;
}) {
    return image && /^https?:\/\//i.test(image) ? (
        <img src={image} alt="" className={`${className} object-cover`} />
    ) : (
        <div
            className={`${className} grid place-items-center bg-[var(--site-secondary)] text-2xl font-black text-[var(--site-secondary-fg)]`}
        >
            {initials(name)}
        </div>
    );
}

function LogoStrip({
    section,
    items,
    anchorId,
}: {
    section: PublicSection;
    items: string[][];
    anchorId?: string;
}) {
    if (!section.enabled || items.length === 0) return null;

    return (
        <div id={anchorId}>
            <p className="text-center text-xs font-bold tracking-[0.18em] text-slate-500 uppercase">
                {section.title}
            </p>
            <div className="mt-7 flex flex-wrap items-center justify-center gap-4">
                {items.map(([name, logo, url], index) => (
                    <a
                        key={`${name}-${index}`}
                        href={safeUrl(url)}
                        target="_blank"
                        rel="noreferrer"
                        className="grid h-20 min-w-40 place-items-center rounded-2xl border bg-white px-5 shadow-sm"
                    >
                        {/^https?:\/\//i.test(logo) ? (
                            <img
                                src={logo}
                                alt={name}
                                className="max-h-11 max-w-32 object-contain"
                            />
                        ) : (
                            <span className="font-bold">{name}</span>
                        )}
                    </a>
                ))}
            </div>
        </div>
    );
}

export default function PublicHome({
    academy,
    site,
    base_url,
    programmes,
    locations,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    programmes: PublicProgramme[];
    locations: Location[];
}) {
    const { sections } = site;
    const hero = sections.hero;
    const heroSlides = parseRows(hero?.slides, 5);
    const slides = heroSlides.length
        ? heroSlides
        : [
              [
                  String(hero?.headline ?? ''),
                  String(hero?.description ?? ''),
                  '',
                  'Explore programmes',
                  '/programmes',
              ],
          ];
    const [activeSlide, setActiveSlide] = useState(0);

    useEffect(() => {
        if (slides.length < 2) return;
        const timer = window.setInterval(
            () => setActiveSlide((current) => (current + 1) % slides.length),
            6500,
        );
        return () => window.clearInterval(timer);
    }, [slides.length]);

    const slide = slides[activeSlide] ?? slides[0];
    const experienceItems = parseRows(sections.experience?.items, 2);
    const events = parseRows(sections.events?.items, 4);
    const news = parseRows(sections.news?.items, 5);
    const statistics = parseRows(sections.statistics?.items, 2);
    const team = parseRows(sections.team?.items, 4);
    const testimonials = parseRows(sections.testimonials?.items, 3);
    const alumni = parseRows(sections.alumni?.items, 3);
    const gallery = parseRows(sections.gallery?.items, 2);
    const affiliations = parseRows(sections.affiliations?.items, 3);
    const sponsors = parseRows(sections.sponsors?.items, 3);
    const faqs = parseRows(sections.faq?.items, 2);

    return (
        <PublicSiteShell academy={academy} site={site} baseUrl={base_url}>
            <main>
                {hero?.enabled && (
                    <section className="relative isolate overflow-hidden bg-slate-950 text-white">
                        {hero.media_type === 'video' && hero.video_url ? (
                            <video
                                src={safeUrl(String(hero.video_url), '')}
                                autoPlay
                                muted
                                loop
                                playsInline
                                className="absolute inset-0 -z-20 size-full object-cover opacity-45"
                            />
                        ) : slide[2] && /^https?:\/\//i.test(slide[2]) ? (
                            <img
                                src={slide[2]}
                                alt=""
                                className="absolute inset-0 -z-20 size-full object-cover opacity-45"
                            />
                        ) : null}
                        <div className="absolute inset-0 -z-10 bg-[linear-gradient(100deg,rgba(2,6,23,.97)_10%,rgba(2,6,23,.72)_60%,rgba(2,6,23,.3))]" />
                        <div
                            className="absolute -top-28 right-0 -z-10 size-[32rem] rounded-full opacity-30 blur-3xl"
                            style={{ backgroundColor: academy.theme.primary }}
                        />
                        <div className="mx-auto grid min-h-[680px] max-w-7xl items-center gap-12 px-5 py-20 sm:px-8 lg:grid-cols-[1fr_0.72fr]">
                            <div className="max-w-3xl">
                                {hero.eyebrow && (
                                    <p className="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-bold tracking-[0.16em] uppercase backdrop-blur">
                                        <Sparkles className="size-3.5 text-[var(--site-primary)]" />
                                        {String(hero.eyebrow)}
                                    </p>
                                )}
                                <h1 className="text-5xl leading-[0.98] font-black tracking-[-0.055em] text-balance sm:text-6xl lg:text-7xl">
                                    {slide[0] || String(hero.headline)}
                                </h1>
                                <p className="mt-7 max-w-2xl text-lg leading-8 text-white/70 sm:text-xl">
                                    {slide[1] || String(hero.description)}
                                </p>
                                <div className="mt-9 flex flex-wrap gap-3">
                                    <a
                                        href={resolveSiteUrl(
                                            base_url,
                                            slide[4] || '/programmes',
                                        )}
                                        className="inline-flex items-center gap-2 rounded-full bg-[var(--site-primary)] px-6 py-3.5 text-sm font-extrabold text-[var(--site-primary-fg)] transition hover:-translate-y-0.5"
                                    >
                                        {slide[3] || 'Explore programmes'}
                                        <ArrowRight className="size-4" />
                                    </a>
                                    <a
                                        href={`${base_url}/apply`}
                                        className="inline-flex items-center gap-2 rounded-full border border-white/25 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/10"
                                    >
                                        Start an application
                                    </a>
                                </div>
                                {slides.length > 1 && (
                                    <div className="mt-12 flex items-center gap-3">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setActiveSlide(
                                                    (activeSlide -
                                                        1 +
                                                        slides.length) %
                                                        slides.length,
                                                )
                                            }
                                            aria-label="Previous slide"
                                            className="grid size-10 place-items-center rounded-full border border-white/20 hover:bg-white/10"
                                        >
                                            <ChevronLeft className="size-4" />
                                        </button>
                                        <div className="flex gap-2">
                                            {slides.map((item, index) => (
                                                <button
                                                    key={`${item[0]}-${index}`}
                                                    type="button"
                                                    onClick={() =>
                                                        setActiveSlide(index)
                                                    }
                                                    aria-label={`Show slide ${index + 1}`}
                                                    className={`h-1.5 rounded-full transition-all ${index === activeSlide ? 'w-8 bg-[var(--site-primary)]' : 'w-3 bg-white/30'}`}
                                                />
                                            ))}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setActiveSlide(
                                                    (activeSlide + 1) %
                                                        slides.length,
                                                )
                                            }
                                            aria-label="Next slide"
                                            className="grid size-10 place-items-center rounded-full border border-white/20 hover:bg-white/10"
                                        >
                                            <ChevronRight className="size-4" />
                                        </button>
                                    </div>
                                )}
                            </div>
                            <div className="hidden lg:block">
                                <div className="ml-auto max-w-sm rounded-[2rem] border border-white/15 bg-white/10 p-6 backdrop-blur-xl">
                                    <div className="grid size-12 place-items-center rounded-2xl bg-[var(--site-primary)] text-[var(--site-primary-fg)]">
                                        <Compass className="size-6" />
                                    </div>
                                    <p className="mt-12 text-xs font-bold tracking-[0.15em] text-white/50 uppercase">
                                        Find your pathway
                                    </p>
                                    <p className="mt-3 text-2xl font-bold">
                                        Explore programmes built for meaningful
                                        progress.
                                    </p>
                                    <a
                                        href={`${base_url}/programmes`}
                                        className="mt-7 flex items-center justify-between border-t border-white/15 pt-5 text-sm font-bold"
                                    >
                                        View all programmes
                                        <ArrowRight className="size-4" />
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                {sections.about?.enabled && (
                    <section id="about" className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
                            <div className="lg:sticky lg:top-28">
                                <SectionHeading
                                    section={sections.about}
                                    intro={String(sections.about.body ?? '')}
                                />
                                {sections.about.motto && (
                                    <blockquote className="mt-8 border-l-4 border-[var(--site-primary)] pl-5 text-xl leading-8 font-bold">
                                        “{String(sections.about.motto)}”
                                    </blockquote>
                                )}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {[
                                    {
                                        label: 'Mission',
                                        text: sections.about.mission,
                                        Icon: Target,
                                    },
                                    {
                                        label: 'Vision',
                                        text: sections.about.vision,
                                        Icon: Lightbulb,
                                    },
                                ].map(({ label, text, Icon }) => (
                                    <article
                                        key={String(label)}
                                        className="rounded-3xl border border-black/5 bg-white p-7 shadow-sm"
                                    >
                                        <span className="grid size-11 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                                            <Icon className="size-5" />
                                        </span>
                                        <h3 className="mt-6 text-xl font-bold">
                                            {String(label)}
                                        </h3>
                                        <p className="mt-3 text-sm leading-7 text-slate-600">
                                            {String(text ?? '')}
                                        </p>
                                    </article>
                                ))}
                                <article className="rounded-3xl bg-slate-950 p-7 text-white sm:col-span-2">
                                    <div className="flex items-center gap-3">
                                        <CheckCircle2 className="size-5 text-[var(--site-primary)]" />
                                        <h3 className="text-xl font-bold">
                                            Our objectives
                                        </h3>
                                    </div>
                                    <ul className="mt-6 grid gap-4 sm:grid-cols-2">
                                        {String(sections.about.objectives ?? '')
                                            .split('\n')
                                            .map((item) => item.trim())
                                            .filter(Boolean)
                                            .map((item) => (
                                                <li
                                                    key={item}
                                                    className="flex gap-3 text-sm leading-6 text-white/70"
                                                >
                                                    <span className="mt-2 size-1.5 shrink-0 rounded-full bg-[var(--site-primary)]" />
                                                    {item}
                                                </li>
                                            ))}
                                    </ul>
                                </article>
                            </div>
                        </div>
                    </section>
                )}

                {sections.welcome?.enabled && (
                    <section
                        id="welcome"
                        className="bg-[var(--site-secondary)] px-5 py-20 text-[var(--site-secondary-fg)] sm:px-8 lg:py-24"
                    >
                        <div className="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[0.72fr_1.28fr]">
                            <div className="relative mx-auto w-full max-w-sm">
                                <ImageOrInitial
                                    image={String(
                                        sections.welcome.image_url ?? '',
                                    )}
                                    name={String(sections.welcome.name ?? '')}
                                    className="aspect-[4/5] w-full rounded-[2rem]"
                                />
                                <span className="absolute -right-3 -bottom-3 grid size-20 place-items-center rounded-full bg-[var(--site-primary)] text-[var(--site-primary-fg)] shadow-xl">
                                    <Quote className="size-8" />
                                </span>
                            </div>
                            <div>
                                <p className="text-xs font-extrabold tracking-[0.2em] uppercase opacity-60">
                                    {sections.welcome.title}
                                </p>
                                <blockquote className="mt-5 text-2xl leading-10 font-bold tracking-tight sm:text-3xl sm:leading-12">
                                    “{String(sections.welcome.message ?? '')}”
                                </blockquote>
                                <div className="mt-7 border-t border-current/15 pt-5">
                                    <p className="font-extrabold">
                                        {String(sections.welcome.name ?? '')}
                                    </p>
                                    <p className="mt-1 text-sm opacity-65">
                                        {String(sections.welcome.role ?? '')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                {sections.programmes?.enabled && (
                    <section
                        id="programmes"
                        className="px-5 py-20 sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="flex flex-wrap items-end justify-between gap-6">
                                <SectionHeading
                                    section={sections.programmes}
                                    intro={String(
                                        sections.programmes.intro ?? '',
                                    )}
                                />
                                <a
                                    href={`${base_url}/programmes`}
                                    className="inline-flex items-center gap-2 text-sm font-extrabold text-[var(--site-primary)]"
                                >
                                    View all programmes
                                    <ArrowRight className="size-4" />
                                </a>
                            </div>
                            {programmes.length ? (
                                <div className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                    {programmes
                                        .slice(0, 6)
                                        .map((programme, index) => (
                                            <a
                                                key={programme.id}
                                                href={programme.url}
                                                className="group relative overflow-hidden rounded-3xl border border-black/5 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                                            >
                                                <span className="text-xs font-black tracking-[0.16em] text-[var(--site-primary)] uppercase">
                                                    {programme.code ||
                                                        `Programme ${String(index + 1).padStart(2, '0')}`}
                                                </span>
                                                <h3 className="mt-12 text-2xl font-black tracking-tight">
                                                    {programme.name}
                                                </h3>
                                                <p className="mt-3 line-clamp-3 text-sm leading-7 text-slate-600">
                                                    {programme.description ||
                                                        'Explore this programme, available cycles and learning locations.'}
                                                </p>
                                                <span className="mt-7 flex items-center gap-2 text-sm font-bold">
                                                    Programme details
                                                    <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                                </span>
                                            </a>
                                        ))}
                                </div>
                            ) : (
                                <p className="mt-10 rounded-3xl border border-dashed p-10 text-center text-slate-500">
                                    Programme information will be published here
                                    soon.
                                </p>
                            )}
                        </div>
                    </section>
                )}

                {sections.locations?.enabled && (
                    <section
                        id="locations"
                        className="bg-slate-950 px-5 py-20 text-white sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl">
                            <SectionHeading
                                section={sections.locations}
                                intro={String(sections.locations.intro ?? '')}
                            />
                            {locations.length ? (
                                <div className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                    {locations.map((location, index) => (
                                        <article
                                            key={location.id}
                                            className="rounded-3xl border border-white/10 bg-white/5 p-7"
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <span className="grid size-12 place-items-center rounded-2xl bg-[var(--site-primary)] text-[var(--site-primary-fg)]">
                                                    <MapPin className="size-5" />
                                                </span>
                                                <span className="text-xs font-bold text-white/35">
                                                    {String(index + 1).padStart(
                                                        2,
                                                        '0',
                                                    )}
                                                </span>
                                            </div>
                                            <h3 className="mt-8 text-xl font-bold">
                                                {location.name}
                                            </h3>
                                            <p className="mt-3 min-h-12 text-sm leading-6 text-white/55">
                                                {location.address ||
                                                    'Contact the academy for directions.'}
                                            </p>
                                            <div className="mt-6 grid gap-2 border-t border-white/10 pt-5 text-xs text-white/60">
                                                {location.phone && (
                                                    <a
                                                        href={`tel:${location.phone}`}
                                                        className="flex items-center gap-2 hover:text-white"
                                                    >
                                                        <Phone className="size-3.5" />
                                                        {location.phone}
                                                    </a>
                                                )}
                                                {location.email && (
                                                    <a
                                                        href={`mailto:${location.email}`}
                                                        className="flex items-center gap-2 hover:text-white"
                                                    >
                                                        <Mail className="size-3.5" />
                                                        {location.email}
                                                    </a>
                                                )}
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            ) : (
                                <p className="mt-10 text-white/50">
                                    Location details will be published soon.
                                </p>
                            )}
                        </div>
                    </section>
                )}

                {sections.experience?.enabled && experienceItems.length > 0 && (
                    <section
                        id="experience"
                        className="px-5 py-20 sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl">
                            <SectionHeading
                                section={sections.experience}
                                intro={String(sections.experience.intro ?? '')}
                                align="center"
                            />
                            <div className="mt-12 grid gap-5 md:grid-cols-3">
                                {experienceItems.map(
                                    ([title, description], index) => {
                                        const icons = [
                                            BookOpen,
                                            UsersRound,
                                            Award,
                                        ];
                                        const Icon =
                                            icons[index % icons.length];
                                        return (
                                            <article
                                                key={`${title}-${index}`}
                                                className="rounded-3xl border border-black/5 bg-white p-7 text-center shadow-sm"
                                            >
                                                <span className="mx-auto grid size-14 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                                                    <Icon className="size-6" />
                                                </span>
                                                <h3 className="mt-6 text-xl font-bold">
                                                    {title}
                                                </h3>
                                                <p className="mt-3 text-sm leading-7 text-slate-600">
                                                    {description}
                                                </p>
                                            </article>
                                        );
                                    },
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {sections.events?.enabled && events.length > 0 && (
                    <section
                        id="events"
                        className="bg-white px-5 py-20 sm:px-8 lg:py-24"
                    >
                        <div className="mx-auto max-w-7xl">
                            <SectionHeading
                                section={sections.events}
                                intro={String(sections.events.intro ?? '')}
                            />
                            <div className="mt-10 divide-y border-y">
                                {events.map(
                                    (
                                        [date, title, description, location],
                                        index,
                                    ) => (
                                        <article
                                            key={`${title}-${index}`}
                                            className="grid gap-5 py-7 md:grid-cols-[160px_1fr_auto] md:items-center"
                                        >
                                            <div className="flex items-center gap-3 text-sm font-bold text-[var(--site-primary)]">
                                                <CalendarDays className="size-5" />
                                                {date}
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-bold">
                                                    {title}
                                                </h3>
                                                <p className="mt-1 text-sm text-slate-600">
                                                    {description}
                                                </p>
                                            </div>
                                            {location && (
                                                <span className="flex items-center gap-2 text-xs text-slate-500">
                                                    <MapPin className="size-4" />
                                                    {location}
                                                </span>
                                            )}
                                        </article>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {sections.news?.enabled && news.length > 0 && (
                    <section id="news" className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto max-w-7xl">
                            <SectionHeading
                                section={sections.news}
                                intro={String(sections.news.intro ?? '')}
                            />
                            <div className="mt-10 grid gap-5 md:grid-cols-3">
                                {news.map(
                                    (
                                        [date, title, excerpt, url, image],
                                        index,
                                    ) => (
                                        <a
                                            key={`${title}-${index}`}
                                            href={safeUrl(url)}
                                            className="group overflow-hidden rounded-3xl border border-black/5 bg-white shadow-sm"
                                        >
                                            <ImageOrInitial
                                                image={image}
                                                name={title}
                                                className="aspect-[16/10] w-full rounded-none"
                                            />
                                            <div className="p-6">
                                                <p className="text-xs font-bold text-[var(--site-primary)]">
                                                    {date}
                                                </p>
                                                <h3 className="mt-3 text-xl font-bold group-hover:underline">
                                                    {title}
                                                </h3>
                                                <p className="mt-3 text-sm leading-6 text-slate-600">
                                                    {excerpt}
                                                </p>
                                            </div>
                                        </a>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {sections.statistics?.enabled && statistics.length > 0 && (
                    <section
                        id="statistics"
                        className="bg-[var(--site-primary)] px-5 py-16 text-[var(--site-primary-fg)] sm:px-8"
                    >
                        <div className="mx-auto grid max-w-7xl gap-8 sm:grid-cols-2 lg:grid-cols-4">
                            {statistics.map(([number, label], index) => (
                                <AnimatedStatistic
                                    key={`${label}-${index}`}
                                    value={number}
                                    label={label}
                                />
                            ))}
                        </div>
                    </section>
                )}

                {sections.team?.enabled && team.length > 0 && (
                    <section id="team" className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto max-w-7xl">
                            <SectionHeading
                                section={sections.team}
                                intro={String(sections.team.intro ?? '')}
                                align="center"
                            />
                            <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                {team.map(([name, role, bio, image], index) => (
                                    <article
                                        key={`${name}-${index}`}
                                        className="overflow-hidden rounded-3xl bg-white shadow-sm"
                                    >
                                        <ImageOrInitial
                                            image={image}
                                            name={name}
                                            className="aspect-[4/4.5] w-full rounded-none"
                                        />
                                        <div className="p-6">
                                            <h3 className="text-lg font-bold">
                                                {name}
                                            </h3>
                                            <p className="mt-1 text-xs font-bold text-[var(--site-primary)]">
                                                {role}
                                            </p>
                                            <p className="mt-3 text-sm leading-6 text-slate-600">
                                                {bio}
                                            </p>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                {sections.testimonials?.enabled && testimonials.length > 0 && (
                    <section
                        id="testimonials"
                        className="bg-slate-950 px-5 py-20 text-white sm:px-8 lg:py-24"
                    >
                        <div className="mx-auto max-w-7xl">
                            <SectionHeading
                                section={sections.testimonials}
                                align="center"
                            />
                            <div className="mt-12 grid gap-5 md:grid-cols-3">
                                {testimonials.map(
                                    ([quote, name, role], index) => (
                                        <figure
                                            key={`${name}-${index}`}
                                            className="rounded-3xl border border-white/10 bg-white/5 p-7"
                                        >
                                            <Quote className="size-7 text-[var(--site-primary)]" />
                                            <blockquote className="mt-6 text-base leading-8 text-white/75">
                                                “{quote}”
                                            </blockquote>
                                            <figcaption className="mt-7 border-t border-white/10 pt-5">
                                                <p className="font-bold">
                                                    {name}
                                                </p>
                                                <p className="mt-1 text-xs text-white/45">
                                                    {role}
                                                </p>
                                            </figcaption>
                                        </figure>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {sections.alumni?.enabled && alumni.length > 0 && (
                    <section
                        id="alumni"
                        className="px-5 py-20 sm:px-8 lg:py-24"
                    >
                        <div className="mx-auto max-w-7xl">
                            <SectionHeading
                                section={sections.alumni}
                                intro={String(sections.alumni.intro ?? '')}
                            />
                            <div className="mt-10 grid gap-5 md:grid-cols-3">
                                {alumni.map(([name, story, image], index) => (
                                    <article
                                        key={`${name}-${index}`}
                                        className="flex items-center gap-5 rounded-3xl border bg-white p-5"
                                    >
                                        <ImageOrInitial
                                            image={image}
                                            name={name}
                                            className="size-20 shrink-0 rounded-2xl"
                                        />
                                        <div>
                                            <h3 className="font-bold">
                                                {name}
                                            </h3>
                                            <p className="mt-2 text-sm leading-6 text-slate-600">
                                                {story}
                                            </p>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                {sections.gallery?.enabled && gallery.length > 0 && (
                    <section
                        id="gallery"
                        className="bg-white px-5 py-20 sm:px-8 lg:py-24"
                    >
                        <div className="mx-auto max-w-7xl">
                            <SectionHeading
                                section={sections.gallery}
                                align="center"
                            />
                            <div className="mt-10 grid auto-rows-[220px] grid-cols-2 gap-3 md:grid-cols-4">
                                {gallery.map(([caption, image], index) => (
                                    <figure
                                        key={`${caption}-${index}`}
                                        className={`group relative overflow-hidden rounded-2xl bg-[var(--site-secondary)] ${index % 5 === 0 ? 'col-span-2 row-span-2' : ''}`}
                                    >
                                        {/^https?:\/\//i.test(image) && (
                                            <img
                                                src={image}
                                                alt={caption}
                                                className="size-full object-cover transition duration-500 group-hover:scale-105"
                                            />
                                        )}
                                        <figcaption className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-4 pt-10 text-sm font-bold text-white">
                                            {caption}
                                        </figcaption>
                                    </figure>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                {(sections.affiliations?.enabled && affiliations.length > 0) ||
                (sections.sponsors?.enabled && sponsors.length > 0) ? (
                    <section id="partners" className="px-5 py-16 sm:px-8">
                        <div className="mx-auto max-w-7xl space-y-12">
                            {sections.affiliations && (
                                <LogoStrip
                                    section={sections.affiliations}
                                    items={affiliations}
                                />
                            )}
                            {sections.sponsors && (
                                <LogoStrip
                                    section={sections.sponsors}
                                    items={sponsors}
                                />
                            )}
                        </div>
                    </section>
                ) : null}

                {sections.faq?.enabled && faqs.length > 0 && (
                    <section
                        id="faq"
                        className="bg-white px-5 py-20 sm:px-8 lg:py-24"
                    >
                        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.7fr_1.3fr]">
                            <SectionHeading section={sections.faq} />
                            <div className="divide-y border-y">
                                {faqs.map(([question, answer], index) => (
                                    <details
                                        key={`${question}-${index}`}
                                        className="group py-5"
                                    >
                                        <summary className="flex cursor-pointer list-none items-center justify-between gap-5 font-bold">
                                            {question}
                                            <span className="text-xl text-[var(--site-primary)] group-open:rotate-45">
                                                +
                                            </span>
                                        </summary>
                                        <p className="max-w-2xl pt-4 text-sm leading-7 text-slate-600">
                                            {answer}
                                        </p>
                                    </details>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                <section className="px-5 py-20 sm:px-8">
                    <div className="mx-auto grid max-w-7xl items-center gap-8 overflow-hidden rounded-[2rem] bg-[var(--site-primary)] p-8 text-[var(--site-primary-fg)] sm:p-12 lg:grid-cols-[1fr_auto]">
                        <div>
                            <p className="text-xs font-bold tracking-[0.18em] uppercase opacity-70">
                                Take the next step
                            </p>
                            <h2 className="mt-3 max-w-3xl text-3xl font-black tracking-tight sm:text-4xl">
                                Ready to begin your journey with {academy.name}?
                            </h2>
                        </div>
                        <a
                            href={`${base_url}/apply`}
                            className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-7 py-4 text-sm font-extrabold text-white"
                        >
                            Start an application
                            <ArrowRight className="size-4" />
                        </a>
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}
