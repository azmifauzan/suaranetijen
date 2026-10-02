<?php

namespace App\Http\Ssr;

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

                return null;
            }

            if (! $data = $response->json()) {
                return null;
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

            return null;
        }
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
