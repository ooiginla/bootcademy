import {
    ArrowLeft,
    ArrowRight,
    Award,
    CalendarRange,
    CheckCircle2,
    Clock3,
    Compass,
    Globe2,
    GraduationCap,
    Layers3,
    MapPin,
    Quote,
    ReceiptText,
    Rocket,
    UsersRound,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import { formatCurrency } from '@/lib/money';
import type {
    PublicAcademy,
    PublicOffering,
    PublicProgramme,
    PublicSite,
} from '@/types/public-site';

type ProgrammeDetails = PublicProgramme & { offerings: PublicOffering[] };
const richClass =
    'space-y-3 text-[15px] leading-7 text-slate-600 [&_a]:font-semibold [&_a]:text-[var(--site-primary)] [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-[var(--site-primary)] [&_blockquote]:pl-4 [&_h2]:text-2xl [&_h2]:font-black [&_h2]:text-slate-950 [&_h3]:text-lg [&_h3]:font-black [&_h3]:text-slate-950 [&_h4]:font-bold [&_h4]:text-slate-950 [&_li]:my-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_strong]:text-slate-900 [&_ul]:list-disc [&_ul]:pl-5';

export default function PublicProgrammeDetails({
    academy,
    site,
    base_url,
    programme,
}: {
    academy: PublicAcademy;
    site: PublicSite;
    base_url: string;
    programme: ProgrammeDetails;
}) {
    const meta = [
        programme.duration && {
            icon: Clock3,
            label: 'Duration',
            value: programme.duration,
        },
        programme.delivery_format && {
            icon: Globe2,
            label: 'Format',
            value: programme.delivery_format,
        },
        programme.programme_type && {
            icon: Layers3,
            label: 'Type',
            value: programme.programme_type,
        },
        programme.level && {
            icon: GraduationCap,
            label: 'Level',
            value: programme.level,
        },
        programme.public_timezone && {
            icon: Compass,
            label: 'Timezone',
            value: programme.public_timezone,
        },
    ].filter(Boolean) as Array<{
        icon: LucideIcon;
        label: string;
        value: string;
    }>;

    return (
        <PublicSiteShell
            academy={academy}
            site={site}
            baseUrl={base_url}
            title={`${programme.name} · ${academy.name}`}
        >
            <main className="bg-slate-50/60">
                <section className="relative overflow-hidden bg-slate-950 px-5 py-14 text-white sm:px-8 lg:py-20">
                    <div className="absolute -top-40 right-0 size-96 rounded-full bg-[var(--site-primary)]/20 blur-3xl" />
                    <div className="relative mx-auto max-w-7xl">
                        <a
                            href={`${base_url}/programmes`}
                            className="inline-flex items-center gap-2 text-sm font-bold text-white/60 hover:text-white"
                        >
                            <ArrowLeft className="size-4" /> All programmes
                        </a>
                        <div
                            className={`mt-10 grid gap-10 ${programme.image_url ? 'lg:grid-cols-[0.9fr_1.1fr] lg:items-center' : ''}`}
                        >
                            <div
                                className={
                                    programme.image_url ? '' : 'max-w-5xl'
                                }
                            >
                                <p className="text-xs font-extrabold tracking-[0.22em] text-[var(--site-primary)] uppercase">
                                    {programme.code ||
                                        programme.programme_type ||
                                        academy.type}
                                </p>
                                <h1 className="mt-4 text-5xl font-black tracking-[-0.05em] text-balance sm:text-6xl lg:text-7xl">
                                    {programme.name}
                                </h1>
                                {programme.description && (
                                    <p className="mt-6 max-w-3xl text-lg leading-8 text-white/65">
                                        {programme.description}
                                    </p>
                                )}
                                {meta.length > 0 && (
                                    <dl
                                        className={`mt-10 grid gap-3 sm:grid-cols-2 ${programme.image_url ? '' : 'lg:grid-cols-5'}`}
                                    >
                                        {meta.map(
                                            ({ icon: Icon, label, value }) => (
                                                <div
                                                    key={label}
                                                    className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm"
                                                >
                                                    <dt className="flex items-center gap-2 text-xs font-bold text-white/45">
                                                        <Icon className="size-4 text-[var(--site-primary)]" />
                                                        {label}
                                                    </dt>
                                                    <dd className="mt-2 font-extrabold">
                                                        {value}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                )}
                            </div>
                            {programme.image_url && (
                                <div className="relative overflow-hidden rounded-[2rem] border border-white/15 bg-white/5 p-2 shadow-2xl shadow-black/35">
                                    <div className="absolute inset-0 bg-[var(--site-primary)]/10 blur-3xl" />
                                    <img
                                        src={programme.image_url}
                                        alt={`${programme.name} programme`}
                                        className="relative aspect-[4/3] w-full rounded-[1.55rem] object-cover"
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <section className="px-5 py-14 sm:px-8 lg:py-20">
                    <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
                        <div className="space-y-8">
                            <RichSection
                                icon={Rocket}
                                eyebrow="Your transformation"
                                title="What you will achieve"
                                html={programme.outcomes_html}
                            />
                            {(programme.skills_gained_html ||
                                programme.curriculum_html) && (
                                <div className="grid gap-6 xl:grid-cols-2">
                                    <RichSection
                                        icon={Award}
                                        eyebrow="Practical capability"
                                        title="Skills gained"
                                        html={programme.skills_gained_html}
                                        compact
                                    />
                                    <RichSection
                                        icon={Layers3}
                                        eyebrow="Learning journey"
                                        title="Curriculum"
                                        html={programme.curriculum_html}
                                        compact
                                    />
                                </div>
                            )}
                            {(programme.tools_platforms?.length ?? 0) > 0 && (
                                <ContentCard
                                    icon={Wrench}
                                    eyebrow="Hands-on learning"
                                    title="Tools & platforms"
                                >
                                    <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                                        {programme.tools_platforms?.map(
                                            (tool) => (
                                                <div
                                                    key={tool.name}
                                                    className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3"
                                                >
                                                    {tool.logo_url ? (
                                                        <img
                                                            src={tool.logo_url}
                                                            alt=""
                                                            className="size-10 rounded-xl bg-white object-contain p-1.5 shadow-sm"
                                                            loading="lazy"
                                                        />
                                                    ) : (
                                                        <span className="grid size-10 place-items-center rounded-xl bg-[var(--site-secondary)] text-sm font-black text-[var(--site-secondary-fg)]">
                                                            {tool.name
                                                                .slice(0, 2)
                                                                .toUpperCase()}
                                                        </span>
                                                    )}
                                                    <span className="text-sm font-extrabold text-slate-900">
                                                        {tool.name}
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </ContentCard>
                            )}
                            {(programme.audience_html ||
                                programme.eligibility_html) && (
                                <div className="grid gap-6 xl:grid-cols-2">
                                    <RichSection
                                        icon={UsersRound}
                                        eyebrow="Find your fit"
                                        title="Who this is for"
                                        html={programme.audience_html}
                                        compact
                                    />
                                    <RichSection
                                        icon={CheckCircle2}
                                        eyebrow="Entry requirements"
                                        title="Eligibility"
                                        html={programme.eligibility_html}
                                        compact
                                    />
                                </div>
                            )}
                            <RichSection
                                icon={Compass}
                                eyebrow="Career value"
                                title="Why it pays off"
                                html={programme.demand_html}
                            />
                            {(programme.testimonials?.length ?? 0) > 0 && (
                                <ContentCard
                                    icon={Quote}
                                    eyebrow="Learner stories"
                                    title="What people say"
                                >
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {programme.testimonials?.map(
                                            (testimonial, index) => (
                                                <figure
                                                    key={`${testimonial.name}-${index}`}
                                                    className="rounded-2xl bg-slate-50 p-5"
                                                >
                                                    <RichHtml
                                                        html={
                                                            testimonial.quote_html
                                                        }
                                                    />
                                                    <figcaption className="mt-5 border-t pt-4">
                                                        <p className="font-extrabold text-slate-950">
                                                            {testimonial.name}
                                                        </p>
                                                        {testimonial.role && (
                                                            <p className="text-xs text-slate-500">
                                                                {
                                                                    testimonial.role
                                                                }
                                                            </p>
                                                        )}
                                                    </figcaption>
                                                </figure>
                                            ),
                                        )}
                                    </div>
                                </ContentCard>
                            )}
                            {(programme.faqs?.length ?? 0) > 0 && (
                                <ContentCard
                                    icon={CheckCircle2}
                                    eyebrow="Good to know"
                                    title="Frequently asked questions"
                                >
                                    <div className="divide-y divide-slate-200">
                                        {programme.faqs?.map((faq, index) => (
                                            <details
                                                key={`${faq.question}-${index}`}
                                                className="group py-4 first:pt-0 last:pb-0"
                                            >
                                                <summary className="cursor-pointer list-none pr-8 font-extrabold text-slate-950 marker:hidden">
                                                    {faq.question}
                                                    <span className="float-right text-[var(--site-primary)] group-open:rotate-45">
                                                        +
                                                    </span>
                                                </summary>
                                                <div className="mt-3">
                                                    <RichHtml
                                                        html={faq.answer_html}
                                                    />
                                                </div>
                                            </details>
                                        ))}
                                    </div>
                                </ContentCard>
                            )}
                            <RichSection
                                icon={ArrowRight}
                                eyebrow="Ready when you are"
                                title="Application process"
                                html={programme.application_process_html}
                            />
                        </div>

                        <aside className="space-y-5 lg:sticky lg:top-24">
                            <div className="rounded-3xl bg-slate-950 p-6 text-white shadow-xl shadow-slate-950/10">
                                <p className="text-xs font-extrabold tracking-[0.18em] text-[var(--site-primary)] uppercase">
                                    Fees & availability
                                </p>
                                <h2 className="mt-3 text-2xl font-black">
                                    Choose your intake
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-white/55">
                                    Review the active cycle, location and
                                    payment options before applying.
                                </p>
                                <a
                                    href={`${base_url}/apply`}
                                    className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-[var(--site-primary)] px-5 py-3 text-sm font-extrabold text-[var(--site-primary-fg)]"
                                >
                                    Apply for this programme{' '}
                                    <ArrowRight className="size-4" />
                                </a>
                            </div>
                            {programme.offerings.length ? (
                                programme.offerings.map((offering) => (
                                    <OfferingCard
                                        key={offering.id}
                                        offering={offering}
                                    />
                                ))
                            ) : (
                                <div className="rounded-3xl border border-dashed border-slate-300 bg-white p-7 text-center">
                                    <CalendarRange className="mx-auto size-8 text-slate-400" />
                                    <p className="mt-3 font-bold text-slate-900">
                                        Next cycle coming soon
                                    </p>
                                    <p className="mt-2 text-sm text-slate-500">
                                        Contact the academy for upcoming dates
                                        and fees.
                                    </p>
                                </div>
                            )}
                        </aside>
                    </div>
                </section>
            </main>
        </PublicSiteShell>
    );
}

function ContentCard({
    icon: Icon,
    eyebrow,
    title,
    children,
}: {
    icon: LucideIcon;
    eyebrow: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div className="flex items-center gap-3">
                <span className="grid size-11 place-items-center rounded-2xl bg-[var(--site-secondary)] text-[var(--site-secondary-fg)]">
                    <Icon className="size-5" />
                </span>
                <div>
                    <p className="text-[10px] font-extrabold tracking-[0.18em] text-[var(--site-primary)] uppercase">
                        {eyebrow}
                    </p>
                    <h2 className="mt-1 text-2xl font-black tracking-tight text-slate-950">
                        {title}
                    </h2>
                </div>
            </div>
            <div className="mt-6">{children}</div>
        </article>
    );
}

function RichSection({
    icon,
    eyebrow,
    title,
    html,
    compact = false,
}: {
    icon: LucideIcon;
    eyebrow: string;
    title: string;
    html?: string | null;
    compact?: boolean;
}) {
    if (!html) return null;
    return (
        <ContentCard icon={icon} eyebrow={eyebrow} title={title}>
            <div className={compact ? 'min-h-24' : ''}>
                <RichHtml html={html} />
            </div>
        </ContentCard>
    );
}

function RichHtml({ html }: { html: string }) {
    return (
        <div className={richClass} dangerouslySetInnerHTML={{ __html: html }} />
    );
}

function OfferingCard({ offering }: { offering: PublicOffering }) {
    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="flex items-center gap-2 text-xs font-bold text-[var(--site-primary)]">
                        <CalendarRange className="size-4" />
                        {offering.cycle_name}
                    </p>
                    <h3 className="mt-2 text-lg font-black text-slate-950">
                        {offering.fee_name}
                    </h3>
                </div>
                <span className="rounded-full bg-[var(--site-secondary)] px-2.5 py-1 text-[11px] font-bold text-[var(--site-secondary-fg)]">
                    <MapPin className="mr-1 inline size-3" />
                    {offering.branch_name}
                </span>
            </div>
            <div className="mt-5 grid grid-cols-2 gap-2">
                <FeeStat
                    label="Programme fees"
                    amount={
                        offering.total === null
                            ? 'Ask the academy'
                            : formatCurrency(offering.currency, offering.total)
                    }
                />
                <FeeStat
                    label="Application fee"
                    amount={formatCurrency(
                        offering.currency,
                        offering.application_fee,
                    )}
                />
            </div>
            {(offering.fee_items.length > 0 ||
                offering.payment_plans.length > 1) && (
                <details className="mt-5 rounded-2xl border border-slate-200 p-4">
                    <summary className="cursor-pointer list-none text-sm font-extrabold text-slate-900 marker:hidden">
                        <ReceiptText className="mr-2 inline size-4" />
                        View fee details
                    </summary>
                    {offering.fee_items.length > 0 && (
                        <div className="mt-3 divide-y">
                            {offering.fee_items.map((item) => (
                                <div
                                    key={item.name}
                                    className="flex justify-between gap-3 py-2 text-xs"
                                >
                                    <span className="text-slate-500">
                                        {item.name}
                                    </span>
                                    <strong>
                                        {formatCurrency(
                                            offering.currency,
                                            item.amount,
                                        )}
                                    </strong>
                                </div>
                            ))}
                        </div>
                    )}
                    {offering.payment_plans.length > 1 && (
                        <p className="mt-3 flex items-center gap-2 text-xs text-slate-500">
                            <CheckCircle2 className="size-4 text-[var(--site-primary)]" />
                            {offering.payment_plans.length} payment plans
                            available when you apply
                        </p>
                    )}
                </details>
            )}
        </article>
    );
}

function FeeStat({ label, amount }: { label: string; amount: string }) {
    return (
        <div className="rounded-2xl bg-slate-50 p-3">
            <p className="text-[10px] text-slate-500">{label}</p>
            <p className="mt-1 text-sm font-black text-slate-950">{amount}</p>
        </div>
    );
}
