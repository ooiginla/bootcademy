import type { AcademyOption, ActiveAcademy, Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            academy: ActiveAcademy | null;
            academies: AcademyOption[];
            platform: {
                is_super_admin: boolean;
            };
            pending_invitations_count: number;
            support_access: {
                academy_name: string;
            } | null;
            sidebarOpen: boolean;
            recaptcha_site_key: string | null;
            [key: string]: unknown;
        };
    }
}
