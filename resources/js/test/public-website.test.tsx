import { fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AcademyWebsite from '@/pages/academies/website';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import PublicApplication from '@/pages/public/application';
import PublicApplicationFees from '@/pages/public/application-fees';
import PublicApplicationComplete from '@/pages/public/application-complete';
import PublicHome from '@/pages/public/home';
import PublicProgrammeDetails from '@/pages/public/programme';
import type {
    PublicAcademy,
    PublicApplicationIntake,
    PublicSite,
} from '@/types/public-site';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({
        children,
    }: {
        children: ReactNode | ((value: object) => ReactNode);
    }) => (
        <form>
            {typeof children === 'function'
                ? children({ processing: false, errors: {} })
                : children}
        </form>
    ),
    usePage: () => ({ props: { errors: {} } }),
}));

const academy: PublicAcademy = {
    name: 'Future Academy',
    type: 'Coding',
    logo_url: null,
    member_singular: 'Student',
    member_plural: 'Students',
    currency: 'NGN',
    theme: {
        primary: '#2563EB',
        primary_foreground: '#FFFFFF',
        secondary: '#DBEAFE',
        secondary_foreground: '#172554',
    },
};

const site: PublicSite = {
    subdomain: 'future-academy',
    theme_key: 'launch',
    sections: {
        topbar: {
            enabled: true,
            title: 'Contact',
            phone: '2348000000000',
            email: 'hello@example.test',
            socials: '',
            quick_links: 'Apply | /apply',
        },
        announcement: {
            enabled: true,
            title: 'Announcements',
            text: 'Admissions are open',
        },
        header: {
            enabled: true,
            title: 'Header',
            application_label: 'Apply now',
        },
        hero: {
            enabled: true,
            title: 'Hero',
            eyebrow: 'Learn and grow',
            headline: 'A future worth building',
            description: 'Practical learning for ambitious people.',
            media_type: 'slides',
            slides: 'A future worth building | Practical learning for ambitious people. | | Explore | /programmes',
        },
        programmes: {
            enabled: true,
            title: 'Learning pathways',
            intro: 'Choose the right pathway.',
        },
        locations: {
            enabled: false,
            title: 'Locations',
        },
        footer: {
            enabled: true,
            title: 'Footer',
            summary: 'Learn with purpose.',
            copyright: '',
        },
        whatsapp: {
            enabled: true,
            title: 'WhatsApp',
            phone: '2348000000000',
            message: 'Hello',
            label: 'Chat with us',
        },
    },
};

const applicationIntake: PublicApplicationIntake = {
    id: 9,
    programme_cycle_id: 9,
    programme_id: 3,
    programme_name: 'Software Engineering',
    cycle_id: 2,
    cycle_name: '2027 Cohort',
    branch_id: null,
    branch_name: 'All locations',
    cohort_id: 7,
    cohort_name: 'January cohort',
    opens_on: null,
    closes_on: null,
    fee_name: 'Tuition',
    currency: 'NGN',
    application_fee: '5000.00',
    total: '120000.00',
    fee_items: [{ name: 'Tuition', amount: '120000.00' }],
    installments: [
        {
            name: 'First payment',
            amount: '60000.00',
            due_on: null,
            due_offset_days: 0,
        },
        {
            name: 'Final payment',
            amount: '60000.00',
            due_on: null,
            due_offset_days: 30,
        },
    ],
    payment_plans: [
        {
            id: 31,
            name: 'Pay once',
            description: 'Lowest total price.',
            total: '100000.00',
            is_default: false,
            items: [
                {
                    name: 'Tuition',
                    description: 'One payment',
                    amount: '100000.00',
                },
            ],
            installments: [
                {
                    name: 'Full payment',
                    amount: '100000.00',
                    due_on: null,
                    due_offset_days: 0,
                },
            ],
        },
        {
            id: 32,
            name: 'Two payments',
            description: 'Split the programme fee into two payments.',
            total: '120000.00',
            is_default: true,
            items: [
                {
                    name: 'Tuition',
                    description: '₦10,000/month',
                    amount: '120000.00',
                },
            ],
            installments: [
                {
                    name: 'First payment',
                    amount: '60000.00',
                    due_on: null,
                    due_offset_days: 0,
                },
                {
                    name: 'Final payment',
                    amount: '60000.00',
                    due_on: null,
                    due_offset_days: 30,
                },
            ],
        },
    ],
};

describe('Bootcademy public website', () => {
    it('lets an academy configure sections through accordions', () => {
        render(
            <AcademyWebsite
                website={{
                    subdomain: 'future-academy',
                    theme_key: 'launch',
                    is_published: true,
                    preview_url: '/sites/future-academy',
                    public_url: 'https://future-academy.bootcademy.com',
                    sections: {
                        hero: {
                            enabled: true,
                            title: 'Welcome',
                            headline: 'Build your future',
                        },
                    },
                }}
                definitions={{
                    hero: {
                        label: 'Hero',
                        description: 'Homepage introduction',
                        enabled: true,
                        title: 'Hero',
                        fields: {
                            headline: {
                                label: 'Headline',
                                type: 'text',
                                default: '',
                                help: '',
                            },
                        },
                    },
                }}
                tenant_domain="bootcademy.com"
            />,
        );

        expect(screen.getByLabelText('Academy subdomain')).toHaveValue(
            'future-academy',
        );
        expect(screen.getByText('.bootcademy.com')).toBeInTheDocument();
        expect(screen.getByText('Hero')).toBeInTheDocument();
        expect(screen.getByLabelText('Public section name')).toHaveValue(
            'Welcome',
        );
        expect(screen.getByLabelText('Headline')).toHaveValue(
            'Build your future',
        );
    });

    it('presents the built-in online store as a visible website module', () => {
        render(
            <AcademyWebsite
                website={{
                    subdomain: 'future-academy',
                    theme_key: 'launch',
                    is_published: true,
                    preview_url: '/sites/future-academy',
                    public_url: 'https://future-academy.bootcademy.com',
                    sections: {
                        store: {
                            enabled: true,
                            title: 'Academy Store',
                            intro: 'Shop official items.',
                        },
                    },
                }}
                definitions={{
                    store: {
                        label: 'Store',
                        description: 'Public cart and checkout',
                        enabled: false,
                        title: 'Store',
                        fields: {
                            intro: {
                                label: 'Introduction',
                                type: 'textarea',
                                default: '',
                                help: '',
                            },
                        },
                    },
                }}
                tenant_domain="bootcademy.com"
                store_module={{
                    enabled: true,
                    product_count: 4,
                    active_product_count: 3,
                    order_count: 2,
                    manage_url: '/app/store',
                    preview_url: '/sites/future-academy/store',
                }}
            />,
        );

        expect(screen.getByText('Online Store module')).toBeVisible();
        expect(screen.getByText('Visible on website')).toBeVisible();
        expect(
            screen.getByRole('link', { name: 'Manage products' }),
        ).toHaveAttribute('href', '/app/store');
        expect(
            screen.getByRole('link', { name: 'Configure Store' }),
        ).toHaveAttribute('href', '#section-store');
        expect(
            screen.getByRole('link', { name: /Preview Store/i }),
        ).toHaveAttribute('href', '/sites/future-academy/store');
        expect(screen.getByLabelText('Show this section')).toBeChecked();
    });

    it('renders the branded theme with live programmes and WhatsApp contact', () => {
        render(
            <PublicHome
                academy={academy}
                site={site}
                base_url="/sites/future-academy"
                programmes={[
                    {
                        id: 1,
                        name: 'Software Engineering',
                        code: 'SWE',
                        description: 'Build practical software skills.',
                        url: '/sites/future-academy/programmes/1',
                    },
                ]}
                locations={[]}
            />,
        );

        expect(
            screen.getByRole('heading', {
                name: 'A future worth building',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('Software Engineering')).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'Chat with the academy on WhatsApp',
            }),
        ).toHaveAttribute(
            'href',
            expect.stringContaining('wa.me/2348000000000'),
        );
        expect(screen.getByText('Website powered by')).toBeInTheDocument();
        expect(screen.getByText('Bootcademy')).toBeInTheDocument();
    });

    it('keeps four priority links visible and groups other enabled sections under Explore', () => {
        const navigationSite: PublicSite = {
            ...site,
            sections: {
                ...site.sections,
                about: { enabled: true, title: 'About us' },
                store: { enabled: true, title: 'Store' },
                locations: { enabled: true, title: 'Locations' },
                experience: { enabled: true, title: 'Why choose us' },
                events: { enabled: true, title: 'Upcoming events' },
                news: { enabled: true, title: 'Featured news' },
                gallery: { enabled: true, title: 'Gallery' },
                faq: { enabled: true, title: 'FAQ' },
                testimonials: {
                    enabled: '0' as unknown as boolean,
                    title: 'Disabled testimonials',
                },
            },
        };

        render(
            <PublicSiteShell
                academy={academy}
                site={navigationSite}
                baseUrl="/sites/future-academy"
            >
                <div>Page content</div>
            </PublicSiteShell>,
        );

        const navigation = screen.getByRole('navigation', {
            name: 'Main navigation',
        });
        expect(navigation.querySelectorAll(':scope > a')).toHaveLength(4);
        expect(
            within(navigation).getByRole('link', { name: 'About us' }),
        ).toBeInTheDocument();
        expect(
            within(navigation).getByRole('link', {
                name: 'Learning pathways',
            }),
        ).toBeInTheDocument();
        expect(
            within(navigation).getByRole('link', { name: 'Store' }),
        ).toHaveAttribute('href', '/sites/future-academy/store');
        const explore = within(navigation).getByText('Explore');
        const dropdown = explore.closest('details');
        expect(dropdown).not.toBeNull();
        expect(
            within(dropdown!).getByRole('link', { name: 'Upcoming events' }),
        ).toBeInTheDocument();
        expect(
            within(dropdown!).getByRole('link', { name: 'Featured news' }),
        ).toBeInTheDocument();
        expect(
            within(dropdown!).getByRole('link', { name: 'Gallery' }),
        ).toBeInTheDocument();
        expect(
            within(dropdown!).queryByRole('link', {
                name: 'Disabled testimonials',
            }),
        ).not.toBeInTheDocument();
    });

    it('selects a payment plan in the application summary before submission', () => {
        render(
            <PublicApplication
                academy={{
                    ...academy,
                    guardian_singular: 'Next of Kin',
                    guardian_plural: 'Next of Kin',
                    collect_guardian_information: true,
                }}
                site={site}
                base_url="/sites/future-academy"
                intakes={[applicationIntake]}
                custom_fields={[
                    {
                        key: 'laptop_access',
                        label: 'Laptop access',
                        type: 'boolean',
                        options: [],
                        required: true,
                        sensitive: false,
                    },
                ]}
                locations={[
                    { id: 4, name: 'Lekki Campus' },
                    { id: 5, name: 'Yaba Campus' },
                ]}
                countries={['Ghana', 'Nigeria']}
            />,
        );

        fireEvent.change(screen.getByLabelText('Programme'), {
            target: { value: '3' },
        });
        fireEvent.change(screen.getByLabelText('Cycle'), {
            target: { value: '2' },
        });
        fireEvent.change(screen.getByLabelText('Cohort'), {
            target: { value: '7' },
        });
        fireEvent.change(screen.getByLabelText('Location'), {
            target: { value: '4' },
        });

        expect(
            document.querySelector('input[name="application_intake_id"]'),
        ).toHaveValue('9');
        expect(
            document.querySelector('input[name="preferred_branch_id"]'),
        ).toHaveValue('4');

        expect(screen.getByLabelText('Two payments')).toBeChecked();
        expect(screen.getByText('Recommended')).toBeInTheDocument();
        expect(screen.getByText('₦10,000/month')).toBeInTheDocument();
        expect(screen.queryByText('Programme fees')).not.toBeInTheDocument();
        expect(screen.getByText('View repayment schedule')).toBeInTheDocument();
        fireEvent.click(screen.getByLabelText('Pay once'));
        expect(screen.getByLabelText('Pay once')).toBeChecked();
        expect(screen.getByText('Pay once breakdown')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Preview application & fees/ }),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Pay once').closest('label'),
        ).not.toHaveTextContent('₦100,000.00');
        expect(screen.getByLabelText(/Laptop access/)).toBeRequired();
        expect(screen.getByLabelText('Country')).toHaveValue('Nigeria');
        expect(screen.getByText('Next of Kin information')).toBeInTheDocument();
        expect(
            screen.getByLabelText('Next of Kin first name'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/selected payment plan is saved/),
        ).toBeInTheDocument();
    });

    it('omits guardian fields when the academy disables guardian collection', () => {
        render(
            <PublicApplication
                academy={{
                    ...academy,
                    guardian_singular: 'Next of Kin',
                    guardian_plural: 'Next of Kin',
                    collect_guardian_information: false,
                }}
                site={site}
                base_url="/sites/future-academy"
                intakes={[applicationIntake]}
                custom_fields={[]}
                locations={[{ id: 4, name: 'Lekki Campus' }]}
                countries={['Nigeria']}
            />,
        );

        expect(
            screen.queryByText('Next of Kin information'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText('Next of Kin first name'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(/academy may process it for this application/),
        ).toBeInTheDocument();
    });

    it('shows fee combinations and processing costs only after an application is saved', () => {
        render(
            <PublicApplicationFees
                academy={academy}
                site={site}
                base_url="/sites/future-academy"
                application={{
                    reference: 'APP-ABC',
                    name: 'Amara Okafor',
                    email: 'amara@example.com',
                    token: 'secret-token',
                }}
                offering={{
                    programme_name: 'Software Engineering',
                    cycle_name: '2027 Cohort',
                    branch_name: 'All locations',
                    currency: 'NGN',
                    fee_items: [{ name: 'Tuition', amount_minor: 12000000 }],
                }}
                payment_options={[
                    {
                        key: 'application_only',
                        label: 'Pay application fee only',
                        currency: 'NGN',
                        application_amount: '5000.00',
                        programme_amount: '0.00',
                        base_amount: '5000.00',
                        processing_fee: '177.67',
                        total_charge: '5177.67',
                    },
                    {
                        key: 'installment:1',
                        label: 'Application fee + First installment',
                        currency: 'NGN',
                        application_amount: '5000.00',
                        programme_amount: '60000.00',
                        base_amount: '65000.00',
                        processing_fee: '1091.38',
                        total_charge: '66091.38',
                    },
                ]}
                paystack_ready
                completion_url="/sites/future-academy/applications/APP-ABC/complete?token=secret-token"
            />,
        );

        expect(screen.getByText('Application saved')).toBeInTheDocument();
        expect(
            screen.getByText('Pay application fee only'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Application fee + First installment'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Pay securely with Paystack' }),
        ).toBeEnabled();
        expect(
            screen.getAllByRole('link', {
                name: 'Skip payment and complete application',
            }),
        ).toHaveLength(2);
        expect(screen.getByText(/receipt will be sent to/i)).toHaveTextContent(
            'amara@example.com',
        );
    });

    it('confirms the application when payment is skipped or unsuccessful', () => {
        const { rerender } = render(
            <PublicApplicationComplete
                academy={academy}
                site={site}
                base_url="/sites/future-academy"
                application={{
                    reference: 'APP-ABC',
                    name: 'Amara Okafor',
                    email: 'amara@example.com',
                }}
                payment={{
                    status: 'skipped',
                    amount: null,
                    currency: null,
                    reference: null,
                }}
            />,
        );

        expect(
            screen.getByText('Application successfully submitted'),
        ).toBeInTheDocument();
        expect(screen.getByText('Payment skipped for now')).toBeInTheDocument();

        rerender(
            <PublicApplicationComplete
                academy={academy}
                site={site}
                base_url="/sites/future-academy"
                application={{
                    reference: 'APP-ABC',
                    name: 'Amara Okafor',
                    email: 'amara@example.com',
                }}
                payment={{
                    status: 'failed',
                    amount: '5177.67',
                    currency: 'NGN',
                    reference: 'BCM-FAILED',
                }}
            />,
        );

        expect(
            screen.getByText('Payment was unsuccessful'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/application was successfully submitted/i),
        ).toBeInTheDocument();
        expect(screen.getByText('₦5,177.67')).toBeInTheDocument();
    });

    it('renders rich programme outcomes, tools, FAQs and fee information together', () => {
        render(
            <PublicProgrammeDetails
                academy={academy}
                site={site}
                base_url="/sites/future-academy"
                programme={{
                    id: 3,
                    name: 'Product Design Bootcamp',
                    code: 'PDB',
                    description: 'Learn to solve real product problems.',
                    url: '/sites/future-academy/programmes/3',
                    duration: '4 Weeks',
                    delivery_format: 'Hybrid',
                    programme_type: 'Bootcamp',
                    public_timezone: 'Africa/Lagos',
                    level: 'Beginner',
                    outcomes_html: '<p>Build a strong portfolio.</p>',
                    curriculum_html:
                        '<ul><li>Research</li><li>Prototyping</li></ul>',
                    tools_platforms: [{ name: 'Figma', logo_url: null }],
                    faqs: [
                        {
                            question: 'Is a laptop required?',
                            answer_html: '<p>Yes.</p>',
                        },
                    ],
                    testimonials: [
                        {
                            quote_html: '<p>A practical programme.</p>',
                            name: 'Ada',
                            role: 'Graduate',
                        },
                    ],
                    image_url: '/sites/future-academy/programmes/3/image?v=1',
                    offerings: [
                        {
                            id: 9,
                            programme_id: 3,
                            cycle_id: 2,
                            cycle_name: 'January Cohort',
                            branch_id: null,
                            branch_name: 'All locations',
                            fee_name: 'Bootcamp tuition',
                            currency: 'NGN',
                            application_fee: '5000.00',
                            total: '120000.00',
                            fee_items: [
                                { name: 'Tuition', amount: '120000.00' },
                            ],
                            installments: [],
                            payment_plans: [],
                        },
                    ],
                }}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Product Design Bootcamp' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('img', {
                name: 'Product Design Bootcamp programme',
            }),
        ).toHaveAttribute(
            'src',
            '/sites/future-academy/programmes/3/image?v=1',
        );
        expect(screen.getByText('4 Weeks')).toBeInTheDocument();
        expect(
            screen.getByText('Build a strong portfolio.'),
        ).toBeInTheDocument();
        expect(screen.getByText('Figma')).toBeInTheDocument();
        expect(screen.getByText('Is a laptop required?')).toBeInTheDocument();
        expect(screen.getByText('Bootcamp tuition')).toBeInTheDocument();
        expect(screen.getAllByText('₦120,000.00')).toHaveLength(2);
    });
});
