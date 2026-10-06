<?php

use App\Http\Middleware\ApplyRobotsPolicy;
use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Domains/Entities/Commands',
        __DIR__.'/../app/Domains/Sources/Commands',
        __DIR__.'/../app/Domains/Admin/Commands',
        __DIR__.'/../app/Domains/Themes/Commands',
        __DIR__.'/../app/Domains/Search/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust only RFC1918 private ranges: the app always sits behind an
        // Nginx reverse proxy on a private Docker network, never exposed
        // directly to the public internet.
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'api/sponsor/webhooks/sumopod-relay',
            'api/sponsor/click/*',
        ]);

        $middleware->web(prepend: [
            CachePublicPages::class,
        ], append: [
            ApplyRobotsPolicy::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
