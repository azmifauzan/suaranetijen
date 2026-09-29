<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Models\LlmSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for an OpenAI-compatible chat completions endpoint. Every
 * LLM-backed feature resolves its settings through this class rather than
 * reading config()/env() or a provider SDK directly, so base_url/model/key
 * stay admin-editable from one place (see the llm_settings table).
 */
class LlmClient
{
    private const PRIMARY_DOWN_KEY_PREFIX = 'llm:primary-down:';

    private const PRIMARY_DOWN_SECONDS = 300;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{name: string, schema: array<string, mixed>}|null  $jsonSchema
     * @param  int|null  $timeoutSeconds  Overrides the configured timeout for one long-running call.
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?array $jsonSchema = null, ?int $timeoutSeconds = null): array
    {
        $settings = $this->resolveSettings();
        $fallback = $settings['fallback_model'];

        if ($fallback === null || $fallback === $settings['model']) {
            return $this->send($settings, (string) $settings['model'], $messages, $jsonSchema, $timeoutSeconds);
        }

        $downKey = self::PRIMARY_DOWN_KEY_PREFIX.md5((string) $settings['model']);

        if (Cache::has($downKey)) {
            return $this->send($settings, $fallback, $messages, $jsonSchema, $timeoutSeconds);
        }

        try {
            return $this->send($settings, (string) $settings['model'], $messages, $jsonSchema, $timeoutSeconds);
        } catch (ConnectionException|RequestException $e) {
            if ($e instanceof RequestException && $e->response->status() < 500 && $e->response->status() !== 429) {
                throw $e;
            }

            Cache::put($downKey, true, self::PRIMARY_DOWN_SECONDS);
            Log::warning('llm.primary_failed_using_fallback', ['model' => $settings['model'], 'fallback' => $fallback, 'error' => $e->getMessage()]);

            return $this->send($settings, $fallback, $messages, $jsonSchema, $timeoutSeconds);
        }
    }

    /**
     * @param  array{base_url: string|null, model: string|null, fallback_model: string|null, api_key: string|null, max_tokens: int, temperature: float, timeout_seconds: int}  $settings
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{name: string, schema: array<string, mixed>}|null  $jsonSchema
     * @return array<string, mixed>
     */
    private function send(array $settings, string $model, array $messages, ?array $jsonSchema, ?int $timeoutSeconds): array
    {
        $body = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $settings['max_tokens'],
            'temperature' => $settings['temperature'],
        ];

        if ($jsonSchema !== null) {
            $body['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $jsonSchema,
            ];
        }

        $response = Http::withToken($settings['api_key'])
            ->timeout($timeoutSeconds ?? $settings['timeout_seconds'])
            ->post(rtrim((string) $settings['base_url'], '/').'/chat/completions', $body);
        $response->throw();

        $content = (string) $response->json('choices.0.message.content', '{}');

        return (array) json_decode($content, true);
    }

    /**
     * @return array{base_url: string|null, model: string|null, fallback_model: string|null, api_key: string|null, max_tokens: int, temperature: float, timeout_seconds: int}
     */
    private function resolveSettings(): array
    {
        $row = LlmSetting::query()->first();

        if ($row === null) {
            return [
                'base_url' => config('services.llm.base_url'),
                'model' => config('services.llm.model'),
                'fallback_model' => null,
                'api_key' => config('services.llm.api_key'),
                'max_tokens' => (int) config('services.llm.max_tokens', 1024),
                'temperature' => (float) config('services.llm.temperature', 0.2),
                'timeout_seconds' => (int) config('services.llm.timeout_seconds', 30),
            ];
        }

        return [
            'base_url' => $row->base_url ?: config('services.llm.base_url'),
            'model' => $row->model ?: config('services.llm.model'),
            'fallback_model' => $row->fallback_model ?: null,
            'api_key' => $row->api_key ?: config('services.llm.api_key'),
            'max_tokens' => $row->max_tokens,
            'temperature' => $row->temperature,
            'timeout_seconds' => $row->timeout_seconds,
        ];
    }
}
