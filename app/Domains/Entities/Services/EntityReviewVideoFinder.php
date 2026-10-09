<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Models\Entity;
use Illuminate\Support\Facades\Http;

/**
 * Finds YouTube review videos for a product. One search call (100 quota units)
 * per product. A video is kept only when its title is clearly about this exact
 * model: every model-code token of the name appears, and a variant word
 * ("pro", "ultra", ...) following the code is part of the name too, so a
 * "Galaxy S24 Ultra" review is not shown on "Galaxy S24".
 */
class EntityReviewVideoFinder
{
    private const REVIEW_WORDS = ['review', 'ulasan', 'hands on', 'handson', 'unboxing', 'test', 'uji', 'tes', 'kupas'];

    /**
     * @return list<array{youtube_id: string, title: string, published_at: string|null}>
     */
    public function find(Entity $entity, int $limit = 3): array
    {
        $response = Http::timeout(15)->get(rtrim((string) config('sources.youtube.api_url'), '/').'/search', [
            'part' => 'snippet',
            'type' => 'video',
            'q' => $entity->name.' review',
            'maxResults' => 15,
            'videoEmbeddable' => 'true',
            'regionCode' => 'ID',
            'relevanceLanguage' => 'id',
            'key' => (string) config('sources.youtube.api_key'),
        ]);
        $response->throw();

        $videos = [];

        foreach ((array) $response->json('items', []) as $item) {
            $id = (string) ($item['id']['videoId'] ?? '');
            $title = html_entity_decode((string) ($item['snippet']['title'] ?? ''), ENT_QUOTES | ENT_HTML5);

            if ($id === '' || $title === '' || ! $this->isAboutEntity($entity->name, $title)) {
                continue;
            }

            $videos[$id] = [
                'youtube_id' => $id,
                'title' => mb_substr($title, 0, 250),
                'published_at' => isset($item['snippet']['publishedAt']) ? (string) $item['snippet']['publishedAt'] : null,
            ];

            if (count($videos) === $limit) {
                break;
            }
        }

        return array_values($videos);
    }

    public function isAboutEntity(string $entityName, string $videoTitle): bool
    {
        $nameTokens = $this->tokens($entityName);
        $titleText = ' '.implode(' ', $this->tokens($videoTitle)).' ';
        $titleTokens = $this->tokens($videoTitle);

        if ($nameTokens === [] || ! $this->mentionsReview($titleText)) {
            return false;
        }

        $codeTokens = array_values(array_filter($nameTokens, fn (string $token): bool => preg_match('/\d/', $token) === 1));
        $required = $codeTokens === [] ? $nameTokens : [$nameTokens[0], ...$codeTokens];

        foreach ($required as $token) {
            if (! str_contains($titleText, " {$token} ")) {
                return false;
            }
        }

        $suffixes = (array) config('entity_matching.model_variant_suffixes', []);

        foreach ($codeTokens as $code) {
            $position = array_search($code, $titleTokens, true);
            $next = $position === false ? null : ($titleTokens[$position + 1] ?? null);

            if ($next !== null && in_array($next, $suffixes, true) && ! in_array($next, $nameTokens, true)) {
                return false;
            }
        }

        return true;
    }

    private function mentionsReview(string $titleText): bool
    {
        foreach (self::REVIEW_WORDS as $word) {
            if (str_contains($titleText, " {$word} ")) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        return array_values(array_filter(explode(' ', TextNormalizer::normalize($text)), fn (string $token): bool => $token !== ''));
    }
}
