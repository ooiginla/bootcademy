import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

const properties = [
    '--primary',
    '--primary-foreground',
    '--secondary',
    '--secondary-foreground',
    '--ring',
    '--sidebar',
    '--sidebar-foreground',
    '--sidebar-primary',
    '--sidebar-primary-foreground',
] as const;

const academyFaviconId = 'active-academy-favicon';

export function AcademyThemeSync() {
    const { academy } = usePage().props;

    useEffect(() => {
        const root = document.documentElement;

        if (!academy) {
            properties.forEach((property) =>
                root.style.removeProperty(property),
            );
            return;
        }

        const { theme } = academy;
        root.style.setProperty('--primary', theme.primary);
        root.style.setProperty(
            '--primary-foreground',
            theme.primary_foreground,
        );
        root.style.setProperty('--secondary', theme.secondary);
        root.style.setProperty(
            '--secondary-foreground',
            theme.secondary_foreground,
        );
        root.style.setProperty('--ring', theme.primary);
        root.style.setProperty('--sidebar', theme.primary);
        root.style.setProperty(
            '--sidebar-foreground',
            theme.primary_foreground,
        );
        root.style.setProperty('--sidebar-primary', theme.secondary);
        root.style.setProperty(
            '--sidebar-primary-foreground',
            theme.secondary_foreground,
        );

        return () => {
            properties.forEach((property) =>
                root.style.removeProperty(property),
            );
        };
    }, [academy]);

    useEffect(() => {
        document.getElementById(academyFaviconId)?.remove();

        if (!academy?.logo_url) return;

        const favicon = document.createElement('link');
        favicon.id = academyFaviconId;
        favicon.rel = 'icon';
        favicon.href = academy.logo_url;
        favicon.sizes = 'any';
        document.head.append(favicon);

        return () => favicon.remove();
    }, [academy?.logo_url]);

    return null;
}
