import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Building2,
    CalendarCheck2,
    Check,
    ClipboardCheck,
    CreditCard,
    Globe2,
    PackageCheck,
    ReceiptText,
    ShieldCheck,
    Sparkles,
    UsersRound,
} from 'lucide-react';
import MarketingShell from '@/components/marketing/marketing-shell';
import { dashboard, register } from '@/routes';
import { marketingPages } from './page-data';

type Seo = { canonical_url: string; image_url: string };

const outcomeIcons = [
    Globe2,
    UsersRound,
    CreditCard,
    BarChart3,
    CalendarCheck2,
    ClipboardCheck,
    ReceiptText,
    PackageCheck,
] as const;

export default function MarketingPage({
    page,
    seo,
}: {
    page: string;
    seo: Seo;
}) {
    const definition = marketingPages[page];
    const { auth } = usePage().props;

    if (!definition) {
        throw new Error(`Unknown marketing page: ${page}`);
    }

    const workspaceUrl = auth.user ? dashboard() : register();
    const structuredData = {
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'WebPage',
                name: definition.title,
                description: definition.description,
                url: seo.canonical_url,
                isPartOf: {
                    '@type': 'WebSite',
                    name: 'Bootcademy',
                    url: seo.canonical_url.replace(definition.path, '/'),
                },
            },
            {
                '@type': 'BreadcrumbList',
                itemListElement: [
                    {
                        '@type': 'ListItem',
                        position: 1,
                        name: 'Home',
                        item: seo.canonical_url.replace(definition.path, '/'),
                    },
                    {
                        '@type': 'ListItem',
                        position: 2,
                        name: definition.title,
                        item: seo.canonical_url,
                    },
                ],
            },
            {
                '@type': 'FAQPage',
                mainEntity: definition.faqs.map((faq) => ({
                    '@type': 'Question',
                    name: faq.question,
                    acceptedAnswer: {
                        '@type': 'Answer',
                        text: faq.answer,
                    },
                })),
            },
        ],
    };

    return (
        <MarketingShell>
            <Head title={definition.title}>
                <meta name="description" content={definition.description} />
                <meta name="keywords" content={definition.keywords} />
                <meta
                    name="robots"
                    content="index, follow, max-image-preview:large"
                />
                <link rel="canonical" href={seo.canonical_url} />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="Bootcademy" />
                <meta property="og:title" content={definition.title} />
                <meta
                    property="og:description"
                    content={definition.description}
                />
                <meta property="og:url" content={seo.canonical_url} />
                <meta property="og:image" content={seo.image_url} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={definition.title} />
                <meta
                    name="twitter:description"
                    content={definition.description}
                />
                <meta name="twitter:image" content={seo.image_url} />
                <script type="application/ld+json">
                    {JSON.stringify(structuredData)}
                </script>
            </Head>

            <main>
                <section className="relative overflow-hidden bg-[#071D49] text-white">
                    <div className="absolute inset-0 [background-image:radial-gradient(circle_at_15%_15%,#0878D1_0,transparent_32%),radial-gradient(circle_at_85%_85%,#12B8B0_0,transparent_30%)] opacity-25" />
                    <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 lg:grid-cols-[1.04fr_0.96fr] lg:py-24">
                        <div>
                            <nav
                                aria-label="Breadcrumb"
                                className="mb-7 flex items-center gap-2 text-xs font-bold text-white/45"
                            >
                                <Link href="/" className="hover:text-white">
                                    Home
                                </Link>
                                <span aria-hidden="true">/</span>
                                <span className="text-white/70">
                                    {definition.eyebrow}
                                </span>
                            </nav>
                            <p className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/8 px-4 py-2 text-xs font-black tracking-[0.08em] text-white/80 uppercase">
                                <Sparkles className="size-3.5 text-[#12B8B0]" />
                                {definition.eyebrow}
                            </p>
                            <h1 className="mt-7 max-w-4xl text-5xl leading-[0.98] font-black tracking-[-0.055em] text-balance sm:text-6xl lg:text-7xl">
                                {definition.headline}{' '}
                                <span className="text-[#FF8A3D]">
                                    {definition.accent}
                                </span>
                            </h1>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/68">
                                {definition.introduction}
                            </p>
                            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={workspaceUrl}
                                    className="inline-flex items-center justify-center gap-2 rounded-full bg-[#FF6900] px-7 py-4 text-sm font-black text-white shadow-xl shadow-orange-950/30 transition hover:-translate-y-0.5 hover:bg-[#F05F00]"
                                >
                                    {auth.user
                                        ? 'Open your workspace'
                                        : 'Start your academy workspace'}
                                    <ArrowRight className="size-4" />
                                </Link>
                                <Link
                                    href="/#pricing"
                                    className="inline-flex items-center justify-center rounded-full border border-white/20 bg-white/8 px-7 py-4 text-sm font-black text-white transition hover:bg-white/12"
                                >
                                    View pricing
                                </Link>
                            </div>
                            <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-xs font-bold text-white/55">
                                {definition.proof.map((item) => (
                                    <span
                                        key={item}
                                        className="flex items-center gap-2"
                                    >
                                        <Check className="size-3.5 text-[#12B8B0]" />
                                        {item}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-xl">
                            <div className="absolute -inset-8 rounded-full bg-[#12B8B0]/15 blur-3xl" />
                            <div className="relative overflow-hidden rounded-[2rem] border border-white/15 bg-white p-3 shadow-2xl shadow-black/25">
                                <img
                                    src="/images/bootcademy-academy-owner.jpg"
                                    alt="Academy owner using Bootcademy to manage academy operations"
                                    className="aspect-[4/3] w-full rounded-[1.35rem] object-cover"
                                    width="1800"
                                    height="1013"
                                />
                                <div className="absolute right-6 bottom-6 left-6 rounded-2xl border border-white/70 bg-white/95 p-4 text-[#071D49] shadow-xl backdrop-blur">
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <p className="text-[10px] font-black tracking-[0.12em] text-[#0878D1] uppercase">
                                                One academy workspace
                                            </p>
                                            <p className="mt-1 text-sm font-black">
                                                Website → application →
                                                enrolment → payment
                                            </p>
                                        </div>
                                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[#E8F9F7] text-[#078A85]">
                                            <Check className="size-5" />
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="px-5 py-20 sm:px-8 lg:py-28">
                    <div className="mx-auto max-w-7xl">
                        <div className="grid gap-12 lg:grid-cols-[0.76fr_1.24fr]">
                            <div>
                                <p className="text-xs font-black tracking-[0.16em] text-[#FF6900] uppercase">
                                    Why it matters
                                </p>
                                <h2 className="mt-4 text-4xl leading-tight font-black tracking-[-0.045em] text-balance sm:text-5xl">
                                    {definition.problemTitle}
                                </h2>
                                <p className="mt-6 text-base leading-7 text-[#071D49]/65">
                                    {definition.problem}
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {definition.outcomes.map((outcome, index) => {
                                    const Icon =
                                        outcomeIcons[
                                            index % outcomeIcons.length
                                        ];
                                    return (
                                        <article
                                            key={outcome.title}
                                            className="rounded-3xl border border-[#071D49]/8 bg-[#F8FAFC] p-6"
                                        >
                                            <span className="grid size-11 place-items-center rounded-2xl bg-[#EAF5FD] text-[#0878D1]">
                                                <Icon className="size-5" />
                                            </span>
                                            <h3 className="mt-5 text-lg font-black">
                                                {outcome.title}
                                            </h3>
                                            <p className="mt-2 text-sm leading-6 text-[#071D49]/60">
                                                {outcome.description}
                                            </p>
                                        </article>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="bg-[#F3F7FB] px-5 py-20 sm:px-8 lg:py-28">
                    <div className="mx-auto max-w-7xl">
                        <div className="mx-auto max-w-3xl text-center">
                            <p className="text-xs font-black tracking-[0.16em] text-[#0878D1] uppercase">
                                A connected workflow
                            </p>
                            <h2 className="mt-4 text-4xl font-black tracking-[-0.045em] sm:text-5xl">
                                Clear enough for the team to use every day.
                            </h2>
                        </div>
                        <ol className="mt-12 grid gap-5 lg:grid-cols-3">
                            {definition.workflow.map((step, index) => (
                                <li
                                    key={step.title}
                                    className="relative rounded-3xl border border-[#071D49]/8 bg-white p-7 shadow-sm"
                                >
                                    <span className="text-xs font-black tracking-[0.12em] text-[#12B8B0] uppercase">
                                        Step {index + 1}
                                    </span>
                                    <h3 className="mt-4 text-xl font-black">
                                        {step.title}
                                    </h3>
                                    <p className="mt-3 text-sm leading-6 text-[#071D49]/60">
                                        {step.description}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>

                <section className="overflow-hidden bg-[#071D49] px-5 py-20 text-white sm:px-8 lg:py-28">
                    <div className="mx-auto grid max-w-7xl items-start gap-12 lg:grid-cols-[0.85fr_1.15fr]">
                        <div>
                            <p className="text-xs font-black tracking-[0.16em] text-[#12B8B0] uppercase">
                                Included capabilities
                            </p>
                            <h2 className="mt-4 text-4xl font-black tracking-[-0.045em] sm:text-5xl">
                                Built into the same academy record.
                            </h2>
                            <p className="mt-6 text-sm leading-7 text-white/60">
                                Permissions and tenant ownership are enforced on
                                the server. Finance actions preserve history,
                                and protected records stay inside the authorised
                                academy context.
                            </p>
                            <div className="mt-7 flex gap-3 text-xs font-bold text-white/55">
                                <ShieldCheck className="size-5 text-[#12B8B0]" />
                                Tenant-aware · role-aware · audit-aware
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {definition.capabilities.map((capability) => (
                                <div
                                    key={capability}
                                    className="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/6 p-4"
                                >
                                    <span className="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-[#12B8B0]/15 text-[#12B8B0]">
                                        <Check className="size-3.5" />
                                    </span>
                                    <span className="text-sm font-bold text-white/75">
                                        {capability}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="px-5 py-20 sm:px-8 lg:py-28">
                    <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.72fr_1.28fr]">
                        <div>
                            <p className="text-xs font-black tracking-[0.16em] text-[#0878D1] uppercase">
                                Frequently asked questions
                            </p>
                            <h2 className="mt-4 text-4xl font-black tracking-[-0.045em]">
                                Useful answers before you start.
                            </h2>
                            <div className="mt-8 rounded-2xl bg-[#FFF0E6] p-5">
                                <p className="text-xs font-black tracking-[0.12em] text-[#B64700] uppercase">
                                    Explore next
                                </p>
                                <div className="mt-4 grid gap-3">
                                    {definition.related.map((item) => (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            className="flex items-center justify-between gap-3 text-sm font-black text-[#071D49] hover:text-[#0878D1]"
                                        >
                                            {item.label}
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        </div>
                        <div className="divide-y divide-[#071D49]/10 border-y border-[#071D49]/10">
                            {definition.faqs.map((faq) => (
                                <details
                                    key={faq.question}
                                    className="group py-5"
                                >
                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-black">
                                        {faq.question}
                                        <span className="text-xl text-[#0878D1] group-open:rotate-45">
                                            +
                                        </span>
                                    </summary>
                                    <p className="max-w-3xl pt-4 text-sm leading-7 text-[#071D49]/60">
                                        {faq.answer}
                                    </p>
                                </details>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="px-5 pb-20 sm:px-8 lg:pb-28">
                    <div className="mx-auto max-w-7xl overflow-hidden rounded-[2.25rem] bg-[#0878D1] px-6 py-12 text-center text-white sm:px-12 lg:py-16">
                        <Building2 className="mx-auto size-8 text-[#BCEAE7]" />
                        <h2 className="mx-auto mt-5 max-w-3xl text-4xl font-black tracking-[-0.05em] sm:text-5xl">
                            Make the next academy registration part of one
                            connected system.
                        </h2>
                        <p className="mx-auto mt-5 max-w-2xl text-sm leading-7 text-white/70">
                            Start with the programmes and people you manage
                            today. Keep the operational history you will need as
                            the academy grows.
                        </p>
                        <Link
                            href={workspaceUrl}
                            className="mt-8 inline-flex items-center gap-2 rounded-full bg-[#FF6900] px-7 py-4 text-sm font-black text-white shadow-xl shadow-blue-950/20"
                        >
                            {auth.user
                                ? 'Open your dashboard'
                                : 'Create your academy account'}
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </section>
            </main>
        </MarketingShell>
    );
}
