<?php

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
    Http::assertSent(fn (Request $request) => $request['model'] === 'backup-model');
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
