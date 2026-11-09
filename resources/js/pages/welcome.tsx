import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Banknote,
    BarChart3,
    BookOpenCheck,
    Building2,
    CalendarCheck2,
    Check,
    ChevronDown,
    ClipboardCheck,
    CreditCard,
    FileSpreadsheet,
    Globe2,
    GraduationCap,
    Landmark,
    LockKeyhole,
    Menu,
    Palette,
    ReceiptText,
    ShieldCheck,
    Sparkles,
    UsersRound,
    WalletCards,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { currencySymbol, formatMoneyAmount } from '@/lib/money';
import { dashboard, login, register } from '@/routes';

type Plan = {
    code: string;
    name: string;
    currency: string;
    monthly_price: string;
    annual_price: string;
    active_player_limit: number;
};
type Seo = { canonical_url: string; image_url: string };

const navigation = [
    ['Product', '#product'],
    ['Features', '#features'],
    ['Who it is for', '#academies'],
    ['Pricing', '#pricing'],
    ['FAQ', '#faq'],
] as const;

const featureGroups = [
    [
        'People and programmes',
        'Keep applicant, learner, guardian, instructor, branch and programme records connected from the first enquiry onward.',
        UsersRound,
        'bg-[#EAF5FD] text-[#0878D1]',
    ],
    [
        'Fees and payments',
        'Issue invoices, offer installments, verify Paystack payments, track balances and preserve a complete payment history.',
        CreditCard,
        'bg-[#FFF0E6] text-[#E85F00]',
    ],
    [
        'Sessions and attendance',
        'Plan physical or virtual sessions, assign instructors and capture a mobile-friendly attendance register.',
        CalendarCheck2,
        'bg-[#E8F9F7] text-[#078A85]',
    ],
    [
        'Expenses and visibility',
        'Record expenses, vendors and categories, then compare collections, outstanding fees and operations by branch.',
        WalletCards,
        'bg-[#F1EEFF] text-[#6246B5]',
    ],
    [
        'Your academy website',
        'Publish a branded academy website, programme pages and an application journey using your academy colours and logo.',
        Globe2,
        'bg-[#EAF5FD] text-[#0878D1]',
    ],
    [
        'Roles and audit history',
        'Give owners, finance teams, branch managers and instructors the right access while recording important actions.',
        ShieldCheck,
        'bg-[#FFF0E6] text-[#E85F00]',
    ],
] as const;

const academyTypes = [
    ['Sports academies', BadgeCheck],
    ['Skills & bootcamps', Sparkles],
    ['Language academies', BookOpenCheck],
    ['Learning centres', GraduationCap],
    ['Creative academies', Palette],
] as const;

const faqs = [
    [
        'What is academy management software?',
        'Academy management software brings learner records, programmes, branches, attendance, invoicing, payments, expenses and staff access into one operational system. Bootcademy is designed for subscription-based sports, education, language, creative and skills academies.',
    ],
    [
        'Can Bootcademy manage more than one branch?',
        'Yes. An academy can run multiple branches from one workspace, assign programmes and sessions by location, and filter operational and financial information by branch.',
    ],
    [
        'Does it support installments and online payments?',
        'Yes. Programme-cycle fees can use one-off or installment arrangements. Bootcademy supports server-verified Paystack payments, manual payment records, receipts, balances and a complete invoice history.',
    ],
    [
        'Can our academy use its own logo and colours?',
        'Yes. Each academy can upload its logo, choose its colours and publish a branded public website. The secure management workspace keeps Bootcademy’s consistent interface while each academy website reflects its own identity.',
    ],
    [
        'Is Bootcademy only for football academies?',
        'No. Football is an early use case, but the platform supports any subscription-based academy. Terminology can adapt from players and coaches to students, customers, tutors or instructors.',
    ],
] as const;

function Brand({ light = false }: { light?: boolean }) {
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

function ProductPreview() {
    return (
        <div className="relative mx-auto w-full max-w-xl">
            <div className="absolute -inset-8 rounded-full bg-[#12B8B0]/15 blur-3xl" />
            <div className="relative overflow-hidden rounded-[1.75rem] border border-white/15 bg-white shadow-2xl shadow-black/25">
                <div className="flex items-center gap-2 border-b bg-[#F3F7FB] px-4 py-3">
                    <span className="size-2.5 rounded-full bg-[#FF6900]" />
                    <span className="size-2.5 rounded-full bg-[#12B8B0]" />
                    <span className="size-2.5 rounded-full bg-[#0878D1]" />
                    <span className="ml-3 h-2 w-24 rounded-full bg-slate-200" />
                </div>
                <div className="grid min-h-[390px] grid-cols-[76px_1fr] bg-[#F8FAFC] sm:grid-cols-[160px_1fr]">
                    <aside className="bg-[#071D49] p-3 text-white sm:p-4">
                        <div className="flex items-center gap-2">
                            <img
                                src="/images/bootcademy-mark.png"
                                alt=""
                                className="size-8 rounded-lg object-cover"
                                width="32"
                                height="32"
                            />
                            <span className="hidden text-xs font-bold sm:block">
                                My Academy
                            </span>
                        </div>
                        <div className="mt-7 space-y-3">
                            {['Overview', 'People', 'Sessions', 'Finance'].map(
                                (label, index) => (
                                    <div
                                        key={label}
                                        className={`flex items-center gap-2 rounded-lg px-2 py-2 text-[10px] ${index === 0 ? 'bg-white/12 text-white' : 'text-white/50'}`}
                                    >
                                        <span className="size-2 rounded-full bg-current" />
                                        <span className="hidden sm:block">
                                            {label}
                                        </span>
                                    </div>
                                ),
                            )}
                        </div>
                    </aside>
                    <div className="min-w-0 p-4 sm:p-5">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-[10px] font-bold tracking-[0.15em] text-[#0878D1] uppercase">
                                    Academy overview
                                </p>
                                <p className="mt-1 text-sm font-black text-[#071D49] sm:text-lg">
                                    Good morning, Ada
                                </p>
                            </div>
                            <span className="rounded-full bg-[#E8F9F7] px-2 py-1 text-[9px] font-bold text-[#078A85]">
                                Active cycle
                            </span>
                        </div>
                        <div className="mt-5 grid grid-cols-2 gap-2.5">
                            {[
                                ['Active learners', '286', UsersRound],
                                ['Collected', '₦4.8m', Banknote],
                                ['Attendance', '91%', ClipboardCheck],
                                ['Outstanding', '₦620k', ReceiptText],
                            ].map(([label, value, Icon]) => (
                                <div
                                    key={String(label)}
                                    className="rounded-xl border border-slate-100 bg-white p-3 shadow-sm"
                                >
                                    <Icon className="size-3.5 text-[#0878D1]" />
                                    <p className="mt-3 text-sm font-black text-[#071D49] sm:text-base">
                                        {String(value)}
                                    </p>
                                    <p className="mt-0.5 truncate text-[9px] text-slate-400">
                                        {String(label)}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <div className="mt-3 rounded-xl border border-slate-100 bg-white p-3 shadow-sm">
                            <div className="flex items-center justify-between">
                                <p className="text-[10px] font-bold text-[#071D49]">
                                    Collections by month
                                </p>
                                <BarChart3 className="size-3.5 text-[#12B8B0]" />
                            </div>
                            <div className="mt-4 flex h-20 items-end gap-2">
                                {[42, 62, 48, 76, 58, 88, 72].map(
                                    (height, index) => (
                                        <span
                                            key={index}
                                            className="flex-1 rounded-t bg-[#0878D1] first:bg-[#12B8B0] last:bg-[#FF6900]"
                                            style={{ height: `${height}%` }}
                                        />
                                    ),
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="absolute -right-3 -bottom-5 rounded-2xl border border-white/70 bg-white p-3 shadow-xl sm:-right-7 sm:p-4">
                <p className="text-[10px] font-bold text-slate-400 uppercase">
                    Payment verified
                </p>
                <p className="mt-1 text-sm font-black text-[#071D49]">
                    ₦150,000.00
                </p>
                <p className="mt-1 flex items-center gap-1 text-[10px] font-bold text-[#078A85]">
                    <Check className="size-3" /> Receipt sent
                </p>
            </div>
        </div>
    );
}

export default function Welcome({ plans, seo }: { plans: Plan[]; seo: Seo }) {
    const { auth } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const [annual, setAnnual] = useState(false);
    const structuredData = {
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: 'Bootcademy',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web',
        url: seo.canonical_url,
        description:
            'Academy management software for learners, programmes, branches, attendance, fees, payments, expenses and branded academy websites.',
        offers: plans.map((plan) => ({
            '@type': 'Offer',
            name: plan.name,
            priceCurrency: plan.currency,
            price: annual ? plan.annual_price : plan.monthly_price,
        })),
    };

    return (
        <>
            <Head title="Academy Management Software for Sports, Schools & Training Centres">
                <meta
                    name="description"
                    content="Bootcademy is academy management software for sports, education, language and skills academies. Manage applications, learners, attendance, fees, Paystack payments, expenses, branches and websites in one platform."
                />
                <meta
                    name="keywords"
                    content="academy management software, sports academy software, student management system Nigeria, training academy app, attendance and fee management, football academy management software, learning centre software"
                />
                <meta
                    name="robots"
                    content="index, follow, max-image-preview:large"
                />
                <meta name="theme-color" content="#071D49" />
                <link rel="canonical" href={seo.canonical_url} />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="Bootcademy" />
                <meta
                    property="og:title"
                    content="Bootcademy · Every Academy. One Platform."
                />
                <meta
                    property="og:description"
                    content="Run learners, programmes, attendance, finance, branches and your academy website from one secure operational platform."
                />
                <meta property="og:url" content={seo.canonical_url} />
                <meta property="og:image" content={seo.image_url} />
                <meta name="twitter:card" content="summary_large_image" />
                <meta
                    name="twitter:title"
                    content="Bootcademy Academy Management Software"
                />
                <meta
                    name="twitter:description"
                    content="Every academy. One operational platform."
                />
                <meta name="twitter:image" content={seo.image_url} />
                <script type="application/ld+json">
                    {JSON.stringify(structuredData)}
                </script>
            </Head>

            <div className="min-h-screen bg-white font-sans text-[#071D49]">
                <header className="sticky top-0 z-50 border-b border-[#071D49]/8 bg-white/95 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8">
                        <Link href="/" aria-label="Bootcademy home">
                            <Brand />
                        </Link>
                        <nav
                            className="hidden items-center gap-7 text-sm font-bold text-[#071D49]/70 lg:flex"
                            aria-label="Main navigation"
                        >
                            {navigation.map(([label, href]) => (
                                <a
                                    key={href}
                                    href={href}
                                    className="transition hover:text-[#0878D1]"
                                >
                                    {label}
                                </a>
                            ))}
                        </nav>
                        <div className="hidden items-center gap-2 sm:flex">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center gap-2 rounded-full bg-[#FF6900] px-5 py-3 text-sm font-black text-white shadow-lg shadow-orange-500/20"
                                >
                                    Open dashboard{' '}
                                    <ArrowRight className="size-4" />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-full px-4 py-3 text-sm font-black text-[#0878D1]"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="inline-flex items-center gap-2 rounded-full bg-[#FF6900] px-5 py-3 text-sm font-black text-white shadow-lg shadow-orange-500/20"
                                    >
                                        Start now{' '}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </>
                            )}
                        </div>
                        <button
                            type="button"
                            className="grid size-11 place-items-center rounded-xl border border-[#071D49]/10 sm:hidden"
                            aria-label={
                                menuOpen
                                    ? 'Close navigation'
                                    : 'Open navigation'
                            }
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
                                    <a
                                        key={href}
                                        href={href}
                                        onClick={() => setMenuOpen(false)}
                                        className="rounded-xl px-3 py-3 text-sm font-bold hover:bg-[#F3F7FB]"
                                    >
                                        {label}
                                    </a>
                                ))}
                                <Link
                                    href={login()}
                                    className="mt-2 rounded-xl px-3 py-3 text-sm font-bold text-[#0878D1]"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={auth.user ? dashboard() : register()}
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

                <main>
                    <section className="relative overflow-hidden bg-[#071D49] text-white">
                        <div className="absolute inset-0 [background-image:radial-gradient(circle_at_20%_20%,#0878D1_0,transparent_34%),radial-gradient(circle_at_80%_80%,#12B8B0_0,transparent_30%)] opacity-20" />
                        <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 lg:grid-cols-[1.02fr_0.98fr] lg:py-24">
                            <div>
                                <p className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/8 px-4 py-2 text-xs font-black tracking-[0.08em] text-white/80 uppercase">
                                    <Sparkles className="size-3.5 text-[#12B8B0]" />{' '}
                                    Built for subscription-based academies
                                </p>
                                <h1 className="mt-7 max-w-3xl text-5xl leading-[0.98] font-black tracking-[-0.055em] text-balance sm:text-6xl lg:text-7xl">
                                    Run your academy.{' '}
                                    <span className="text-[#FF8A3D]">
                                        Grow with clarity.
                                    </span>
                                </h1>
                                <p className="mt-7 max-w-2xl text-lg leading-8 text-white/68">
                                    Bootcademy is the academy management
                                    software that connects applications,
                                    learners, programmes, attendance, payments,
                                    expenses, staff and branches—without the
                                    spreadsheet chaos.
                                </p>
                                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                    <Link
                                        href={
                                            auth.user ? dashboard() : register()
                                        }
                                        className="inline-flex items-center justify-center gap-2 rounded-full bg-[#FF6900] px-7 py-4 text-sm font-black text-white shadow-xl shadow-orange-950/30 transition hover:-translate-y-0.5 hover:bg-[#F05F00]"
                                    >
                                        {auth.user
                                            ? 'Open your workspace'
                                            : 'Start your academy workspace'}{' '}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                    <a
                                        href="#pricing"
                                        className="inline-flex items-center justify-center rounded-full border border-white/20 bg-white/8 px-7 py-4 text-sm font-black text-white transition hover:bg-white/12"
                                    >
                                        View pricing
                                    </a>
                                </div>
                                <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-xs font-bold text-white/55">
                                    {[
                                        'Multi-branch ready',
                                        'Paystack verified',
                                        'Role-based access',
                                    ].map((item) => (
                                        <span
                                            key={item}
                                            className="flex items-center gap-2"
                                        >
                                            <Check className="size-3.5 text-[#12B8B0]" />{' '}
                                            {item}
                                        </span>
                                    ))}
                                </div>
                            </div>
                            <ProductPreview />
                        </div>
                    </section>

                    <section
                        className="border-b bg-white px-5 py-8 sm:px-8"
                        aria-label="Academy categories"
                    >
                        <div className="mx-auto max-w-7xl">
                            <p className="text-center text-xs font-black tracking-[0.16em] text-[#0878D1] uppercase">
                                One flexible platform for
                            </p>
                            <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                {academyTypes.map(([label, Icon]) => (
                                    <div
                                        key={label}
                                        className="flex items-center justify-center gap-2 rounded-xl border border-[#071D49]/8 bg-[#F8FAFC] px-3 py-3 text-xs font-bold text-[#071D49]/70"
                                    >
                                        <Icon className="size-4 text-[#12B8B0]" />{' '}
                                        {label}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="product"
                        className="scroll-mt-24 px-5 py-20 sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="grid items-center gap-12 lg:grid-cols-[0.92fr_1.08fr]">
                                <div className="relative">
                                    <img
                                        src="/images/bootcademy-academy-owner.jpg"
                                        alt="Academy owner coordinating sports and learning programmes with Bootcademy"
                                        className="aspect-[4/3] w-full rounded-[2rem] object-cover shadow-2xl shadow-[#071D49]/15"
                                        width="1800"
                                        height="1013"
                                    />
                                    <div className="absolute -right-3 -bottom-6 max-w-[220px] rounded-2xl bg-white p-4 shadow-xl sm:right-6">
                                        <p className="text-xs font-black text-[#0878D1]">
                                            One operational view
                                        </p>
                                        <p className="mt-1 text-xs leading-5 text-[#071D49]/60">
                                            Across programmes, people, branches
                                            and finances.
                                        </p>
                                    </div>
                                </div>
                                <div className="lg:pl-8">
                                    <p className="text-xs font-black tracking-[0.16em] text-[#FF6900] uppercase">
                                        From enquiry to enrolment
                                    </p>
                                    <h2 className="mt-4 text-4xl leading-tight font-black tracking-[-0.045em] text-balance sm:text-5xl">
                                        One source of truth for your whole
                                        academy.
                                    </h2>
                                    <p className="mt-6 text-base leading-7 text-[#071D49]/65">
                                        A learner should not disappear between a
                                        website form, a payment alert, a coach’s
                                        register and a finance spreadsheet.
                                        Bootcademy keeps the operational story
                                        connected.
                                    </p>
                                    <div className="mt-8 grid gap-4 sm:grid-cols-2">
                                        {[
                                            [
                                                '01',
                                                'Apply',
                                                'Capture biodata, custom fields, programme, cycle and guardian details.',
                                            ],
                                            [
                                                '02',
                                                'Collect',
                                                'Present fees and installments, verify payments and email receipts.',
                                            ],
                                            [
                                                '03',
                                                'Operate',
                                                'Manage sessions, attendance, instructors, branches and enrolments.',
                                            ],
                                            [
                                                '04',
                                                'Understand',
                                                'See what was billed, collected, owed and spent in the active cycle.',
                                            ],
                                        ].map(([number, title, text]) => (
                                            <article
                                                key={number}
                                                className="rounded-2xl bg-[#F3F7FB] p-5"
                                            >
                                                <span className="text-xs font-black text-[#12B8B0]">
                                                    {number}
                                                </span>
                                                <h3 className="mt-2 font-black">
                                                    {title}
                                                </h3>
                                                <p className="mt-2 text-sm leading-6 text-[#071D49]/60">
                                                    {text}
                                                </p>
                                            </article>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="features"
                        className="scroll-mt-24 bg-[#F3F7FB] px-5 py-20 sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="max-w-3xl">
                                <p className="text-xs font-black tracking-[0.16em] text-[#0878D1] uppercase">
                                    Practical academy operations
                                </p>
                                <h2 className="mt-4 text-4xl font-black tracking-[-0.045em] sm:text-5xl">
                                    The tools your team needs, without the
                                    clutter.
                                </h2>
                                <p className="mt-5 text-base leading-7 text-[#071D49]/65">
                                    Built as one secure workspace, so every
                                    record contributes to a clearer view of the
                                    academy.
                                </p>
                            </div>
                            <div className="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {featureGroups.map(
                                    ([title, text, Icon, tone]) => (
                                        <article
                                            key={title}
                                            className="group rounded-3xl border border-[#071D49]/7 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-[#071D49]/7"
                                        >
                                            <span
                                                className={`grid size-12 place-items-center rounded-2xl ${tone}`}
                                            >
                                                <Icon className="size-5" />
                                            </span>
                                            <h3 className="mt-6 text-lg font-black">
                                                {title}
                                            </h3>
                                            <p className="mt-3 text-sm leading-6 text-[#071D49]/60">
                                                {text}
                                            </p>
                                        </article>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>

                    <section
                        id="academies"
                        className="scroll-mt-24 overflow-hidden bg-[#071D49] px-5 py-20 text-white sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto grid max-w-7xl items-center gap-12 lg:grid-cols-2">
                            <div>
                                <p className="text-xs font-black tracking-[0.16em] text-[#12B8B0] uppercase">
                                    Your academy, your language
                                </p>
                                <h2 className="mt-4 text-4xl font-black tracking-[-0.045em] sm:text-5xl">
                                    Flexible enough for the way you already
                                    work.
                                </h2>
                                <p className="mt-6 text-base leading-7 text-white/65">
                                    Call them students, players, members or
                                    customers. Call your team coaches, tutors or
                                    instructors. Bootcademy keeps the database
                                    dependable while adapting the words your
                                    academy uses.
                                </p>
                                <div className="mt-8 space-y-4">
                                    {[
                                        [
                                            'Custom profiles',
                                            'Collect academy-specific fields such as playing position, allergies, tools, level or jersey size.',
                                        ],
                                        [
                                            'Branded public presence',
                                            'Use your academy logo, colours, programmes, locations and application form on your own website.',
                                        ],
                                        [
                                            'Structured access',
                                            'Give each person the access their role and branch require—no more, no less.',
                                        ],
                                    ].map(([title, text]) => (
                                        <div key={title} className="flex gap-3">
                                            <span className="mt-1 grid size-6 shrink-0 place-items-center rounded-full bg-[#12B8B0]/15 text-[#12B8B0]">
                                                <Check className="size-3.5" />
                                            </span>
                                            <div>
                                                <h3 className="font-black">
                                                    {title}
                                                </h3>
                                                <p className="mt-1 text-sm leading-6 text-white/55">
                                                    {text}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <img
                                src="/images/bootcademy-academy-team.jpg"
                                alt="Academy instructors, coaches and administrators planning together"
                                className="aspect-[3/2] w-full rounded-[2rem] object-cover shadow-2xl shadow-black/30"
                                width="1400"
                                height="933"
                                loading="lazy"
                            />
                        </div>
                    </section>

                    <section className="px-5 py-20 sm:px-8 lg:py-28">
                        <div className="mx-auto max-w-7xl">
                            <div className="grid gap-6 lg:grid-cols-2">
                                <div className="rounded-[2rem] bg-[#0878D1] p-8 text-white sm:p-10">
                                    <LockKeyhole className="size-8 text-white/80" />
                                    <h2 className="mt-8 text-3xl font-black tracking-[-0.04em]">
                                        Built around trust and accountability.
                                    </h2>
                                    <p className="mt-4 text-sm leading-7 text-white/70">
                                        Academy records stay scoped to the right
                                        tenant. Role-aware permissions,
                                        protected uploads, audit logs and
                                        verified financial transitions help your
                                        team work responsibly.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {[
                                        [
                                            'Tenant isolation',
                                            Building2,
                                            'Every academy workspace and record is kept within its verified academy boundary.',
                                        ],
                                        [
                                            'Financial history',
                                            Landmark,
                                            'Payments, allocations, write-offs and reversals remain traceable.',
                                        ],
                                        [
                                            'API-ready intake',
                                            FileSpreadsheet,
                                            'Versioned APIs support secure applications from an existing academy website.',
                                        ],
                                        [
                                            'Branded experience',
                                            Palette,
                                            'Each academy public site responds to its own identity, logo and colours.',
                                        ],
                                    ].map(([title, Icon, text]) => (
                                        <article
                                            key={String(title)}
                                            className="rounded-3xl border border-[#071D49]/8 bg-white p-6 shadow-sm"
                                        >
                                            <Icon className="size-5 text-[#FF6900]" />
                                            <h3 className="mt-5 font-black">
                                                {String(title)}
                                            </h3>
                                            <p className="mt-2 text-sm leading-6 text-[#071D49]/60">
                                                {String(text)}
                                            </p>
                                        </article>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="pricing"
                        className="scroll-mt-24 bg-[#F3F7FB] px-5 py-20 sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="mx-auto max-w-3xl text-center">
                                <p className="text-xs font-black tracking-[0.16em] text-[#FF6900] uppercase">
                                    Straightforward pricing
                                </p>
                                <h2 className="mt-4 text-4xl font-black tracking-[-0.045em] sm:text-5xl">
                                    Choose room for your academy to grow.
                                </h2>
                                <p className="mt-5 text-base leading-7 text-[#071D49]/65">
                                    All prices are in Nigerian naira. Annual
                                    billing includes the configured 10%
                                    discount.
                                </p>
                                <div className="mt-7 inline-flex rounded-full border border-[#071D49]/10 bg-white p-1 shadow-sm">
                                    <button
                                        type="button"
                                        onClick={() => setAnnual(false)}
                                        className={`rounded-full px-5 py-2.5 text-sm font-black ${!annual ? 'bg-[#071D49] text-white' : 'text-[#071D49]/55'}`}
                                    >
                                        Monthly
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setAnnual(true)}
                                        className={`rounded-full px-5 py-2.5 text-sm font-black ${annual ? 'bg-[#071D49] text-white' : 'text-[#071D49]/55'}`}
                                    >
                                        Annual{' '}
                                        <span className="text-[#12B8B0]">
                                            save 10%
                                        </span>
                                    </button>
                                </div>
                            </div>
                            {plans.length > 0 ? (
                                <div className="mt-12 grid gap-5 lg:grid-cols-3">
                                    {plans.map((plan) => {
                                        const featured =
                                            plan.code === 'professional';
                                        const amount = annual
                                            ? plan.annual_price
                                            : plan.monthly_price;
                                        return (
                                            <article
                                                key={plan.code}
                                                className={`relative flex flex-col rounded-[2rem] border p-7 sm:p-8 ${featured ? 'border-[#0878D1] bg-[#071D49] text-white shadow-2xl shadow-[#071D49]/20' : 'border-[#071D49]/8 bg-white'}`}
                                            >
                                                {featured && (
                                                    <span className="absolute -top-3 left-7 rounded-full bg-[#FF6900] px-4 py-1.5 text-[10px] font-black tracking-[0.12em] text-white uppercase">
                                                        Most popular
                                                    </span>
                                                )}
                                                <h3 className="text-xl font-black">
                                                    {plan.name}
                                                </h3>
                                                <p
                                                    className={`mt-2 text-sm ${featured ? 'text-white/55' : 'text-[#071D49]/55'}`}
                                                >
                                                    Up to{' '}
                                                    {plan.active_player_limit.toLocaleString()}{' '}
                                                    active learners
                                                </p>
                                                <div className="mt-7 flex items-end gap-2">
                                                    <span className="text-4xl font-black tracking-[-0.05em]">
                                                        {currencySymbol(
                                                            plan.currency,
                                                        )}
                                                        {formatMoneyAmount(
                                                            amount,
                                                        ).replace('.00', '')}
                                                    </span>
                                                    <span
                                                        className={`pb-1 text-xs ${featured ? 'text-white/50' : 'text-[#071D49]/45'}`}
                                                    >
                                                        /
                                                        {annual
                                                            ? 'year'
                                                            : 'month'}
                                                    </span>
                                                </div>
                                                <div
                                                    className={`my-7 h-px ${featured ? 'bg-white/10' : 'bg-[#071D49]/8'}`}
                                                />
                                                <ul className="space-y-3 text-sm">
                                                    {[
                                                        `${plan.active_player_limit.toLocaleString()} active learner records`,
                                                        'Secure academy workspace',
                                                        'Plan modules configured in your account',
                                                        'Records preserved when your plan changes',
                                                    ].map((item) => (
                                                        <li
                                                            key={item}
                                                            className="flex gap-2.5"
                                                        >
                                                            <Check className="mt-0.5 size-4 shrink-0 text-[#12B8B0]" />
                                                            <span
                                                                className={
                                                                    featured
                                                                        ? 'text-white/75'
                                                                        : 'text-[#071D49]/65'
                                                                }
                                                            >
                                                                {item}
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                                <Link
                                                    href={register()}
                                                    className={`mt-8 inline-flex items-center justify-center rounded-full px-5 py-3.5 text-sm font-black ${featured ? 'bg-[#FF6900] text-white' : 'bg-[#0878D1] text-white'}`}
                                                >
                                                    Choose {plan.name}
                                                </Link>
                                            </article>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="mx-auto mt-12 max-w-2xl rounded-3xl border border-[#071D49]/8 bg-white p-8 text-center">
                                    <p className="font-black">
                                        Pricing is being configured.
                                    </p>
                                    <p className="mt-2 text-sm text-[#071D49]/55">
                                        Create an account and we will help match
                                        the right plan to your academy.
                                    </p>
                                </div>
                            )}
                            <p className="mt-6 text-center text-xs leading-5 text-[#071D49]/45">
                                Plan modules and limits are data-driven and may
                                be configured for the academy. Payment
                                processing, messaging and other external service
                                charges are separate.
                            </p>
                        </div>
                    </section>

                    <section
                        id="faq"
                        className="scroll-mt-24 px-5 py-20 sm:px-8 lg:py-28"
                    >
                        <div className="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[0.72fr_1.28fr]">
                            <div>
                                <p className="text-xs font-black tracking-[0.16em] text-[#0878D1] uppercase">
                                    Frequently asked questions
                                </p>
                                <h2 className="mt-4 text-4xl font-black tracking-[-0.045em]">
                                    Before you move another spreadsheet.
                                </h2>
                                <p className="mt-5 text-sm leading-7 text-[#071D49]/60">
                                    A few useful answers about the Bootcademy
                                    academy management platform.
                                </p>
                            </div>
                            <div className="divide-y divide-[#071D49]/10 border-y border-[#071D49]/10">
                                {faqs.map(([question, answer]) => (
                                    <details
                                        key={question}
                                        className="group py-5"
                                    >
                                        <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-black">
                                            {question}
                                            <ChevronDown className="size-4 shrink-0 transition group-open:rotate-180" />
                                        </summary>
                                        <p className="max-w-3xl pt-4 text-sm leading-7 text-[#071D49]/60">
                                            {answer}
                                        </p>
                                    </details>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="px-5 pb-20 sm:px-8 lg:pb-28">
                        <div className="mx-auto max-w-7xl overflow-hidden rounded-[2.25rem] bg-[#0878D1] px-6 py-12 text-center text-white sm:px-12 lg:py-16">
                            <p className="text-xs font-black tracking-[0.16em] text-white/60 uppercase">
                                Every academy. One platform.
                            </p>
                            <h2 className="mx-auto mt-4 max-w-3xl text-4xl font-black tracking-[-0.05em] sm:text-5xl">
                                Give your academy an operating system built for
                                growth.
                            </h2>
                            <p className="mx-auto mt-5 max-w-2xl text-sm leading-7 text-white/70">
                                Start with the learners and programmes you
                                manage today. Keep the history you will need
                                tomorrow.
                            </p>
                            <Link
                                href={auth.user ? dashboard() : register()}
                                className="mt-8 inline-flex items-center gap-2 rounded-full bg-[#FF6900] px-7 py-4 text-sm font-black text-white shadow-xl shadow-blue-950/20"
                            >
                                {auth.user
                                    ? 'Open dashboard'
                                    : 'Create your account'}{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    </section>
                </main>

                <footer className="bg-[#071D49] px-5 py-12 text-white sm:px-8">
                    <div className="mx-auto grid max-w-7xl gap-10 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto]">
                        <div>
                            <Brand light />
                            <p className="mt-4 max-w-sm text-sm leading-6 text-white/50">
                                Academy management software for the people,
                                programmes, payments and progress that keep an
                                academy moving.
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-black tracking-[0.12em] text-white/40 uppercase">
                                Explore
                            </p>
                            <div className="mt-4 grid gap-3 text-sm text-white/65">
                                {navigation.slice(0, 4).map(([label, href]) => (
                                    <a
                                        key={href}
                                        href={href}
                                        className="hover:text-white"
                                    >
                                        {label}
                                    </a>
                                ))}
                            </div>
                        </div>
                        <div>
                            <p className="text-xs font-black tracking-[0.12em] text-white/40 uppercase">
                                Account
                            </p>
                            <div className="mt-4 grid gap-3 text-sm text-white/65">
                                <Link
                                    href={login()}
                                    className="hover:text-white"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="hover:text-white"
                                >
                                    Create account
                                </Link>
                            </div>
                        </div>
                    </div>
                    <div className="mx-auto mt-10 flex max-w-7xl flex-col gap-2 border-t border-white/10 pt-6 text-xs text-white/35 sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            © {new Date().getFullYear()} Bootcademy. All rights
                            reserved.
                        </p>
                        <p>Built for academies of every kind.</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
