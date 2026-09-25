<?php

namespace App\Domains\Themes\Services;

use App\Domains\Entities\Services\LlmClient;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeAlias;
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

    public function __construct(
        private readonly LlmClient $client,
        private readonly ThemeNormalizer $normalizer,
    ) {}

    /**
     * @return list<array{theme: Theme, sentiment: SentimentClass, confidence: float, context: string|null}>
     */
    public function extract(int $entityId, string $entityName, string $text): array
    {
        $text = mb_substr($text, 0, self::MAX_INPUT_CHARS);
        $normalizedText = TextNormalizer::normalize($text);

        if ($normalizedText === '') {
            return [];
        }

        $response = $this->client->chat($this->messages($entityId, $entityName, $text), $this->schema());

        $found = [];

        foreach (array_slice((array) ($response['themes'] ?? []), 0, self::MAX_THEMES) as $item) {
            $parsed = is_array($item) ? $this->parseItem($item, $normalizedText) : null;
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
     * @param  array<mixed>  $item
     * @return array{label: string, sentiment: SentimentClass, context: string|null}|null
     */
    private function parseItem(array $item, string $normalizedText): ?array
    {
        $label = trim((string) ($item['label'] ?? ''));
        $sentiment = SentimentClass::tryFrom((string) ($item['sentiment'] ?? ''));
        $evidence = TextNormalizer::normalize((string) ($item['evidence'] ?? ''));

        $wordCount = count(preg_split('/\s+/u', $label) ?: []);

        if ($label === '' || mb_strlen($label) > 60 || $wordCount > self::MAX_LABEL_WORDS
            || $this->containsIdentifier($label) || $sentiment === null
            || $evidence === '' || ! str_contains($normalizedText, $evidence)) {
            return null;
        }

        $context = trim((string) ($item['context'] ?? ''));
        $context = ($context === '' || $this->containsIdentifier($context))
            ? null
            : mb_substr($context, 0, self::MAX_CONTEXT_CHARS);

        return ['label' => $label, 'sentiment' => $sentiment, 'context' => $context];
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
     * @return list<array{role: string, content: string}>
     */
    private function messages(int $entityId, string $entityName, string $text): array
    {
        $known = $this->knownLabels($entityId);

        return [
            [
                'role' => 'system',
                'content' => "You extract what Indonesian netizens say about \"{$entityName}\" from ONE opinion. "
                    .'Return at most 5 themes, only about '.$entityName.' itself. '
                    .'label: a short Indonesian phrase, 2-6 words, lowercase, naming the specific thing AND the judgement '
                    .'(e.g. "baterai cepat habis", "kamera malam bagus", "cs lambat merespons", "harga seri a terjangkau"). '
                    .'Avoid a bare generic word like "bagus" or "mahal" when the opinion says what is good or expensive. '
                    .'If a known label below means the same thing, reuse that exact label. '
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
     * Labels already used for this entity first, then globally, so the LLM reuses
     * them instead of inventing near-duplicates (lazy clustering, docs/25).
     *
     * @return list<string>
     */
    private function knownLabels(int $entityId): array
    {
        $query = fn (?int $scopeEntityId) => Theme::query()
            ->join('theme_observations', 'themes.id', '=', 'theme_observations.theme_id')
            ->where('theme_observations.extractor', 'llm')
            ->when($scopeEntityId !== null, fn ($q) => $q->where('theme_observations.entity_id', $scopeEntityId))
            ->groupBy('themes.id', 'themes.display_label')
            ->orderByRaw('count(*) desc')
            ->limit(self::KNOWN_LABELS_PER_SCOPE)
            ->pluck('display_label');

        return array_values(array_unique(array_merge($query($entityId)->all(), $query(null)->all())));
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
                    'themes' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'label' => ['type' => 'string'],
                                'sentiment' => ['type' => 'string', 'enum' => array_column(SentimentClass::cases(), 'value')],
                                'evidence' => ['type' => 'string'],
                                'context' => ['type' => 'string'],
                            ],
                            'required' => ['label', 'sentiment', 'evidence', 'context'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['themes'],
                'additionalProperties' => false,
            ],
        ];
    }
}
