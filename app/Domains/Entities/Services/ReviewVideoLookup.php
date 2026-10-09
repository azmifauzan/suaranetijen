<?php

namespace App\Domains\Entities\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Turns an admin-pasted YouTube link into a video id and confirms YouTube can
 * embed it. Uses the public oEmbed endpoint, which needs no API key and costs
 * no Data API quota.
 */
class ReviewVideoLookup
{
    /**
     * Accepts a watch, youtu.be, embed, shorts or live URL, or a bare 11-character id.
     */
    public function parseId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input) === 1) {
            return $input;
        }

        $parts = parse_url(str_contains($input, '://') ? $input : 'https://'.$input);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;

        if ($host === 'youtu.be') {
            $candidate = trim((string) ($parts['path'] ?? ''), '/');
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $path = trim((string) ($parts['path'] ?? ''), '/');
            $candidate = match (true) {
                is_string($query['v'] ?? null) => $query['v'],
                preg_match('#^(?:embed|shorts|live|v)/([^/?]+)#', $path, $m) === 1 => $m[1],
                default => '',
            };
        } else {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1 ? $candidate : null;
    }

    /**
     * The video's public title, or null when YouTube cannot find or embed it.
     */
    public function embeddableTitle(string $youtubeId): ?string
    {
        try {
            $response = Http::timeout(10)->get('https://www.youtube.com/oembed', [
                'url' => "https://www.youtube.com/watch?v={$youtubeId}",
                'format' => 'json',
            ]);
        } catch (ConnectionException) {
            return null;
        }

        $title = $response->successful() ? trim((string) $response->json('title')) : '';

        return $title === '' ? null : mb_substr($title, 0, 250);
    }
}
