<?php

namespace App\Http\Ssr;

use App\Http\Middleware\CachePublicPages;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;
use Inertia\Ssr\SsrException;

/**
 * HttpGateway with a bounded SSR request. inertia-laravel 3.3 sends the render request with
 * Laravel's default 30 s timeout, so a hung Node process would stall every page for 30 s
 * instead of falling back to client-side rendering. Remove this once the package honours
 * `inertia.ssr.timeout`.
 */
class TimeoutHttpGateway extends HttpGateway
{
    /**
     * @param  array<string, mixed>  $page
     */
    public function dispatch(array $page, ?Request $request = null): ?Response
    {
        if (! $this->ssrIsEnabled($request ?? request())) {
            return null;
        }

        $isHot = Vite::isRunningHot();

        if (! $isHot && $this->shouldEnsureBundleExists() && ! $this->bundleExists()) {
            return null;
        }

        $url = $isHot
            ? $this->getHotUrl('/__inertia_ssr')
            : $this->getProductionUrl('/render');

        try {
            $response = $this->bounded()->post($url, $page);

            if ($response->failed()) {
                $this->handleSsrFailure($page, $response->json());

                return $this->fallback($request);
            }

            if (! $data = $response->json()) {
                return $this->fallback($request);
            }

            return new Response(
                implode("\n", $data['head'] ?? []),
                $data['body'] ?? ''
            );
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException || $e instanceof SsrException) {
                throw $e;
            }

            $this->handleSsrFailure($page, [
                'error' => $e->getMessage(),
                'type' => 'connection',
            ]);

            return $this->fallback($request);
        }
    }

    /**
     * Client-side fallback for a render that was attempted and failed. The page cache must not store it:
     * a crawler would get an empty shell for the whole TTL (docs/31, 26 Oct 2026).
     */
    protected function fallback(?Request $request): null
    {
        ($request ?? request())->attributes->set(CachePublicPages::SKIP_ATTRIBUTE, true);

        return null;
    }

    public function isHealthy(): bool
    {
        try {
            return $this->bounded()->get($this->getProductionUrl('/health'))->successful();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return false;
        }
    }

    protected function bounded(): PendingRequest
    {
        return Http::connectTimeout((float) config('inertia.ssr.connect_timeout', 1))
            ->timeout((float) config('inertia.ssr.timeout', 3));
    }
}
