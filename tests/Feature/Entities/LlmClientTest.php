<?php

use App\Domains\Entities\Exceptions\LlmResponseUnusable;
use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Entities\Services\LlmClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::flush());

it('sends a chat completion using the saved llm_settings row', function () {
    LlmSetting::create([
        'base_url' => 'https://llm.internal/v1',
        'model' => 'test-model',
        'api_key' => 'secret-key',
        'max_tokens' => 500,
        'temperature' => 0.5,
        'timeout_seconds' => 20,
    ]);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        expect($request->url())->toBe('https://llm.internal/v1/chat/completions')
            ->and($request->hasHeader('Authorization', 'Bearer secret-key'))->toBeTrue()
            ->and($request['model'])->toBe('test-model')
            ->and($request['max_tokens'])->toBe(500)
            ->and($request['temperature'])->toBe(0.5);

        return Http::response([
            'choices' => [
                ['message' => ['content' => '{"answer":"ok"}']],
            ],
        ]);
    });

    $result = (new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]);

    expect($result)->toBe(['answer' => 'ok']);
});

it('falls back to config defaults when no llm_settings row exists', function () {
    config([
        'services.llm.base_url' => 'https://default.example/v1',
        'services.llm.model' => 'default-model',
        'services.llm.api_key' => 'default-key',
    ]);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        expect($request->url())->toBe('https://default.example/v1/chat/completions')
            ->and($request['model'])->toBe('default-model');

        return Http::response(['choices' => [['message' => ['content' => '{}']]]]);
    });

    (new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]);
});

it('requests a JSON schema response format when a schema is given', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'test-model', 'api_key' => 'key']);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        expect($request['response_format']['type'])->toBe('json_schema')
            ->and($request['response_format']['json_schema']['name'])->toBe('entity_candidate_suggestion');

        return Http::response(['choices' => [['message' => ['content' => '{"suggested_name":"Foo"}']]]]);
    });

    $result = (new LlmClient)->chat(
        [['role' => 'user', 'content' => 'hi']],
        ['name' => 'entity_candidate_suggestion', 'schema' => ['type' => 'object']]
    );

    expect($result)->toBe(['suggested_name' => 'Foo']);
});

function llmSettingWithFallback(): void
{
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'main-model', 'fallback_model' => 'backup-model', 'api_key' => 'key']);
}

function llmOk(string $content = '{"ok":true}'): PromiseInterface
{
    return Http::response(['choices' => [['message' => ['content' => $content]]]]);
}

it('retries with the fallback model when the main model times out', function () {
    llmSettingWithFallback();
    Http::preventStrayRequests();
    Http::fake(fn (Request $request) => $request['model'] === 'main-model'
        ? throw new ConnectionException('cURL error 28: Operation timed out')
        : llmOk());

    expect((new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]))->toBe(['ok' => true]);
    Http::assertSent(fn (Request $request) => ($request['model'] ?? null) === 'backup-model');
});

it('retries with the fallback model on a 5xx from the main model', function () {
    llmSettingWithFallback();
    Http::preventStrayRequests();
    Http::fake(fn (Request $request) => $request['model'] === 'main-model' ? Http::response('bad gateway', 502) : llmOk());

    expect((new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]))->toBe(['ok' => true]);
});

it('does not fall back on a client error such as a bad key', function () {
    llmSettingWithFallback();
    Http::preventStrayRequests();
    Http::fake(['llm.internal/*' => Http::response('unauthorized', 401)]);

    expect(fn () => (new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]))->toThrow(RequestException::class);
    Http::assertSentCount(1);
});

it('skips the main model straight to the fallback while it is marked down', function () {
    llmSettingWithFallback();
    $mainAttempts = 0;
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$mainAttempts) {
        if ($request['model'] === 'main-model') {
            $mainAttempts++;

            throw new ConnectionException('cURL error 28: Operation timed out');
        }

        return llmOk();
    });

    $client = new LlmClient;
    $client->chat([['role' => 'user', 'content' => 'hi']]);
    $client->chat([['role' => 'user', 'content' => 'hi again']]);

    expect($mainAttempts)->toBe(1);
});

it('still throws when no fallback model is configured', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'main-model', 'api_key' => 'key']);
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(fn () => (new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]))->toThrow(ConnectionException::class);
});

function failingLlm(int $status, string $body): void
{
    app()->detectEnvironment(fn () => 'production');
    config(['sponsorship.telegram.bot_token' => 'bot', 'sponsorship.telegram.chat_id' => '42']);
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'm', 'api_key' => 'k', 'max_tokens' => 100, 'temperature' => 0.1, 'timeout_seconds' => 5]);
    Http::preventStrayRequests();
    Http::fake([
        'llm.internal/*' => Http::response($body, $status),
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);
}

it('alerts telegram once when the llm balance is exhausted', function () {
    failingLlm(402, '{"error":"insufficient balance"}');

    foreach ([1, 2] as $_) {
        expect(fn () => app(LlmClient::class)->chat([['role' => 'user', 'content' => 'x']]))->toThrow(RequestException::class);
    }

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.telegram.org') && str_contains($r['text'], 'saldo/kuota habis'));
});

it('stays quiet on a routine 429 rate limit', function () {
    failingLlm(429, '{"error":"rate limit, slow down"}');

    expect(fn () => app(LlmClient::class)->chat([['role' => 'user', 'content' => 'x']]))->toThrow(RequestException::class);

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'api.telegram.org'));
});

function useCodexRunner(): void
{
    config([
        'services.llm.provider' => 'codex_subscription',
        'services.llm.codex_runner_url' => 'http://codex.local:8080',
        'services.llm.codex_runner_token' => 'private-runner-key',
    ]);
}

it('uses the node local codex runner instead of shared API settings', function () {
    useCodexRunner();
    llmSettingWithFallback();
    Http::preventStrayRequests();
    Http::fake(['codex.local:8080/chat' => Http::response(['themes' => []])]);
    $schema = ['name' => 'themes', 'schema' => ['type' => 'object']];
    $messages = [['role' => 'user', 'content' => 'synthetic opinion']];

    expect(app(LlmClient::class)->chat($messages, $schema))->toBe(['themes' => []]);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://codex.local:8080/chat'
        && $request->hasHeader('Authorization', 'Bearer private-runner-key')
        && $request['messages'] === $messages && $request['json_schema'] === $schema
        && ! isset($request['api_key']) && ! isset($request['model']));
    Http::assertSentCount(1);
    expect(LlmSetting::first()->api_key)->toBe('key');
});

it('does not fall back to the paid API after any codex runner failure', function (int $status) {
    useCodexRunner();
    llmSettingWithFallback();
    Http::preventStrayRequests();
    Http::fake(['codex.local:8080/chat' => Http::response(['error' => 'runner_failed'], $status, ['Retry-After' => '300'])]);

    try {
        app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]);
        $this->fail('Expected a runner failure');
    } catch (RequestException $exception) {
        expect($exception->response->status())->toBe($status);
        if ($status === 429) {
            expect($exception->response->header('Retry-After'))->toBe('300');
        }
    }
    Http::assertSentCount(1);
})->with([401, 429, 502, 504]);

it('does not fall back after a codex connection failure', function () {
    useCodexRunner();
    llmSettingWithFallback();
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('runner unavailable'));

    expect(fn () => app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]))
        ->toThrow(ConnectionException::class);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'llm.internal'));
});

it('requires a schema before contacting codex', function () {
    useCodexRunner();
    Http::fake();

    expect(fn () => app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']]))
        ->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

it('rejects malformed or non object codex output', function (string $body) {
    useCodexRunner();
    Http::preventStrayRequests();
    Http::fake(['codex.local:8080/chat' => Http::response($body)]);

    expect(fn () => app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]))
        ->toThrow(UnexpectedValueException::class);
})->with(['broken JSON', 'null', '[]', '[{"theme":"x"}]']);

function useCodexApiFallback(): void
{
    useCodexRunner();
    config(['services.llm.codex_api_fallback' => true]);
    llmSettingWithFallback();
}

it('keeps codex primary when API fallback is enabled', function () {
    useCodexApiFallback();
    Http::preventStrayRequests();
    Http::fake(['codex.local:8080/chat' => Http::response(['ok' => true])]);

    expect(app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]))->toBe(['ok' => true]);

    Http::assertSent(fn (Request $request) => $request['timeout_seconds'] === 45);
    Http::assertSentCount(1);
    expect(Cache::get('llm:codex-api-fallback:'.gethostname(), 0))->toBe(0);
});

it('uses saved API settings on codex quota auth or runtime failures when enabled', function (int $status, string $code) {
    useCodexApiFallback();
    Http::preventStrayRequests();
    Http::fake([
        'codex.local:8080/chat' => Http::response(['error' => $code], $status),
        'llm.internal/*' => llmOk(),
    ]);

    expect(app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]))->toBe(['ok' => true]);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://llm.internal/v1/chat/completions'
        && $request['model'] === 'main-model' && $request->hasHeader('Authorization', 'Bearer key'));
    Http::assertSentCount(2);
    expect(Cache::get('llm:codex-api-fallback:'.gethostname()))->toBe(1);
})->with([[401, 'codex_login_required'], [403, 'unauthorized'], [429, 'codex_quota_limited'], [502, 'codex_inference_failed'], [503, 'runner_unavailable'], [504, 'inference_timeout']]);

it('does not spend API credits on runner contention or invalid input', function (int $status, string $code) {
    useCodexApiFallback();
    Http::preventStrayRequests();
    Http::fake(['codex.local:8080/chat' => Http::response(['error' => $code], $status, ['Retry-After' => '15'])]);

    expect(fn () => app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]))->toThrow(RequestException::class);
    Http::assertSentCount(1);
    expect(Cache::get('llm:codex-api-fallback:'.gethostname(), 0))->toBe(0);
})->with([[429, 'runner_busy'], [429, 'runner_rate_limited'], [422, 'invalid_request']]);

it('uses API fallback for a lost codex connection or invalid JSON', function (bool $connectionFailure) {
    useCodexApiFallback();
    Http::preventStrayRequests();
    Http::fake([
        'codex.local:8080/chat' => $connectionFailure ? Http::failedConnection() : Http::response('broken JSON'),
        'llm.internal/*' => llmOk(),
    ]);

    expect(app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]))->toBe(['ok' => true]);
    Http::assertSent(fn (Request $request) => ($request['model'] ?? null) === 'main-model');
    expect(Cache::get('llm:codex-api-fallback:'.gethostname()))->toBe(1);
})->with([true, false]);

it('preserves the API model fallback after codex fails', function () {
    useCodexApiFallback();
    Http::preventStrayRequests();
    $timeouts = [];
    Http::fake(function (Request $request, array $options) use (&$timeouts) {
        $timeouts[] = $options['timeout'];

        return match (true) {
            str_contains($request->url(), 'codex.local') => Http::response(['error' => 'inference_timeout'], 504),
            $request['model'] === 'main-model' => Http::response('bad gateway', 502),
            default => llmOk(),
        };
    });

    expect(app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]))->toBe(['ok' => true]);
    Http::assertSent(fn (Request $request) => ($request['model'] ?? null) === 'backup-model');
    Http::assertSentCount(3);
    expect($timeouts)->toBe([50, 30, 30]);
});

it('propagates the API error when both codex and the saved provider fail', function () {
    useCodexApiFallback();
    Http::preventStrayRequests();
    Http::fake([
        'codex.local:8080/chat' => Http::response(['error' => 'inference_timeout'], 504),
        'llm.internal/*' => Http::response('bad gateway', 502),
    ]);

    expect(fn () => app(LlmClient::class)->chat([['role' => 'user', 'content' => 'test']], ['name' => 'x', 'schema' => ['type' => 'object']]))->toThrow(RequestException::class);
    Http::assertSentCount(3);
});

function llmTruncated(): PromiseInterface
{
    return Http::response(['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{"results":[{"opinion_ind']]]]);
}

it('treats an answer cut off at max_tokens as a failure instead of an empty result', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'main-model', 'api_key' => 'key']);
    Http::preventStrayRequests();
    Http::fake(['llm.internal/*' => llmTruncated()]);

    expect(fn () => (new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]))->toThrow(LlmResponseUnusable::class);
});

it('treats a completion that is not a JSON object as a failure', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'main-model', 'api_key' => 'key']);
    Http::preventStrayRequests();
    Http::fake(['llm.internal/*' => llmOk('Maaf, saya tidak bisa menjawab.')]);

    expect(fn () => (new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]))->toThrow(LlmResponseUnusable::class);
});

it('retries once with the fallback model on an unusable answer without parking the main model', function () {
    llmSettingWithFallback();
    Http::preventStrayRequests();
    Http::fake(fn (Request $request) => $request['model'] === 'main-model' ? llmTruncated() : llmOk());

    expect((new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]))->toBe(['ok' => true])
        ->and(Cache::has('llm:primary-down:'.md5('main-model')))->toBeFalse();
    Http::assertSent(fn (Request $request) => $request['model'] === 'backup-model');
});

it('throws when the fallback model also returns an unusable answer', function () {
    llmSettingWithFallback();
    Http::preventStrayRequests();
    Http::fake(['llm.internal/*' => llmTruncated()]);

    expect(fn () => (new LlmClient)->chat([['role' => 'user', 'content' => 'hi']]))->toThrow(LlmResponseUnusable::class);
    Http::assertSentCount(2);
});
