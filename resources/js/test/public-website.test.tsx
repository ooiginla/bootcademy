import { fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AcademyWebsite from '@/pages/academies/website';
import PublicSiteShell from '@/components/public-site/public-site-shell';
import PublicApplication from '@/pages/public/application';
import PublicHome from '@/pages/public/home';
import PublicProgrammeDetails from '@/pages/public/programme';
import type { PublicAcademy, PublicSite } from '@/types/public-site';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({ children }: { children: (value: object) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
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

    it('previews programme, cycle, installments and custom application fields', () => {
        render(
            <PublicApplication
                academy={academy}
                site={site}
                base_url="/sites/future-academy"
                offerings={[
                    {
                        id: 9,
                        programme_id: 3,
                        programme_name: 'Software Engineering',
                        cycle_id: 2,
                        cycle_name: '2027 Cohort',
                        branch_id: null,
                        branch_name: 'All locations',
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
                    },
                ]}
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
                countries={['Ghana', 'Nigeria']}
            />,
        );

        fireEvent.change(screen.getByLabelText('Programme'), {
            target: { value: '3' },
        });
        fireEvent.change(screen.getByLabelText('Cycle and location'), {
            target: { value: '9' },
        });

        expect(screen.getByText('Pay in full')).toBeInTheDocument();
        expect(screen.getByText('Pay in installments')).toBeInTheDocument();
        expect(screen.getAllByText('₦120,000.00')).toHaveLength(2);
        expect(screen.getByLabelText(/Laptop access/)).toBeRequired();
        expect(screen.getByLabelText('Country')).toHaveValue('Nigeria');
        expect(
            screen.getByText(/No form data is transmitted/),
        ).toBeInTheDocument();
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
