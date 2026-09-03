<?php

namespace App\Providers;

use App\Domains\Search\Services\TrigramSimilarity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

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
}
