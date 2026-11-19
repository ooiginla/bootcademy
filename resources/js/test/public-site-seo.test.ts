import { describe, expect, it } from 'vitest';
import {
    formatDocumentTitle,
    publicSiteTitlePrefix,
} from '@/components/public-site/seo';

describe('document titles', () => {
    it('shows only the academy title on its public website', () => {
        expect(
            formatDocumentTitle(
                `${publicSiteTitlePrefix}Apply · Lekki Stars`,
                'Bootcademy',
            ),
        ).toBe('Apply · Lekki Stars');
    });

    it('keeps the platform name on the Bootcademy app', () => {
        expect(formatDocumentTitle('Dashboard', 'Bootcademy')).toBe(
            'Dashboard - Bootcademy',
        );
        expect(formatDocumentTitle('', 'Bootcademy')).toBe('Bootcademy');
    });
});
