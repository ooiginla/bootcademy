import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

const academyFaviconId = 'active-academy-favicon';

export function AcademyThemeSync() {
    const { academy } = usePage().props;

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
