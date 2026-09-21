/**
 * Helper utilities for sponsor outbound links, live backlinks, and click tracking (RankUp style).
 */

export function getDirectWebsiteUrl(url?: string | null, slug?: string): string {
    if (!url || !url.trim()) {
        return slug ? `/go/${slug}` : '#';
    }

    const trimmed = url.trim();
    return /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
}

export function getFaviconUrl(url?: string | null): string | null {
    if (!url || !url.trim()) return null;
    try {
        const fullUrl = /^https?:\/\//i.test(url.trim()) ? url.trim() : `https://${url.trim()}`;
        const domain = new URL(fullUrl).hostname;
        return `https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain)}&sz=64`;
    } catch {
        return null;
    }
}

export function trackSponsorClick(slug?: string | null): void {
    if (!slug || typeof window === 'undefined') return;

    const endpoint = `/api/sponsor/click/${encodeURIComponent(slug)}`;
    try {
        if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
            navigator.sendBeacon(endpoint);
        } else {
            fetch(endpoint, { method: 'POST', keepalive: true }).catch(() => {});
        }
    } catch {
        // Silently continue without interrupting user navigation
    }
}
