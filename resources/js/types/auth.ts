export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    is_platform_super_admin: boolean;
    [key: string]: unknown;
};

export type AcademyTheme = {
    primary: string;
    primary_foreground: string;
    secondary: string;
    secondary_foreground: string;
};

export type ActiveAcademy = {
    id: number;
    name: string;
    academy_type: string;
    terminology: {
        singular: string;
        plural: string;
        staff_singular: string;
        staff_plural: string;
    };
    currency: string;
    timezone: string;
    logo_url: string | null;
    role: string;
    role_label: string;
    can_manage: boolean;
    can_manage_finance: boolean;
    can_view_audit_log: boolean;
    active_member_count: number;
    theme: AcademyTheme;
};

export type AcademyOption = {
    id: number;
    name: string;
    role_label: string;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
