export function parseRows(
    value: string | boolean | undefined,
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
