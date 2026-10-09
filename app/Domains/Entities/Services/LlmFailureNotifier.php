<?php

namespace App\Domains\Entities\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram alert when an LLM call fails, so an empty balance or a bad key is noticed before
 * summaries and themes silently stop. Reuses the sponsorship bot; queue workers run under
 * their own APP_ENV, so there is deliberately no environment filter here. Never throws.
 */
class LlmFailureNotifier
{
    private const THROTTLE_MINUTES = 30;

    private const QUOTA_PATTERN = '/quota|credit|balance|saldo|billing|insufficient|payment|exceeded your current/i';

    public function notify(Throwable $exception, string $model): void
    {
        $botToken = trim((string) config('sponsorship.telegram.bot_token'));
        $chatId = trim((string) config('sponsorship.telegram.chat_id'));

        if ($botToken === '' || $chatId === '') {
            return;
        }

        $kind = $this->classify($exception);
        if ($kind === null || ! Cache::add("llm:failure-alert:{$kind['key']}", true, now()->addMinutes(self::THROTTLE_MINUTES))) {
            return;
        }

        try {
            Http::connectTimeout(3)->timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => implode("\n", [
                    '⚠️ SuaraNetijen | LLM gagal',
                    "Penyebab: {$kind['label']}",
                    "Model: {$model}",
                    'Host: '.gethostname(),
                    'Detail: '.$kind['detail'],
                    'Alert yang sama ditahan '.self::THROTTLE_MINUTES.' menit.',
                ]),
                'disable_web_page_preview' => true,
            ])->throw();
        } catch (Throwable $e) {
            Log::warning('llm.failure_alert_not_sent', ['error' => $e->getMessage()]);
        }
    }

    /**
     * A plain 429 is the routine rate limit the jobs already retry, so it only alerts when the
     * body says the quota or balance is gone.
     *
     * @return array{key: string, label: string, detail: string}|null
     */
    private function classify(Throwable $exception): ?array
    {
        if ($exception instanceof ConnectionException) {
            return ['key' => 'connection', 'label' => 'tidak bisa terhubung ke penyedia LLM', 'detail' => mb_substr($exception->getMessage(), 0, 160)];
        }

        if (! $exception instanceof RequestException) {
            return null;
        }

        $status = $exception->response->status();
        $body = mb_substr(trim($exception->response->body()), 0, 200);
        $quotaGone = $status === 402 || preg_match(self::QUOTA_PATTERN, $body) === 1;

        return match (true) {
            $quotaGone => ['key' => 'quota', 'label' => 'saldo/kuota habis, perlu top up', 'detail' => "HTTP {$status} {$body}"],
            in_array($status, [401, 403], true) => ['key' => 'auth', 'label' => 'API key ditolak', 'detail' => "HTTP {$status} {$body}"],
            $status >= 500 => ['key' => 'server', 'label' => 'penyedia LLM error', 'detail' => "HTTP {$status} {$body}"],
            $status === 429 => null,
            default => ['key' => "http-{$status}", 'label' => 'permintaan ditolak', 'detail' => "HTTP {$status} {$body}"],
        };
    }
}
