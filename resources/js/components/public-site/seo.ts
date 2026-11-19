export const publicSiteTitlePrefix = '__public_academy__:';

export function formatDocumentTitle(title: string, appName: string): string {
    if (title.startsWith(publicSiteTitlePrefix)) {
        return title.slice(publicSiteTitlePrefix.length);
    }

    return title ? `${title} - ${appName}` : appName;
}
