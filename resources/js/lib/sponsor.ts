/**
 * Helper utilities for sponsor outbound links, live backlinks, and click tracking (RankUp style).
 */

export interface SponsorUtmOptions {
    placement?: string;
    source?: string;
    medium?: string;
    campaign?: string;
    term?: string;
    content?: string;
    id?: string;
}

export function getDirectWebsiteUrl(
    url?: string | null,
    slug?: string,
    options?: SponsorUtmOptions
): string {
    if (!url || !url.trim()) {
        return slug ? `/go/${slug}` : '#';
    }

    const trimmed = url.trim();
    const fullUrl = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;

    try {
        const parsed = new URL(fullUrl);

        // Append GA (Google Analytics) campaign and platform referral parameters
        if (!parsed.searchParams.has('ref')) {
            parsed.searchParams.set('ref', 'suaranetijen.id');
        }
        if (!parsed.searchParams.has('utm_source')) {
            parsed.searchParams.set('utm_source', options?.source || 'suaranetijen.id');
        }
        if (!parsed.searchParams.has('utm_medium')) {
            parsed.searchParams.set('utm_medium', options?.medium || 'sponsor');
        }
        if (!parsed.searchParams.has('utm_campaign')) {
            parsed.searchParams.set('utm_campaign', options?.campaign || 'sponsor_leaderboard');
        }
        if (!parsed.searchParams.has('utm_term') && (options?.term || slug)) {
            parsed.searchParams.set('utm_term', (options?.term || slug)!);
        }
        if (!parsed.searchParams.has('utm_content')) {
            parsed.searchParams.set(
                'utm_content',
                options?.content || options?.placement || (slug ? `entry_${slug}` : 'leaderboard')
            );
        }
        if (options?.id && !parsed.searchParams.has('utm_id')) {
            parsed.searchParams.set('utm_id', options.id);
        }

        return parsed.toString();
    } catch {
        return fullUrl;
    }
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

export function trackSponsorClick(
    slug?: string | null,
    meta?: { placement?: string; name?: string; url?: string }
): void {
    if (!slug || typeof window === 'undefined') return;

    // 1. Internal tracking via beacon/fetch to update sponsor clicks_count
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

    // 2. Google Analytics (GA4) outbound click event if gtag is active
    try {
        const win = window as unknown as { gtag?: (...args: unknown[]) => void };
        if (typeof win.gtag === 'function') {
            win.gtag('event', 'sponsor_click', {
                event_category: 'outbound_sponsor',
                event_label: slug,
                entity_slug: slug,
                placement: meta?.placement || 'unknown',
                outbound_url: meta?.url,
            });
        }
    } catch {
        // Silently continue
    }
}
