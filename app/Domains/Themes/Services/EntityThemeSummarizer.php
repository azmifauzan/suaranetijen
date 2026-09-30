<?php

namespace App\Domains\Themes\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\LlmClient;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\EntityThemeSummary;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Support\Facades\Log;

class EntityThemeSummarizer
{
    private const CONTEXTS_PER_THEME = 5;

    private const MAX_SUMMARY_CHARS = 800;

    private const MAX_NOTE_CHARS = 200;

    private const FRESH_DAYS = 7;

    private const MIN_NEW_OPINIONS = 10;

    public function __construct(
        private readonly LlmClient $client,
        private readonly TopThemesService $topThemes,
    ) {}

    public function summarize(Entity $entity): ?EntityThemeSummary
    {
        $data = $this->topThemes->getTopThemesForEntity($entity, Period::OneYear);
        if (! $data['has_enough_data'] || $data['top_themes'] === []) {
            return null;
        }

        $existing = EntityThemeSummary::query()->where('entity_id', $entity->id)->first();
        // Theme data can change without the opinion count moving at all — a backfill,
        // an extractor switch, or a themes:consolidate merge all rewrite theme_observations/
        // EntityThemeSnapshot without adding a single new sentiment opinion. Comparing
        // opinion_count alone missed exactly that case (confirmed live, 28 Sep 2026: a
        // consolidate run changed an entity's top themes but its summary, gated only on
        // opinion_count, stayed stale for two days). A summary is fresh only when its own
        // theme snapshot has not been recalculated more recently than the summary itself.
        $themesRecalculatedAt = EntityThemeSnapshot::query()
            ->where('entity_id', $entity->id)
            ->where('window', Period::OneYear->value)
            ->value('calculated_at');

        if ($existing !== null
            && $existing->generated_at->gt(now()->subDays(self::FRESH_DAYS))
            && abs($data['opinion_count'] - $existing->opinion_count) < self::MIN_NEW_OPINIONS
            && ($themesRecalculatedAt === null || $existing->generated_at->gte($themesRecalculatedAt))) {
            return $existing;
        }

        $themeIds = array_map(fn (array $t) => $t['id'], $data['top_themes']);
        $response = $this->client->chat($this->messages($entity, $data['top_themes']), $this->schema());

        $summary = trim((string) ($response['summary'] ?? ''));
        if (! PublicCopyGuard::isAllowed($summary, self::MAX_SUMMARY_CHARS)) {
            Log::warning('themes.summary_rejected', ['entity_id' => $entity->id]);

            return $existing;
        }

        $notes = [];
        foreach ((array) ($response['theme_notes'] ?? []) as $note) {
            $themeId = (int) (is_array($note) ? ($note['theme_id'] ?? 0) : 0);
            $text = trim((string) (is_array($note) ? ($note['note'] ?? '') : ''));
            if (in_array($themeId, $themeIds, true) && PublicCopyGuard::isAllowed($text, self::MAX_NOTE_CHARS)) {
                $notes[$themeId] = $text;
            }
        }

        return EntityThemeSummary::query()->updateOrCreate(
            ['entity_id' => $entity->id],
            [
                'summary' => $summary,
                'theme_notes' => $notes,
                'opinion_count' => $data['opinion_count'],
                'generated_at' => now(),
            ]
        );
    }

    /**
     * @param  array<int, array{id: int, display_label: string, observation_count: int, positive_count: int, negative_count: int}>  $topThemes
     * @return list<array{role: string, content: string}>
     */
    private function messages(Entity $entity, array $topThemes): array
    {
        $themeIds = array_map(fn (array $t) => $t['id'], $topThemes);

        // Sampled per theme so a low-volume theme still gets its own examples.
        $contexts = collect($themeIds)->mapWithKeys(fn (int $themeId) => [
            $themeId => ThemeObservation::query()
                ->where('entity_id', $entity->id)
                ->where('theme_id', $themeId)
                ->where('extractor', 'llm')
                ->whereNotNull('context')
                ->latest('id')
                ->limit(self::CONTEXTS_PER_THEME)
                ->pluck('context'),
        ]);

        $lines = array_map(function (array $t) use ($contexts): string {
            $examples = $contexts->get($t['id'])?->implode(' | ') ?? '';

            return "- [id {$t['id']}] {$t['display_label']}: {$t['observation_count']} opini "
                ."(positif {$t['positive_count']}, negatif {$t['negative_count']}). Contoh: {$examples}";
        }, $topThemes);

        return [
            [
                'role' => 'system',
                'content' => 'You write a short Indonesian summary of what netizens say about "'.$entity->name.'" '
                    .'using ONLY the theme data given. summary: 2-4 sentences, max 600 characters, written as reported '
                    .'opinion ("netizen banyak menyebut ...", "keluhan yang sering muncul ..."), never as fact. '
                    .'No percentages, no numbers except the opinion counts given, no superlatives such as "terbaik" or '
                    .'"terburuk", no claims absent from the data, no names of people or accounts, no mention of scores '
                    .'or ratings. theme_notes: for each theme id given, one sentence (max 20 words) explaining concretely '
                    .'what netizens mean by that theme, based on its examples.',
            ],
            [
                'role' => 'user',
                'content' => "Entity: {$entity->name}\nTop themes (last 12 months):\n".implode("\n", $lines),
            ],
        ];
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    private function schema(): array
    {
        return [
            'name' => 'entity_theme_summary',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'theme_notes' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'theme_id' => ['type' => 'integer'],
                                'note' => ['type' => 'string'],
                            ],
                            'required' => ['theme_id', 'note'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['summary', 'theme_notes'],
                'additionalProperties' => false,
            ],
        ];
    }
}
