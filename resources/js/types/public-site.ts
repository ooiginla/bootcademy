export type PublicTheme = {
    primary: string;
    primary_foreground: string;
    secondary: string;
    secondary_foreground: string;
};

export type PublicAcademy = {
    name: string;
    type: string;
    logo_url: string | null;
    member_singular: string;
    member_plural: string;
    currency: string;
    theme: PublicTheme;
};

export type PublicSection = Record<string, string | boolean> & {
    enabled: boolean;
    title: string;
};

export type PublicSite = {
    subdomain: string;
    theme_key: string;
    sections: Record<string, PublicSection>;
};

export type PublicProgramme = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    duration?: string | null;
    delivery_format?: string | null;
    programme_type?: string | null;
    public_timezone?: string | null;
    level?: string | null;
    outcomes_html?: string | null;
    skills_gained_html?: string | null;
    curriculum_html?: string | null;
    eligibility_html?: string | null;
    audience_html?: string | null;
    demand_html?: string | null;
    application_process_html?: string | null;
    tools_platforms?: Array<{ name: string; logo_url: string | null }>;
    faqs?: Array<{ question: string; answer_html: string }>;
    testimonials?: Array<{
        quote_html: string;
        name: string;
        role: string | null;
    }>;
    image_url?: string | null;
    url: string;
};

export type PublicOffering = {
    id: number;
    cycle_id: number;
    cycle_name: string;
    programme_id: number;
    programme_name?: string;
    branch_id: number | null;
    branch_name: string;
    fee_name: string;
    currency: string;
    application_fee: string;
    total: string;
    fee_items: Array<{ name: string; amount: string }>;
    installments: Array<{
        name: string;
        amount: string;
        due_on: string | null;
        due_offset_days: number | null;
    }>;
};
