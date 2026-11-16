export type MarketingPageDefinition = {
    path: string;
    title: string;
    description: string;
    keywords: string;
    eyebrow: string;
    headline: string;
    accent: string;
    introduction: string;
    proof: string[];
    problemTitle: string;
    problem: string;
    outcomes: Array<{ title: string; description: string }>;
    workflow: Array<{ title: string; description: string }>;
    capabilities: string[];
    related: Array<{ label: string; href: string }>;
    faqs: Array<{ question: string; answer: string }>;
};

export const marketingPages: Record<string, MarketingPageDefinition> = {
    features: {
        path: '/features',
        title: 'Academy Management Software Features',
        description:
            'Explore Bootcademy features for academy websites, admissions, learners, programmes, branches, fees, attendance, scholarships, referrals, inventory, expenses and analytics.',
        keywords:
            'academy management software features, academy administration system, learner management, fee management software, academy website',
        eyebrow: 'The complete academy operations toolkit',
        headline: 'Everything your academy needs to',
        accent: 'recruit, run and grow.',
        introduction:
            'Replace scattered forms, spreadsheets and payment chats with one connected workspace—from your public website and first application to attendance, receipts and operational reporting.',
        proof: [
            'Free branded academy website',
            'Multiple branches in one workspace',
            'Secure roles and audit history',
        ],
        problemTitle: 'One platform, not another disconnected tool.',
        problem:
            'Bootcademy connects the public journey and the back office. Applications become learner records, programme fees become invoices, verified payments update balances, and branch activity contributes to one operational view.',
        outcomes: [
            {
                title: 'Attract and admit',
                description:
                    'Publish a branded website, show programmes, collect online applications and move candidates through a visible admissions pipeline.',
            },
            {
                title: 'Deliver programmes',
                description:
                    'Organise programmes, cohorts, branches, instructors, sessions and attendance without rebuilding the same records.',
            },
            {
                title: 'Control academy finance',
                description:
                    'Set fees and payment plans, issue invoices and receipts, record expenses, and follow outstanding balances.',
            },
            {
                title: 'Grow with accountability',
                description:
                    'Manage scholarships, referrals, coupons, stock and role-based access while preserving a useful audit trail.',
            },
        ],
        workflow: [
            {
                title: 'Set up your academy',
                description:
                    'Add your identity, terminology, branches, staff roles and programmes.',
            },
            {
                title: 'Open your front door',
                description:
                    'Publish your website, programme information and online application journey.',
            },
            {
                title: 'Run from one record',
                description:
                    'Review, enrol, bill, collect, schedule, mark attendance and report without duplicate entry.',
            },
        ],
        capabilities: [
            'Online registration and application tracking',
            'Learner, guardian and instructor records',
            'Programmes, cohorts, cycles and terms',
            'Multi-branch schedules and permissions',
            'Fees, invoices, installments and receipts',
            'Scholarships, waivers, referrals and coupons',
            'Attendance, expenses and operational analytics',
            'Inventory, storefront and order tracking',
        ],
        related: [
            {
                label: 'Free academy website',
                href: '/features/academy-website',
            },
            {
                label: 'Admissions management',
                href: '/features/admissions-and-enrolment',
            },
            {
                label: 'Fees and payments',
                href: '/features/fees-invoicing-payments',
            },
        ],
        faqs: [
            {
                question: 'What does Bootcademy replace?',
                answer: 'Bootcademy can replace separate registration forms, applicant trackers, learner spreadsheets, attendance registers, invoice sheets and disconnected academy reports with one linked operational system.',
            },
            {
                question: 'Can we start with only the features we need?',
                answer: 'Yes. Academy plans and entitlements are configured in the platform, and your records are preserved as your needs change.',
            },
            {
                question: 'Is it only for schools?',
                answer: 'No. Bootcademy supports sports, football, skills, language, creative and other subscription-based academies, with adaptable learner and instructor terminology.',
            },
        ],
    },
    'academy-management-software': {
        path: '/academy-management-software',
        title: 'Academy Management Software for Growing Academies',
        description:
            'Manage registrations, learners, instructors, programmes, branches, attendance, fees, payments, expenses and reporting with Bootcademy academy management software.',
        keywords:
            'academy management software, academy administration software, academy management system Nigeria, training academy software',
        eyebrow: 'Academy management software',
        headline: 'Run your entire academy from',
        accent: 'one clear workspace.',
        introduction:
            'Bootcademy gives academy owners and teams one place to manage the public website, admissions, people, programmes, fees, attendance and branch performance.',
        proof: [
            'Built for subscription-based academies',
            'Nigerian naira and Paystack support',
            'Works on mobile and desktop',
        ],
        problemTitle: 'Stop rebuilding the same learner record everywhere.',
        problem:
            'A registration form, finance spreadsheet, attendance book and WhatsApp thread each show only part of the academy. Bootcademy keeps the journey connected so your team can act from the same current information.',
        outcomes: [
            {
                title: 'Know every application',
                description:
                    'Track candidates by intake, programme, branch and status from submission to enrolment.',
            },
            {
                title: 'Know every balance',
                description:
                    'Connect fee plans, invoices, verified payments, receipts, adjustments and outstanding amounts.',
            },
            {
                title: 'Know every session',
                description:
                    'Schedule delivery, assign instructors and capture attendance in an academy-aware calendar.',
            },
            {
                title: 'Know every branch',
                description:
                    'Filter people, finance and operations by location while academy leadership retains a consolidated view.',
            },
        ],
        workflow: [
            {
                title: 'Configure',
                description:
                    'Create your academy, branches, programmes, cycles, staff roles and fee plans.',
            },
            {
                title: 'Connect',
                description:
                    'Collect applications through your Bootcademy site or a secured API from an existing website.',
            },
            {
                title: 'Operate',
                description:
                    'Run admissions, enrolment, finance, sessions, attendance and reporting from linked records.',
            },
        ],
        capabilities: [
            'Branded public academy website',
            'Application and admissions pipeline',
            'Learner, guardian and instructor management',
            'Programme, cohort and branch management',
            'Invoices, payments, receipts and expenses',
            'Attendance and operational dashboards',
            'Role-based access and audit logs',
            'CSV imports and permission-aware exports',
        ],
        related: [
            {
                label: 'All platform features',
                href: '/features',
            },
            {
                label: 'Sports academy software',
                href: '/sports-academy-management-software',
            },
            {
                label: 'Programme and branch management',
                href: '/features/programmes-cohorts-branches',
            },
        ],
        faqs: [
            {
                question: 'What is academy management software?',
                answer: 'Academy management software centralises admissions, learners, programmes, staff, attendance, finance and reporting so an academy can operate from consistent records.',
            },
            {
                question: 'Can Bootcademy support different academy types?',
                answer: 'Yes. Terminology can adapt to players and coaches, students and teachers, or learners and instructors without changing the secure multi-tenant foundation.',
            },
            {
                question: 'Can users belong to more than one academy?',
                answer: 'Yes. Memberships and permissions are scoped per academy, and authorised users can switch between the academies they belong to.',
            },
        ],
    },
    'sports-academy-management-software': {
        path: '/sports-academy-management-software',
        title: 'Sports Academy Management Software',
        description:
            'Sports academy management software for online registration, players, coaches, branches, training sessions, attendance, fees, receipts, expenses and reporting.',
        keywords:
            'sports academy management software, sports academy software, player management system, coach attendance software, sports school management',
        eyebrow: 'For sports academies',
        headline: 'Manage players, coaches and payments',
        accent: 'beyond the pitch.',
        introduction:
            'Give coaches a practical attendance workflow, give finance a traceable fee history, and give owners a clear multi-branch view—without running the academy from scattered spreadsheets.',
        proof: [
            'Player and guardian records',
            'Coach-friendly attendance',
            'Branch-level finance visibility',
        ],
        problemTitle:
            'Your coaching programme deserves an organised back office.',
        problem:
            'Sports academies coordinate children, guardians, coaches, venues, schedules and recurring fees. Bootcademy links those moving parts while keeping access scoped to the right academy, role and branch.',
        outcomes: [
            {
                title: 'Register players online',
                description:
                    'Collect player and guardian details, consent, preferred programme, branch and intake from a branded application journey.',
            },
            {
                title: 'Run training sessions',
                description:
                    'Schedule sessions across locations, assign coaches and manage physical or virtual delivery.',
            },
            {
                title: 'Capture attendance',
                description:
                    'Use a mobile-friendly roster for present, absent, late or excused statuses with protected notes.',
            },
            {
                title: 'Follow fees and expenses',
                description:
                    'Track invoices, installments, receipts, outstanding fees, vendors and branch-attributed expenses.',
            },
        ],
        workflow: [
            {
                title: 'Recruit',
                description:
                    'Publish programmes and locations, then accept player applications online.',
            },
            {
                title: 'Organise',
                description:
                    'Place accepted players into the right programme, cohort, branch and payment plan.',
            },
            {
                title: 'Deliver',
                description:
                    'Schedule training, mark attendance, collect fees and review performance by branch.',
            },
        ],
        capabilities: [
            'Players, guardians and consent records',
            'Coaches and branch assignments',
            'Programmes, age bands and cohorts',
            'Training sessions and attendance',
            'Invoices, online payments and receipts',
            'Scholarships, waivers and referrals',
            'Uniform and equipment inventory',
            'Multi-branch dashboards and exports',
        ],
        related: [
            {
                label: 'Football academy software',
                href: '/football-academy-management-software',
            },
            {
                label: 'Admissions and enrolment',
                href: '/features/admissions-and-enrolment',
            },
            {
                label: 'Fees and payments',
                href: '/features/fees-invoicing-payments',
            },
        ],
        faqs: [
            {
                question: 'Can coaches use Bootcademy on a phone?',
                answer: 'Yes. The web application is responsive, including coach-facing session and attendance workflows.',
            },
            {
                question: 'Can one sports academy manage several locations?',
                answer: 'Yes. Branches, programmes, sessions, staff access, applications, finance and reporting can be scoped by location.',
            },
            {
                question: 'Does it protect children’s records?',
                answer: 'The platform is designed around tenant isolation, role-based access, protected documents, guardian consent and auditable activity. Each academy remains responsible for its lawful data practices.',
            },
        ],
    },
    'football-academy-management-software': {
        path: '/football-academy-management-software',
        title: 'Football Academy Management Software',
        description:
            'Run football academy registrations, player and guardian records, coaches, branches, training attendance, fees, receipts, scholarships, referrals and kit inventory.',
        keywords:
            'football academy management software, football academy software, soccer academy management software, player registration software Nigeria',
        eyebrow: 'For football academies',
        headline: 'From player registration to match-ready',
        accent: 'academy operations.',
        introduction:
            'Bootcademy keeps player intake, training groups, coaches, locations, attendance and recurring fees together, giving football academy teams a reliable operational view.',
        proof: [
            'Online player registration',
            'Multiple training locations',
            'Fees, receipts and kit stock',
        ],
        problemTitle: 'Spend less time reconciling lists before training.',
        problem:
            'When player names, guardian contacts, balances and attendance live in separate files, simple questions take too long to answer. Bootcademy connects each player to the programme, branch, cohort, session and finance records your team needs.',
        outcomes: [
            {
                title: 'Build a complete player directory',
                description:
                    'Keep player, guardian, emergency contact, programme, branch and enrolment details in one academy-scoped record.',
            },
            {
                title: 'Coordinate coaches and groups',
                description:
                    'Assign coaches to branches and sessions, organise cohorts, and keep roster access role-aware.',
            },
            {
                title: 'Make fee status visible',
                description:
                    'Issue invoices, offer approved payment plans, record verified payments and produce receipts without deleting history.',
            },
            {
                title: 'Manage uniforms and equipment',
                description:
                    'Track products, variants, branch stock, transfers, orders and fulfilment for academy merchandise.',
            },
        ],
        workflow: [
            {
                title: 'Open registration',
                description:
                    'Show programmes, age groups, locations and available intakes on your academy website.',
            },
            {
                title: 'Review and enrol',
                description:
                    'Move accepted applicants into the right branch, cohort and fee arrangement.',
            },
            {
                title: 'Train and track',
                description:
                    'Run sessions, attendance, collections, expenses, referrals and inventory from linked records.',
            },
        ],
        capabilities: [
            'Football player registration form',
            'Guardian and consent records',
            'Age groups, programmes and cohorts',
            'Coach and training session management',
            'Attendance on mobile browsers',
            'Fee plans, installments and receipts',
            'Referral reward administration',
            'Uniform and merchandise stock',
        ],
        related: [
            {
                label: 'Sports academy platform',
                href: '/sports-academy-management-software',
            },
            {
                label: 'Free football academy website',
                href: '/features/academy-website',
            },
            {
                label: 'Programmes, cohorts and branches',
                href: '/features/programmes-cohorts-branches',
            },
        ],
        faqs: [
            {
                question: 'Is Bootcademy only for football academies?',
                answer: 'No. Football is an important use case, but Bootcademy also supports other sports, skills, language, creative and learning academies.',
            },
            {
                question: 'Can we organise players by age group and branch?',
                answer: 'Yes. Programmes, cohorts, intakes, sessions and enrolments can be organised around branches and academy-defined groupings.',
            },
            {
                question: 'Can we sell kits through the academy website?',
                answer: 'Yes. The commerce module supports a public storefront, product variants, orders, branch stock and fulfilment workflows.',
            },
        ],
    },
    'academy-website': {
        path: '/features/academy-website',
        title: 'Free Academy Website with Online Registration',
        description:
            'Launch a free branded academy website with programme pages, branches, online registration, fee options, Paystack payments and an optional storefront.',
        keywords:
            'free academy website, academy website builder, online academy registration form, football academy website, sports academy website',
        eyebrow: 'Free academy website included',
        headline: 'Turn interest into applications with a',
        accent: 'website built into your system.',
        introduction:
            'Publish your academy identity, programmes, locations and application journey without maintaining a separate website database. Every submission enters the same secure admissions workflow your team uses.',
        proof: [
            'Your logo and academy colours',
            'Online forms connected to admissions',
            'Mobile-ready programme pages',
        ],
        problemTitle:
            'Your website should do more than display a phone number.',
        problem:
            'A Bootcademy academy site is connected to your operational data. Published programmes and branches stay aligned with applications, public fee options, payments and the internal review pipeline.',
        outcomes: [
            {
                title: 'Look like your academy',
                description:
                    'Use your logo, accessible brand colours, academy terminology and configurable homepage sections.',
            },
            {
                title: 'Show useful programme detail',
                description:
                    'Publish programme descriptions, outcomes, eligibility, curriculum, tools, FAQs and available offerings.',
            },
            {
                title: 'Collect complete applications',
                description:
                    'Capture learner and guardian information, custom fields, intake choices and required declarations online.',
            },
            {
                title: 'Accept payments online',
                description:
                    'Present approved fee or installment options and verify Paystack transactions before updating academy records.',
            },
        ],
        workflow: [
            {
                title: 'Brand it',
                description:
                    'Add your logo, colours, contact details and the words your academy uses.',
            },
            {
                title: 'Publish it',
                description:
                    'Choose public programmes, locations, fee options, sections and application calls to action.',
            },
            {
                title: 'Manage every response',
                description:
                    'Receive each submission inside your admissions pipeline with a reference and status history.',
            },
        ],
        capabilities: [
            'Branded academy homepage',
            'Programme directory and detail pages',
            'Branch and contact information',
            'Online application form',
            'Public fee and payment-plan choices',
            'Paystack payment verification',
            'Optional academy storefront',
            'Secure API for an existing website',
        ],
        related: [
            {
                label: 'Admissions management',
                href: '/features/admissions-and-enrolment',
            },
            {
                label: 'Football academy software',
                href: '/football-academy-management-software',
            },
            {
                label: 'All academy features',
                href: '/features',
            },
        ],
        faqs: [
            {
                question: 'Is the academy website really included?',
                answer: 'Yes. Bootcademy includes a branded public academy website connected to your workspace. Your subscription plan, external payment charges and any custom implementation remain separate considerations.',
            },
            {
                question: 'Can we keep our existing website?',
                answer: 'Yes. You can use the included public site or integrate an existing website with Bootcademy through the versioned application API.',
            },
            {
                question: 'Can parents pay after applying?',
                answer: 'Yes. When an academy publishes eligible fee options, an applicant can select an approved plan and complete a Paystack payment that is verified server-side.',
            },
        ],
    },
    'admissions-and-enrolment': {
        path: '/features/admissions-and-enrolment',
        title: 'Online Academy Registration and Admissions Management',
        description:
            'Collect online academy applications, organise intakes and cohorts, review candidates, track statuses, capture guardians and enrol accepted learners.',
        keywords:
            'online academy registration form, admissions management software, student application tracking, learner enrolment system',
        eyebrow: 'Online registration and admissions',
        headline: 'Never lose an applicant between the form and',
        accent: 'the first session.',
        introduction:
            'Collect structured applications, organise them by intake, review every candidate and convert accepted applicants into connected learner and guardian records.',
        proof: [
            'Public online application form',
            'Visible application status',
            'Intakes, cohorts and enrolment',
        ],
        problemTitle: 'Admissions should be a workflow, not an inbox search.',
        problem:
            'Bootcademy gives each application a reference, programme context, branch and status. Your team can review the right information, record decisions and enrol accepted candidates without retyping their profiles.',
        outcomes: [
            {
                title: 'Capture the right details',
                description:
                    'Collect biodata, guardian information, programme and intake choices, custom fields and declarations.',
            },
            {
                title: 'Track every decision',
                description:
                    'Move applications through submitted, under-review, accepted or rejected states with an auditable history.',
            },
            {
                title: 'Organise demand',
                description:
                    'Use application intakes and cohorts to separate start dates, programmes, branches and available capacity.',
            },
            {
                title: 'Enrol without duplication',
                description:
                    'Create linked learner, guardian and enrolment records from an accepted application.',
            },
        ],
        workflow: [
            {
                title: 'Publish an intake',
                description:
                    'Choose the programme, cycle, branch, cohort, dates and public payment options.',
            },
            {
                title: 'Review applicants',
                description:
                    'Filter the pipeline, open complete records and document the admissions decision.',
            },
            {
                title: 'Enrol accepted learners',
                description:
                    'Carry forward verified details into the learner directory and programme enrolment.',
            },
        ],
        capabilities: [
            'Branded registration journey',
            'Application references and status tracking',
            'Custom academy fields',
            'Guardian and emergency contacts',
            'Intake opening and closing dates',
            'Programme, branch and cohort choices',
            'Application and programme payments',
            'Accepted-applicant enrolment',
        ],
        related: [
            {
                label: 'Academy website',
                href: '/features/academy-website',
            },
            {
                label: 'Programme and cohort management',
                href: '/features/programmes-cohorts-branches',
            },
            {
                label: 'Fees and payments',
                href: '/features/fees-invoicing-payments',
            },
        ],
        faqs: [
            {
                question: 'Can applicants track their application?',
                answer: 'Each public application receives a reference and a completion journey, while academy staff track its current admissions status inside the workspace.',
            },
            {
                question: 'Can different programmes have different forms?',
                answer: 'Academies can configure custom profile fields and publish programme-specific intakes, branches, cohorts and fee options.',
            },
            {
                question: 'Can existing learners be imported?',
                answer: 'Yes. Bootcademy includes a validated CSV import flow with preview, duplicate checks and error reporting.',
            },
        ],
    },
    'fees-invoicing-payments': {
        path: '/features/fees-invoicing-payments',
        title: 'Academy Fee Management, Invoicing and Receipts',
        description:
            'Set programme fees and payment plans, issue academy invoices, verify Paystack and manual payments, send receipts, track installments, balances and expenses.',
        keywords:
            'academy fee management software, school fee management system, invoicing software for academies, Paystack academy payments, receipt software',
        eyebrow: 'Fees, invoicing and receipts',
        headline: 'Know what was billed, paid, due and',
        accent: 'adjusted—without guesswork.',
        introduction:
            'Build programme fee plans, invoice learners, verify online or manual payments, issue receipts and preserve financial history across cycles and branches.',
        proof: [
            'Integer-based money records',
            'Verified and idempotent payments',
            'Traceable reversals and write-offs',
        ],
        problemTitle: 'A payment alert is not a fee ledger.',
        problem:
            'Bootcademy separates invoices, payment allocations, adjustments, reversals and expenses so finance teams can explain a balance instead of reconstructing it from screenshots and chats.',
        outcomes: [
            {
                title: 'Configure academy fees',
                description:
                    'Set effective programme-cycle fees, scoped items, application charges and public payment plans.',
            },
            {
                title: 'Offer approved installments',
                description:
                    'Define payment schedules whose installments reconcile to the contracted total.',
            },
            {
                title: 'Verify and receipt payments',
                description:
                    'Record manual payments or verify Paystack transactions, allocate them and generate receipts.',
            },
            {
                title: 'Preserve the explanation',
                description:
                    'Use explicit adjustments, write-offs and reversals instead of deleting original transactions.',
            },
        ],
        workflow: [
            {
                title: 'Set the fee structure',
                description:
                    'Define fee items, totals, payment-plan options and installment dates for an offering.',
            },
            {
                title: 'Invoice and collect',
                description:
                    'Create the learner obligation, accept authorised payment methods and issue a receipt.',
            },
            {
                title: 'Reconcile and report',
                description:
                    'Review collected, outstanding, overdue, adjusted and spent amounts by branch and period.',
            },
        ],
        capabilities: [
            'Programme and cycle fee schedules',
            'One-off and installment plans',
            'Invoices and outstanding balances',
            'Paystack and manual payment records',
            'Receipts and payment history',
            'Adjustments, write-offs and reversals',
            'Expense vendors and attachments',
            'Permission-aware finance exports',
        ],
        related: [
            {
                label: 'Scholarships and referrals',
                href: '/features/scholarships-referrals',
            },
            {
                label: 'Expenses and analytics',
                href: '/features/inventory-expenses-analytics',
            },
            {
                label: 'Admissions and enrolment',
                href: '/features/admissions-and-enrolment',
            },
        ],
        faqs: [
            {
                question: 'Does Bootcademy support installment payments?',
                answer: 'Yes. Academies can configure public payment plans with fee items and installments that reconcile to each plan’s total.',
            },
            {
                question: 'Does Bootcademy use Paystack?',
                answer: 'Yes. Paystack is implemented behind payment services with server-side verification, signature checks and idempotency controls. Manual payment recording is also supported.',
            },
            {
                question: 'Is Bootcademy full accounting software?',
                answer: 'No. It provides academy-focused invoicing, payment, balance, expense and operational reporting. It does not claim to replace a complete accounting system or audited financial statements.',
            },
        ],
    },
    'programmes-cohorts-branches': {
        path: '/features/programmes-cohorts-branches',
        title: 'Programme, Cohort and Multi-Branch Academy Management',
        description:
            'Manage academy programmes, cycles, terms, cohorts, intakes, instructors, sessions and multiple branches from one connected workspace.',
        keywords:
            'programme management software, cohort management system, multi branch academy software, training schedule management',
        eyebrow: 'Programmes, cohorts and branches',
        headline: 'Give every programme, cohort and location',
        accent: 'a clear operating structure.',
        introduction:
            'Organise what you teach, when you deliver it, who belongs to each group, where sessions happen and which staff can access each branch.',
        proof: [
            'Multiple branches supported',
            'Fixed-cycle or rolling programmes',
            'Branch-scoped roles and reporting',
        ],
        problemTitle:
            'Growth should not make the academy harder to understand.',
        problem:
            'Bootcademy separates programmes, cycles, terms, cohorts, intakes and branches while keeping their relationships clear. That structure supports accurate schedules, fees, enrolments and reports as the academy expands.',
        outcomes: [
            {
                title: 'Model the way you deliver',
                description:
                    'Use rolling or fixed cycles, optional terms, programme offerings, cohorts and branch-specific intakes.',
            },
            {
                title: 'Coordinate locations',
                description:
                    'Maintain venues, contacts, programmes, sessions, stock and staff assignments for multiple branches.',
            },
            {
                title: 'Keep schedules practical',
                description:
                    'Use list and calendar views, academy timezone presentation, filters and instructor assignments.',
            },
            {
                title: 'Control local access',
                description:
                    'Give branch managers and instructors access to the locations and records their roles require.',
            },
        ],
        workflow: [
            {
                title: 'Create the structure',
                description:
                    'Add branches, programmes, cycles, terms, cohorts and staff assignments.',
            },
            {
                title: 'Open delivery',
                description:
                    'Publish intakes, configure fees and enrol learners into the right programme context.',
            },
            {
                title: 'Run and compare',
                description:
                    'Schedule sessions, capture attendance and filter operational or financial results by branch.',
            },
        ],
        capabilities: [
            'Multiple academy branches and venues',
            'Programme profiles and public pages',
            'Rolling or fixed training cycles',
            'Optional terms and cohorts',
            'Application intakes and capacity',
            'Instructor and branch assignments',
            'Session list and calendar views',
            'Branch-filtered dashboards and exports',
        ],
        related: [
            {
                label: 'Academy management software',
                href: '/academy-management-software',
            },
            {
                label: 'Admissions management',
                href: '/features/admissions-and-enrolment',
            },
            {
                label: 'Sports academy software',
                href: '/sports-academy-management-software',
            },
        ],
        faqs: [
            {
                question: 'How many branches can an academy manage?',
                answer: 'The product supports multiple branches. Any commercial branch limit should come from the academy’s configured plan rather than a hardcoded product assumption.',
            },
            {
                question:
                    'What is the difference between a programme and a cohort?',
                answer: 'A programme describes the learning or training offer. A cohort groups learners receiving that offer together within a defined delivery context such as an intake, branch or start period.',
            },
            {
                question: 'Can staff access only assigned branches?',
                answer: 'Yes. Academy roles and branch assignments are enforced server-side for supported workflows, with tenant ownership checked for records and requests.',
            },
        ],
    },
    'scholarships-referrals': {
        path: '/features/scholarships-referrals',
        title: 'Academy Scholarships, Waivers, Referrals and Coupons',
        description:
            'Administer academy scholarships, sponsors, awards, fee waivers, referrals, coupon codes, bonuses and reward approval without losing the financial trail.',
        keywords:
            'scholarship management software, academy referral program software, fee waiver management, coupon and bonus tracking',
        eyebrow: 'Scholarships, waivers and growth rewards',
        headline: 'Support access and reward growth with',
        accent: 'a traceable process.',
        introduction:
            'Manage sponsors, scholarship schemes, awards and fee allocations alongside referral codes, qualification events, reward approval and payout records.',
        proof: [
            'Sponsor and scholarship ledgers',
            'Configurable referral rules',
            'Approval before reward payout',
        ],
        problemTitle:
            'Discounts and rewards should never become mystery numbers.',
        problem:
            'Bootcademy records why a learner received support or why a referrer earned a reward. Allocations and contributions remain connected to academy finance instead of disappearing into informal notes.',
        outcomes: [
            {
                title: 'Define scholarship support',
                description:
                    'Create sponsors, commitments, schemes, awards and learner allocations with clear values and status.',
            },
            {
                title: 'Apply support to fees',
                description:
                    'Record scholarship contributions and fee adjustments without deleting the original obligation.',
            },
            {
                title: 'Attribute referrals',
                description:
                    'Create referral codes, connect referred applications and reconcile configured qualifying events.',
            },
            {
                title: 'Approve rewards safely',
                description:
                    'Keep rewards pending until authorised, then record payout references and related expenses.',
            },
        ],
        workflow: [
            {
                title: 'Configure the rule',
                description:
                    'Set the scholarship scope or referral qualification and reward basis approved by the academy.',
            },
            {
                title: 'Record the event',
                description:
                    'Connect the learner, sponsor, application, payment or referral event to its source.',
            },
            {
                title: 'Review and account',
                description:
                    'Approve allocations or rewards, record contributions or payouts, and preserve the history.',
            },
        ],
        capabilities: [
            'Sponsor and commitment records',
            'Scholarship schemes and awards',
            'Learner award allocations',
            'Contribution and disbursement history',
            'Referral codes and attribution',
            'Configurable qualification events',
            'Pending, approved and paid rewards',
            'Coupon and bonus administration',
        ],
        related: [
            {
                label: 'Fees and invoicing',
                href: '/features/fees-invoicing-payments',
            },
            {
                label: 'Admissions and enrolment',
                href: '/features/admissions-and-enrolment',
            },
            {
                label: 'All platform features',
                href: '/features',
            },
        ],
        faqs: [
            {
                question: 'Can scholarship funds be connected to learner fees?',
                answer: 'Yes. Scholarship awards can be allocated to learners and contributions recorded against the supported obligation while retaining the underlying finance history.',
            },
            {
                question: 'Are referral percentages fixed?',
                answer: 'No. Referral qualification and reward rules are academy-configurable and should follow an approved business policy.',
            },
            {
                question: 'Can a reward be paid automatically?',
                answer: 'Bootcademy uses a pending, approved and paid workflow so academies can verify eligibility before recording a payout.',
            },
        ],
    },
    'inventory-expenses-analytics': {
        path: '/features/inventory-expenses-analytics',
        title: 'Academy Inventory, Expenses and Analytics',
        description:
            'Track academy products, branch stock, transfers, orders, vendors, expenses, attachments and operational dashboards for collections, attendance and outstanding fees.',
        keywords:
            'academy inventory management, sports academy expenses, school inventory software, academy analytics dashboard, branch stock management',
        eyebrow: 'Inventory, expenses and analytics',
        headline: 'See where money, stock and academy activity',
        accent: 'are moving.',
        introduction:
            'Track merchandise and equipment stock by branch, record operating expenses with supporting documents, and review academy metrics from the same operational source.',
        proof: [
            'Branch-level stock movements',
            'Expense vendors and attachments',
            'Operational—not fabricated—metrics',
        ],
        problemTitle: 'Revenue alone does not explain academy performance.',
        problem:
            'Bootcademy combines collections, outstanding fees, expenses, attendance and enrolment activity with inventory movements and orders so teams can investigate real operational records behind the totals.',
        outcomes: [
            {
                title: 'Control branch stock',
                description:
                    'Maintain products and variants, record adjustments and transfers, and preserve movement references.',
            },
            {
                title: 'Run academy orders',
                description:
                    'Take public or staff-created orders, allocate stock, record payments and manage fulfilment or cancellation.',
            },
            {
                title: 'Capture operating costs',
                description:
                    'Organise expenses by category, vendor, date and branch with protected supporting attachments.',
            },
            {
                title: 'Read the operating picture',
                description:
                    'Review active learners, enrolments, collections, outstanding fees, attendance and expenses by period and branch.',
            },
        ],
        workflow: [
            {
                title: 'Record the source',
                description:
                    'Create the product, stock location, vendor or expense category before activity begins.',
            },
            {
                title: 'Capture each movement',
                description:
                    'Record stock changes, transfers, orders, fulfilment, payments and operating expenses.',
            },
            {
                title: 'Review the result',
                description:
                    'Filter dashboards and exports by the date range, branch and permission scope that matter.',
            },
        ],
        capabilities: [
            'Products, categories and variants',
            'Branch stock and transfer history',
            'Public academy storefront',
            'Orders, payments and fulfilment',
            'Expense categories and vendors',
            'Protected receipt attachments',
            'Branch and date-range dashboards',
            'CSV finance and attendance exports',
        ],
        related: [
            {
                label: 'Fees and payments',
                href: '/features/fees-invoicing-payments',
            },
            {
                label: 'Programmes and branches',
                href: '/features/programmes-cohorts-branches',
            },
            {
                label: 'Sports academy software',
                href: '/sports-academy-management-software',
            },
        ],
        faqs: [
            {
                question: 'Does Bootcademy track inventory at each branch?',
                answer: 'Yes. Stock records and movements are branch-aware, including adjustments, transfers, allocations and order fulfilment.',
            },
            {
                question: 'Can expenses include supporting documents?',
                answer: 'Yes. Authorised users can attach protected expense evidence that is served through academy-scoped access controls.',
            },
            {
                question: 'Does the dashboard show profit?',
                answer: 'Bootcademy reports operational amounts such as billed, collected, outstanding and expenses. It does not label those figures as audited profit without the accounting controls required to support that claim.',
            },
        ],
    },
};
