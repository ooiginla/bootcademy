import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, academy } = usePage().props;
    const displayName = academy?.name ?? name;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {academy?.logo_url ? (
                    <img
                        src={academy.logo_url}
                        alt={`${academy.name} logo`}
                        className="size-full rounded-md object-cover"
                    />
                ) : (
                    <AppLogoIcon className="size-5" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {displayName}
                </span>
                <span className="truncate text-[11px] leading-none text-sidebar-foreground/60">
                    {academy?.role_label ?? 'Management platform'}
                </span>
            </div>
        </>
    );
}
