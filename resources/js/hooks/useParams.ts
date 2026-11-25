import { usePage } from '@inertiajs/react';

const PATTERNS: Array<[RegExp, string]> = [
    [/^\/(?:customer|restaurant)\/orders\/([^/]+)/, 'order'],
    [/^\/restaurant\/menu\/([^/]+)\/edit/, 'menuItem'],
    [/^\/rider\/(?:deliveries|tracking)\/([^/]+)/, 'delivery'],
    [/^\/admin\/riders\/([^/]+)/, 'rider'],
];

/**
 * Inertia pages are not rendered inside a react-router <Route>, so react-router's
 * useParams() is always empty. Derive the params from the current page URL instead.
 */
export function useParams<T extends Record<string, string | undefined>>(): Partial<T> {
    const { url } = usePage();
    const path = url.split('?')[0];

    for (const [pattern, name] of PATTERNS) {
        const match = path.match(pattern);
        if (match) {
            return { [name]: decodeURIComponent(match[1]) } as Partial<T>;
        }
    }

    return {};
}
