<?php

namespace App\Providers;

use App\Domains\Search\Services\TrigramSimilarity;
use App\Http\Ssr\TimeoutHttpGateway;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\SsrRenderFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HttpGateway::class, TimeoutHttpGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureInertiaErrors();

        RateLimiter::for('themes-llm', fn () => Limit::perMinute(max(1, (int) config('themes.llm_per_minute', 60))));

        RateLimiter::for('ratings', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(10)
                ->by($userId !== null ? 'rating:user:'.$userId : 'rating:ip:'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    Log::warning('rating.rate_limited', [
                        'user_id' => $request->user()?->getAuthIdentifier(),
                        'entity_id' => $request->route('entity'),
                        'ip' => $request->ip(),
                    ]);

                    return response()->json([
                        'message' => 'Terlalu banyak percobaan rating. Coba lagi nanti.',
                    ], 429, $headers);
                });
        });

        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'sqlite') {
                TrigramSimilarity::registerSqliteFunctions($event->connection->getPdo());
            }
        });

        if (DB::getDriverName() === 'sqlite') {
            TrigramSimilarity::registerSqliteFunctions(DB::connection()->getPdo());
        }

        Event::listen(SsrRenderFailed::class, function (SsrRenderFailed $event): void {
            Log::warning('[InertiaSsr] Render failed, falling back to client-side rendering', $event->toArray());

            try {
                Cache::put('ssr:last_failed_at', now()->toIso8601String(), 86400);
                Cache::increment('ssr:failures_count_24h');
            } catch (\Throwable) {
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Render branded error pages for Inertia requests while keeping API errors JSON.
     */
    protected function configureInertiaErrors(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            if (! $response->request->header('X-Inertia') || $response->request->is('api/*')) {
                return null;
            }

            if (! in_array($response->statusCode(), [401, 403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            return $response
                ->render('ErrorPage', ['status' => $response->statusCode()])
                ->withSharedData();
        });
    }
}
