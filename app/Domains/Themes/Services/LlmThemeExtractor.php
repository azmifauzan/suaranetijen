<?php

namespace App\Domains\Themes\Services;

use App\Domains\Entities\Exceptions\LlmResponseUnusable;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Services\LlmClient;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeAlias;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Extracts specific theme phrases ("baterai cepat habis") from one opinion via the
 * shared LlmClient (docs/25 "discovered from data"). Every theme must be backed by
 * an evidence span found verbatim in the opinion; anything else is dropped as a
 * hallucination. Only the paraphrased context is kept — never the raw text.
 */
class LlmThemeExtractor
{
    private const MAX_INPUT_CHARS = 4000;

    private const MAX_THEMES = 5;

    private const MAX_LABEL_WORDS = 6;

    private const MAX_CONTEXT_CHARS = 200;

    private const KNOWN_LABELS_PER_SCOPE = 25;

    private const MAX_BATCH_ITEM_CHARS = 1200;

    private const MIN_ALIAS_CHARS_IN_LABEL = 4;

    /**
     * Fillers a model writes when an opinion holds no concrete judgement ("penyebutan tanpa penilaian").
     */
    private const META_LABEL_PATTERN = '/\b(tanpa (penilaian|opini)|tidak ada (penilaian|opini)|penyebutan)\b/iu';

    /**
     * A batch asks for many opinions' themes in one completion, which routinely outlasts the 30s default.
     */
    private const BATCH_TIMEOUT_SECONDS = 50;

    public function __construct(
        private readonly LlmClient $client,
        private readonly ThemeNormalizer $normalizer,
    ) {}

    /**
     * $aboutEntity is set to false when the model judges the opinion is not about this entity at all
     * (the name only coincides with an everyday word); the caller must then drop the opinion.
     *
     * @return list<array{theme: Theme, sentiment: SentimentClass, confidence: float, context: string|null}>
     */
    public function extract(int $entityId, string $entityName, string $text, bool &$aboutEntity = true): array
    {
        $text = mb_substr($text, 0, self::MAX_INPUT_CHARS);
        $normalizedText = TextNormalizer::normalize($text);

        if ($normalizedText === '') {
            return [];
        }

        $response = $this->client->chat($this->messages($entityId, $entityName, $text), $this->schema());

        $aboutEntity = ($response['about_entity'] ?? true) !== false;
        if (! $aboutEntity) {
            return [];
        }

        if (! is_array($response['themes'] ?? null)) {
            throw new LlmResponseUnusable('The theme answer has no themes list.');
        }

        $entityTerms = $this->entityTerms($entityId, $entityName);
        $found = [];

        foreach (array_slice($response['themes'], 0, self::MAX_THEMES) as $item) {
            $parsed = is_array($item) ? $this->parseItem($item, $normalizedText, $entityTerms) : null;
            if ($parsed === null) {
                continue;
            }

            $theme = $this->resolveOrCreateTheme($parsed['label']);
            if ($theme === null) {
                continue;
            }

            $found[$theme->id] ??= [
                'theme' => $theme,
                'sentiment' => $parsed['sentiment'],
                'confidence' => 0.8,
                'context' => $parsed['context'],
            ];
        }

        return array_values($found);
    }

    /**
     * Batched sibling of extract(): one LLM call covers many opinions from the same
     * entity instead of one call each. Amortizing the instructions+known-labels prompt
     * over a batch is the main cost saving; seeing several opinions together also lets
     * the model reuse one label across them instead of each opinion minting its own
     * near-duplicate ("baterai boros" / "baterai cepat habis" / "baterai drop").
     *
     * Keys of opinions the model judged not to be about the entity at all are appended to
     * $offTopic; the caller must drop those opinions. Keys the model left out of its answer are
     * appended to $unanswered; the caller must not treat those as "no themes".
     *
     * @param  array<int|string, array{key: int|string, text: string}>  $items  keyed however the caller likes; that key is echoed back
     * @param  list<int|string>  $offTopic
     * @param  list<int|string>  $unanswered
     * @return array<int|string, list<array{theme: Theme, sentiment: SentimentClass, confidence: float, context: string|null}>>
     */
    public function extractBatch(int $entityId, string $entityName, array $items, array &$offTopic = [], array &$unanswered = []): array
    {
        $items = array_values($items);
        if ($items === []) {
            return [];
        }

        $normalizedTexts = [];
        foreach ($items as $index => $item) {
            $normalizedTexts[$index] = TextNormalizer::normalize(mb_substr($item['text'], 0, self::MAX_BATCH_ITEM_CHARS));
        }

        $response = $this->client->chat($this->batchMessages($entityId, $entityName, $items), $this->batchSchema(), self::BATCH_TIMEOUT_SECONDS);

        if (! is_array($response['results'] ?? null)) {
            throw new LlmResponseUnusable('The batch answer has no results list.');
        }

        $entityTerms = $this->entityTerms($entityId, $entityName);
        $found = [];
        $answered = [];

        foreach ($response['results'] as $result) {
            if (! is_array($result)) {
                continue;
            }

            $index = (int) ($result['opinion_index'] ?? 0) - 1;
            if (! isset($items[$index])) {
                continue;
            }

            $answered[$index] = true;
            $key = $items[$index]['key'];
            $normalizedText = $normalizedTexts[$index];

            if (($result['about_entity'] ?? true) === false) {
                $offTopic[] = $key;

                continue;
            }

            foreach (array_slice((array) ($result['themes'] ?? []), 0, self::MAX_THEMES) as $themeItem) {
                $parsed = is_array($themeItem) ? $this->parseItem($themeItem, $normalizedText, $entityTerms) : null;
                if ($parsed === null) {
                    continue;
                }

                $theme = $this->resolveOrCreateTheme($parsed['label']);
                if ($theme === null) {
                    continue;
                }

                $found[$key] ??= [];
                $found[$key][$theme->id] ??= [
                    'theme' => $theme,
                    'sentiment' => $parsed['sentiment'],
                    'confidence' => 0.8,
                    'context' => $parsed['context'],
                ];
            }
        }

        foreach ($items as $index => $item) {
            if (! isset($answered[$index])) {
                $unanswered[] = $item['key'];
            }
        }

        return array_map('array_values', $found);
    }

    /**
     * Relevance-only pass over short summaries of opinions already stored (raw text has expired),
     * returning the keys judged not to be about the entity.
     *
     * @param  list<array{key: int|string, text: string}>  $items
     * @return list<int|string>
     */
    public function judgeRelevance(int $entityId, string $entityName, array $items): array
    {
        if ($items === []) {
            return [];
        }

        $numbered = [];
        foreach ($items as $index => $item) {
            $numbered[] = '['.($index + 1).'] '.mb_substr($item['text'], 0, self::MAX_CONTEXT_CHARS * 3);
        }

        $response = $this->client->chat([
            [
                'role' => 'system',
                'content' => "You check which numbered notes really concern \"{$entityName}\". Each note is a short summary of one netizen "
                    .'opinion that was attributed to this entity by a name match. Entity profile: '.$this->entityProfile($entityId, $entityName).'. '
                    .$this->relevanceRule($entityName)
                    .'Return every note with its about_entity verdict; opinion_index is 1-based.',
            ],
            ['role' => 'user', 'content' => implode("\n\n", $numbered)],
        ], [
            'name' => 'relevance_check',
            'schema' => [
                'type' => 'object',
                'properties' => ['results' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => ['opinion_index' => ['type' => 'integer'], 'about_entity' => ['type' => 'boolean']],
                    'required' => ['opinion_index', 'about_entity'],
                    'additionalProperties' => false,
                ]]],
                'required' => ['results'],
                'additionalProperties' => false,
            ],
        ]);

        $offTopic = [];
        foreach ((array) ($response['results'] ?? []) as $result) {
            $index = (int) ($result['opinion_index'] ?? 0) - 1;
            if (is_array($result) && isset($items[$index]) && ($result['about_entity'] ?? true) === false) {
                $offTopic[] = $items[$index]['key'];
            }
        }

        return $offTopic;
    }

    /**
     * @param  array<mixed>  $item
     * @param  list<string>  $entityTerms  normalized names the label must not contain
     * @return array{label: string, sentiment: SentimentClass, context: string|null}|null
     */
    private function parseItem(array $item, string $normalizedText, array $entityTerms): ?array
    {
        $label = trim((string) ($item['label'] ?? ''));
        $sentiment = SentimentClass::tryFrom((string) ($item['sentiment'] ?? ''));
        $evidence = TextNormalizer::normalize((string) ($item['evidence'] ?? ''));

        $labelStance = SentimentClass::tryFrom((string) ($item['label_stance'] ?? ''));
        $contradicts = $labelStance !== null && $sentiment !== null
            && $labelStance !== SentimentClass::Neutral && $sentiment !== SentimentClass::Neutral && $labelStance !== $sentiment;

        $wordCount = count(preg_split('/\s+/u', $label) ?: []);

        if (! PublicCopyGuard::isAllowed($label, 60) || $wordCount > self::MAX_LABEL_WORDS
            || $this->containsIdentifier($label) || $this->isEmptyOrNamesEntity($label, $entityTerms)
            || $sentiment === null || $contradicts
            || $evidence === '' || ! str_contains($normalizedText, $evidence)) {
            return null;
        }

        $context = trim((string) ($item['context'] ?? ''));
        $context = ($context === '' || $this->containsIdentifier($context))
            ? null
            : mb_substr($context, 0, self::MAX_CONTEXT_CHARS);

        return ['label' => $label, 'sentiment' => $sentiment, 'context' => $context];
    }

    /**
     * The prompt forbids a model name in a label and a filler label for an opinion with no judgement;
     * models ignore both, so the code enforces them. Dropping the theme beats guessing a rewritten label.
     *
     * @param  list<string>  $entityTerms
     */
    private function isEmptyOrNamesEntity(string $label, array $entityTerms): bool
    {
        if (preg_match(self::META_LABEL_PATTERN, $label) === 1) {
            return true;
        }

        $normalizedLabel = TextNormalizer::normalize($label);

        foreach ($entityTerms as $term) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u', $normalizedLabel) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function entityTerms(int $entityId, string $entityName): array
    {
        $terms = [TextNormalizer::normalize($entityName)];

        foreach (EntityAlias::query()->where('entity_id', $entityId)->pluck('normalized_alias') as $alias) {
            if (mb_strlen((string) $alias) >= self::MIN_ALIAS_CHARS_IN_LABEL) {
                $terms[] = (string) $alias;
            }
        }

        return array_values(array_filter(array_unique($terms), fn (string $term) => $term !== ''));
    }

    private function containsIdentifier(string $value): bool
    {
        return (bool) preg_match('/@\w|https?:\/\/|www\./iu', $value);
    }

    private function resolveOrCreateTheme(string $label): ?Theme
    {
        $existing = $this->normalizer->resolveTheme($label);
        if ($existing !== null) {
            return $existing;
        }

        $slug = Str::slug($label);
        $normalized = $this->normalizer->normalize($label);
        if ($slug === '' || $normalized === '') {
            return null;
        }

        $theme = Theme::query()->createOrFirst(
            ['slug' => $slug],
            ['display_label' => Str::ucfirst(mb_strtolower($label)), 'canonical_key' => $slug]
        );

        ThemeAlias::query()->createOrFirst(
            ['theme_id' => $theme->id, 'normalized_alias' => $normalized],
            ['alias' => $label]
        );

        return $theme;
    }

    /**
     * What the entity actually is, so the model can tell a real mention from a coincidental word
     * ("flip top" packaging vs. the Flip transfer app).
     */
    private function entityProfile(int $entityId, string $entityName): string
    {
        $entity = Entity::query()->with('category')->find($entityId);
        $profile = $entityName;

        if ($entity?->category !== null) {
            $profile .= " (kategori: {$entity->category->name})";
        }

        if (filled($entity?->description)) {
            $profile .= ' — '.$entity->description;
        }

        return $profile;
    }

    private function relevanceRule(string $entityName): string
    {
        return 'about_entity: false when the opinion is NOT about this specific entity as described in its profile — '
            .'the name merely coincides with an everyday word, another product, a person, a place or a different topic '
            .'(e.g. "flip top" packaging for a transfer app, "vidio" meaning any video, "aqua" as an ingredient, '
            .'a football club for a snack brand). Judge by what the opinion is actually about, not by the word alone. '
            .'When false, return no themes. ';
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function messages(int $entityId, string $entityName, string $text): array
    {
        $known = $this->knownLabels($entityId);

        return [
            [
                'role' => 'system',
                'content' => "You extract what Indonesian netizens say about \"{$entityName}\" from ONE opinion. "
                    .'Entity profile: '.$this->entityProfile($entityId, $entityName).'. '
                    .$this->relevanceRule($entityName)
                    .'Return at most 5 themes, only about '.$entityName.' itself. '
                    .'label: a short Indonesian phrase, 2-6 words, lowercase, naming the specific thing AND the judgement '
                    .'(e.g. "baterai cepat habis", "kamera malam bagus", "cs lambat merespons", "harga seri a terjangkau"). '
                    .'Avoid a bare generic word like "bagus" or "mahal" when the opinion says what is good or expensive. '
                    .'Never put a brand, product or model name in the label ("harga murah", not "harga s24 murah"), so the same theme '
                    .'gets the same label across opinions. If an opinion is really about a different model or variant than '
                    .$entityName.' (e.g. an FE, Plus or Ultra version), it has no theme about '.$entityName.'. '
                    .'If a known label below means the same thing, reuse that exact label; never reuse one that names a different kind of thing than '.$entityName.' (a car label for a food, a phone label for a bank). '
                    .'label_stance: the judgement the LABEL itself states (positive for "kamera bagus", negative for "baterai cepat habis", neutral if it names a topic only). '
                    .'It must agree with sentiment: when the opinion\'s stance is the opposite of a known label, do NOT reuse that label; write a new label with the opposite judgement '
                    .'("kamera kurang bagus", not "kamera bagus" marked negative). '
                    .'sentiment: the opinion\'s stance on that theme (positive, neutral, negative). '
                    .'evidence: copy the exact words from the opinion (max 12 words) that support the theme. '
                    .'context: one sentence in Indonesian, max 25 words, in your own words, paraphrasing what was said — '
                    .'never names, usernames, links or direct quotes. '
                    .'No numeric scores. If the opinion has no concrete judgement about '.$entityName.', return an empty list.',
            ],
            [
                'role' => 'user',
                'content' => 'Known labels: '.($known === [] ? '(none yet)' : implode('; ', $known))
                    ."\n\nOpinion:\n".$text,
            ],
        ];
    }

    /**
     * @param  list<array{key: int|string, text: string}>  $items
     * @return list<array{role: string, content: string}>
     */
    private function batchMessages(int $entityId, string $entityName, array $items): array
    {
        $known = $this->knownLabels($entityId);

        $numbered = [];
        foreach ($items as $index => $item) {
            $numbered[] = '['.($index + 1).'] '.mb_substr($item['text'], 0, self::MAX_BATCH_ITEM_CHARS);
        }

        return [
            [
                'role' => 'system',
                'content' => "You extract what Indonesian netizens say about \"{$entityName}\" from a numbered list of "
                    .'independent opinions. Entity profile: '.$this->entityProfile($entityId, $entityName).'. '
                    .$this->relevanceRule($entityName)
                    .'Process EACH opinion on its own — never let one opinion\'s content leak '
                    .'into another\'s themes/evidence. For each opinion, return at most 5 themes, only about '.$entityName.' itself. '
                    .'label: a short Indonesian phrase, 2-6 words, lowercase, naming the specific thing AND the judgement '
                    .'(e.g. "baterai cepat habis", "kamera malam bagus", "cs lambat merespons", "harga seri a terjangkau"). '
                    .'Avoid a bare generic word like "bagus" or "mahal" when the opinion says what is good or expensive. '
                    .'Never put a brand, product or model name in the label ("harga murah", not "harga s24 murah"), so the same theme '
                    .'gets the same label across opinions. If an opinion is really about a different model or variant than '
                    .$entityName.' (e.g. an FE, Plus or Ultra version), it has no theme about '.$entityName.'. '
                    .'Reuse the exact same label across opinions that describe the same underlying theme — do not mint a '
                    .'new near-duplicate label ("baterai boros" vs "baterai cepat habis") for what is really one theme, '
                    .'either within this batch or against a known label below. Never reuse a known label that names a different kind of thing than '.$entityName.' (a car label for a food, a phone label for a bank). '
                    .'label_stance: the judgement the LABEL itself states (positive for "kamera bagus", negative for "baterai cepat habis", neutral if it names a topic only). '
                    .'It must agree with sentiment: when the opinion\'s stance is the opposite of a known label, do NOT reuse that label; write a new label with the opposite judgement '
                    .'("kamera kurang bagus", not "kamera bagus" marked negative). '
                    .'sentiment: that opinion\'s stance on that theme (positive, neutral, negative). '
                    .'evidence: copy the exact words from THAT SAME opinion (max 12 words) that support the theme. '
                    .'context: one sentence in Indonesian, max 25 words, in your own words, paraphrasing what was said — '
                    .'never names, usernames, links or direct quotes. '
                    .'No numeric scores. opinion_index is 1-based and must match the numbered opinion below. Include EVERY opinion in results '
                    .'with its about_entity verdict; use an empty themes list if it has no concrete judgement about '.$entityName.'.',
            ],
            [
                'role' => 'user',
                'content' => 'Known labels: '.($known === [] ? '(none yet)' : implode('; ', $known))
                    ."\n\nOpinions:\n".implode("\n\n", $numbered),
            ],
        ];
    }

    /**
     * Labels already used for this entity first, then for entities in the same root
     * category, so the LLM reuses them instead of inventing near-duplicates (lazy
     * clustering, docs/25). The fallback is category-scoped: a global list let
     * "kualitas mobil bagus" leak onto a milk brand.
     *
     * @return list<string>
     */
    private function knownLabels(int $entityId): array
    {
        $categoryId = Entity::query()->whereKey($entityId)->value('category_id');
        $rootCategoryId = $categoryId === null
            ? null
            : (Category::query()->whereKey($categoryId)->value('parent_id') ?? $categoryId);

        $query = fn (string $scopeKey, Closure $scope): array => Cache::remember(
            'themes:known-labels:'.$scopeKey,
            now()->addMinutes(10),
            fn () => Theme::query()
                ->join('theme_observations', 'themes.id', '=', 'theme_observations.theme_id')
                ->where('theme_observations.extractor', 'llm')
                ->where($scope)
                ->groupBy('themes.id', 'themes.display_label')
                ->orderByRaw('count(*) desc')
                ->limit(self::KNOWN_LABELS_PER_SCOPE)
                ->pluck('display_label')
                ->all()
        );

        $labels = $query('entity:'.$entityId, fn ($q) => $q->where('theme_observations.entity_id', $entityId));

        if ($rootCategoryId !== null) {
            $labels = array_merge($labels, $query('category:'.$rootCategoryId, fn ($q) => $q->whereIn(
                'theme_observations.entity_id',
                Entity::query()
                    ->select('entities.id')
                    ->join('categories', 'categories.id', '=', 'entities.category_id')
                    ->where(fn ($c) => $c->where('categories.id', $rootCategoryId)->orWhere('categories.parent_id', $rootCategoryId))
            )));
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    private function schema(): array
    {
        return [
            'name' => 'opinion_themes',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'about_entity' => ['type' => 'boolean'],
                    'themes' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'label' => ['type' => 'string'],
                                'label_stance' => ['type' => 'string', 'enum' => array_column(SentimentClass::cases(), 'value')],
                                'sentiment' => ['type' => 'string', 'enum' => array_column(SentimentClass::cases(), 'value')],
                                'evidence' => ['type' => 'string'],
                                'context' => ['type' => 'string'],
                            ],
                            'required' => ['label', 'label_stance', 'sentiment', 'evidence', 'context'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['about_entity', 'themes'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    private function batchSchema(): array
    {
        return [
            'name' => 'opinion_themes_batch',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'results' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'opinion_index' => ['type' => 'integer'],
                                'about_entity' => ['type' => 'boolean'],
                                'themes' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'label' => ['type' => 'string'],
                                            'label_stance' => ['type' => 'string', 'enum' => array_column(SentimentClass::cases(), 'value')],
                                            'sentiment' => ['type' => 'string', 'enum' => array_column(SentimentClass::cases(), 'value')],
                                            'evidence' => ['type' => 'string'],
                                            'context' => ['type' => 'string'],
                                        ],
                                        'required' => ['label', 'label_stance', 'sentiment', 'evidence', 'context'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['opinion_index', 'about_entity', 'themes'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['results'],
                'additionalProperties' => false,
            ],
        ];
    }
}
