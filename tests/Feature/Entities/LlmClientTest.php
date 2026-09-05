<?php

use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Entities\Services\LlmClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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
