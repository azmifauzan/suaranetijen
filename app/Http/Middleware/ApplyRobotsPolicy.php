<?php

namespace App\Http\Middleware;

use App\Domains\Search\Services\RobotsPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ApplyRobotsPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (SymfonyResponse)  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        RobotsPolicy::reset();

        // 1. Detect static/predefined noindex routes
        if ($this->shouldNoindexRoute($request)) {
            RobotsPolicy::noindex();
        }

        view()->share('robots', RobotsPolicy::get());

        /** @var SymfonyResponse $response */
        $response = $next($request);

        // 2. Apply header & sync raw HTML if noindex was triggered
        if (RobotsPolicy::isNoindex()) {
            $policy = RobotsPolicy::get();
            $response->headers->set('X-Robots-Tag', $policy);

            if ($response instanceof Response && str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
                $content = $response->getContent();
                if ($content !== false && str_contains($content, 'name="robots" content="index, follow"')) {
                    $original = $response->original;
                    $response->setContent(str_replace('name="robots" content="index, follow"', 'name="robots" content="'.$policy.'"', $content));
                    $response->original = $original;
                }
            }
        }

        return $response;
    }

    /**
     * Determine if the request route matches standing noindex criteria (docs/31 Fase 0).
     */
    protected function shouldNoindexRoute(Request $request): bool
    {
        // Auth & verification pages
        if ($request->is([
            'login',
            'register',
            'forgot-password',
            'reset-password*',
            'two-factor-challenge',
            'confirm-password',
            'email/verify*',
            'auth/*',
            'go/*',
        ])) {
            return true;
        }

        // Administrative & user settings
        if ($request->is(['dashboard*', 'settings*', 'admin*'])) {
            return true;
        }

        // Sponsor checkout & payment status
        if ($request->is('sponsor/payment-status')) {
            return true;
        }

        // Filtered search results
        if ($request->is('search') && ($request->filled('q') || $request->filled('category'))) {
            return true;
        }

        return false;
    }
}
