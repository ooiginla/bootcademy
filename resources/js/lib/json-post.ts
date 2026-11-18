function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

/** POSTs JSON with the session CSRF token and returns the response and parsed body. */
export async function jsonPost<T = Record<string, unknown>>(
    url: string,
    body: Record<string, unknown> = {},
): Promise<{ response: Response; payload: T }> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    const payload = (await response.json().catch(() => ({}))) as T;

    return { response, payload };
}
