type Gtag = (command: 'event', name: string, params?: Record<string, unknown>) => void;

/**
 * Sends a GA4 event when gtag is loaded (GOOGLE_ANALYTICS_ID set); a no-op otherwise.
 */
export function trackEvent(name: string, params: Record<string, unknown> = {}): void {
    try {
        const gtag = (window as unknown as { gtag?: Gtag }).gtag;

        if (typeof gtag === 'function') {
            gtag('event', name, params);
        }
    } catch {
        // Analytics must never interrupt the page.
    }
}
