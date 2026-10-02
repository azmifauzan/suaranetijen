<?php

use App\Http\Ssr\TimeoutHttpGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\SsrRenderFailed;

beforeEach(function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.ensure_bundle_exists' => false,
        'inertia.ssr.url' => 'http://ssr.test:13714',
        'inertia.ssr.timeout' => 2,
        'inertia.ssr.connect_timeout' => 1,
    ]);
});

test('the container resolves the bounded gateway', function () {
    expect(app(Gateway::class))->toBeInstanceOf(TimeoutHttpGateway::class)
        ->and(app(HttpGateway::class))->toBeInstanceOf(TimeoutHttpGateway::class);
});

test('render requests carry the configured timeouts', function () {
    $seen = [];
    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen = $options;

        return Http::response(['head' => ['<title>x</title>'], 'body' => '<div>ok</div>']);
    });

    $response = app(Gateway::class)->dispatch(['component' => 'Welcome', 'props' => [], 'url' => '/', 'version' => 'x']);

    expect($response)->not->toBeNull()
        ->and($seen['timeout'])->toBe(2.0)
        ->and($seen['connect_timeout'])->toBe(1.0);
});

test('a timed out render falls back to client rendering and reports the failure', function () {
    Event::fake([SsrRenderFailed::class]);
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $response = app(Gateway::class)->dispatch(['component' => 'Welcome', 'props' => [], 'url' => '/', 'version' => 'x']);

    expect($response)->toBeNull();
    Event::assertDispatched(SsrRenderFailed::class);
});

test('the health check is bounded and reports an unreachable server as unhealthy', function () {
    $seen = [];
    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen = $options;

        throw new ConnectionException('timed out');
    });

    expect(app(HttpGateway::class)->isHealthy())->toBeFalse()
        ->and($seen['timeout'])->toBe(2.0);
});
