<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves public HTML pages to cookie-less visitors (crawlers, link-preview bots, a first visit) from the
 * cache for five minutes (docs/31 item 0.9). The pages are rendered by Inertia SSR from a shared Postgres
 * that the ingestion pipeline keeps busy; under that load a page took 1.1-1.6 s while crawl rate and
 * previews depend on how fast it answers.
 *
 * Runs before the session starts, so a hit never sets a cookie. A request that carries any cookie (a
 * returning visitor, a logged-in user, a stored appearance) is never cached or served from the cache, which
 * keeps per-user data (ratings, flash messages, sidebar state, theme) out of it. A cached page is keyed by
 * the Vite manifest hash, so a deploy never serves HTML that points at assets the new image no longer has.
 */
class CachePublicPages
{
    public const TTL_SECONDS = 300;

    /** Set by a controller whose page must run on every view (sponsor view counts). */
    public const SKIP_ATTRIBUTE = 'page_cache.skip';

    private const ROUTES = [
        'home', 'about', 'methodology', 'sources', 'terms', 'privacy',
        'entities.show', 'categories.show', 'rankings.index', 'rankings.show',
        'topics.index', 'topics.show', 'comparisons.index', 'comparisons.show',
    ];

    private const KEPT_HEADERS = ['Content-Type', 'X-Robots-Tag', 'Link', 'Cache-Control'];

    private const MAX_BYTES = 524288;

    private const EPOCH_KEY = 'page-cache:epoch';

    /**
     * Drops every cached page at once. Called when content that decides what the public sees is edited
     * (an entity, a topic, a category), so an unpublish or a rename is not served for another five minutes.
     * Score changes from the pipeline do not call it; those can wait out the TTL.
     */
    public static function flush(): void
    {
        try {
            Cache::add(self::EPOCH_KEY, 1);
            Cache::increment(self::EPOCH_KEY);
        } catch (\Throwable) {
            // Nothing cached means nothing stale.
        }
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cacheable($request)) {
            return $next($request);
        }

        try {
            $epoch = (int) Cache::get(self::EPOCH_KEY, 0);
            $key = 'page-cache:'.sha1(Vite::manifestHash().'|'.$epoch.'|'.$request->getHost().$request->getPathInfo());
            $hit = Cache::get($key);
        } catch (\Throwable) {
            return $next($request);
        }

        if (is_array($hit)) {
            return response($hit['content'], 200, $hit['headers'])->header('X-Page-Cache', 'HIT');
        }

        $response = $next($request);

        if ($this->storable($request, $response)) {
            $headers = [];
            foreach (self::KEPT_HEADERS as $name) {
                if ($response->headers->has($name)) {
                    $headers[$name] = (string) $response->headers->get($name);
                }
            }

            try {
                Cache::put($key, ['content' => (string) $response->getContent(), 'headers' => $headers], self::TTL_SECONDS);
            } catch (\Throwable) {
                // A cache outage must not turn into a failed page.
            }
        }

        return $response->header('X-Page-Cache', 'MISS');
    }

    private function cacheable(Request $request): bool
    {
        return $request->isMethod('GET')
            && ! $request->headers->has('X-Inertia')
            && $request->cookies->count() === 0
            && $request->query->count() === 0
            && $request->routeIs(...self::ROUTES);
    }

    private function storable(Request $request, Response $response): bool
    {
        return $response->getStatusCode() === 200
            && ! $request->attributes->get(self::SKIP_ATTRIBUTE, false)
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && strlen((string) $response->getContent()) <= self::MAX_BYTES;
    }
}
