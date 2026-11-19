import type { CSSProperties } from 'react';
import type { PublicSection } from '@/types/public-site';

export function parseRows(
    value: string | boolean | null | undefined,
    columns: number,
) {
    if (typeof value !== 'string') return [];

    return value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const parts = line.split('|').map((part) => part.trim());
            return Array.from(
                { length: columns },
                (_, index) => parts[index] ?? '',
            );
        });
}

export function safeUrl(value: string, fallback = '#') {
    const url = value.trim();
    if (
        url.startsWith('/') ||
        url.startsWith('#') ||
        /^https?:\/\//i.test(url) ||
        /^mailto:/i.test(url) ||
        /^tel:/i.test(url)
    ) {
        return url;
    }

    return fallback;
}

export function resolveSiteUrl(baseUrl: string, value: string) {
    const url = safeUrl(value);
    if (url.startsWith('/') && !url.startsWith('/sites/')) {
        return `${baseUrl}${url}` || '/';
    }

    return url;
}

export function initials(value: string) {
    return value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}
function optionalHex(
    value: string | boolean | null | undefined,
): string | undefined {
    return typeof value === 'string' && /^#[0-9A-F]{6}$/i.test(value)
        ? value
        : undefined;
}

export function sectionAppearance(
    section: PublicSection | undefined,
    className = '',
    chrome = false,
): { className: string; style: CSSProperties } {
    const backgroundColor = optionalHex(section?.background_color);
    const color = optionalHex(section?.text_color);

    return {
        className: [
            className,
            color ? 'public-section-custom-text' : '',
            color && chrome ? 'public-chrome-custom-text' : '',
        ]
            .filter(Boolean)
            .join(' '),
        style: {
            ...(backgroundColor ? { backgroundColor } : {}),
            ...(color
                ? {
                      color,
                      '--public-section-text': color,
                  }
                : {}),
        } as CSSProperties,
    };
}
