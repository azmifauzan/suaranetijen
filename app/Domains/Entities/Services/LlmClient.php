<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Exceptions\LlmResponseUnusable;
use App\Domains\Entities\Models\LlmSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Shared JSON inference entry point. A node can opt into its private Codex runner;
 * API nodes keep using the admin-managed llm_settings row.
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
        if (config('services.llm.provider') === 'codex_subscription') {
            if ($jsonSchema === null) {
                throw new InvalidArgumentException('Codex inference requires a JSON schema.');
            }

            $apiFallbackEnabled = (bool) config('services.llm.codex_api_fallback', false);
            $codexTimeout = $apiFallbackEnabled ? 45 : 120;

            try {
                $response = Http::withToken(config('services.llm.codex_runner_token'))
                    ->connectTimeout(5)
                    ->timeout($codexTimeout + 5)
                    ->post(rtrim((string) config('services.llm.codex_runner_url'), '/').'/chat', [
                        'messages' => $messages,
                        'json_schema' => $jsonSchema,
                        'timeout_seconds' => $codexTimeout,
                    ])->throw();

                $result = $response->json();
                if (! is_array($result) || ! is_object($response->object())) {
                    throw new UnexpectedValueException('Codex runner returned an invalid JSON object.');
                }

                return $result;
            } catch (ConnectionException|RequestException|UnexpectedValueException $e) {
                if (! $apiFallbackEnabled) {
                    throw $e;
                }

                if ($e instanceof RequestException) {
                    $status = $e->response->status();
                    if (($status < 500 && ! in_array($status, [401, 403, 429], true))
                        || ($status === 429 && in_array($e->response->json('error'), ['runner_busy', 'runner_rate_limited'], true))) {
                        throw $e;
                    }
                }

                Cache::increment('llm:codex-api-fallback:'.gethostname());
                Log::warning('llm.codex_failed_using_api', [
                    'exception' => $e::class,
                    'http_status' => $e instanceof RequestException ? $e->response->status() : null,
                ]);
                // 50s Codex HTTP + two 30s API attempts fit the 140s job timeout.
                $timeoutSeconds = 30;
            }
        }

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
        } catch (ConnectionException|RequestException|LlmResponseUnusable $e) {
            if ($e instanceof RequestException && $e->response->status() < 500 && $e->response->status() !== 429) {
                throw $e;
            }

            // An unusable answer is specific to this prompt, so it falls back once without parking the primary.
            if (! $e instanceof LlmResponseUnusable) {
                Cache::put($downKey, true, self::PRIMARY_DOWN_SECONDS);
            }
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

        try {
            $response = Http::withToken($settings['api_key'])
                ->timeout($timeoutSeconds ?? $settings['timeout_seconds'])
                ->post(rtrim((string) $settings['base_url'], '/').'/chat/completions', $body);
            $response->throw();
        } catch (ConnectionException|RequestException $e) {
            app(LlmFailureNotifier::class)->notify($e, $model);

            throw $e;
        }

        $content = (string) $response->json('choices.0.message.content', '{}');
        $decoded = json_decode($content, true);
        $finishReason = $response->json('choices.0.finish_reason');

        if ($finishReason === 'length' || ! is_array($decoded)) {
            Log::warning('llm.response_unusable', ['model' => $model, 'finish_reason' => $finishReason]);

            throw new LlmResponseUnusable($finishReason === 'length'
                ? "{$model} was cut off at max_tokens before finishing the JSON answer."
                : "{$model} did not return a JSON object.");
        }

        return $decoded;
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
