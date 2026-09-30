<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\LlmClient;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One-off maintenance command: per-opinion LLM extraction (themes:backfill /
 * ClassifySentimentJob's ExtractThemesJob) can mint near-duplicate labels for the
 * same underlying theme ("baterai boros" / "baterai cepat habis" / "baterai drop")
 * when each opinion is judged in isolation. This asks the LLM to group existing
 * LLM-extracted themes into synonyms and merges each group onto one canonical theme.
 *
 * Themes are a global dictionary (docs/25), not per-entity, so grouping runs once
 * over the whole table rather than per entity. Operator-invoked, not scheduled —
 * a handful of calls per run, so it does not go through the themes-llm queue
 * limiter the way per-opinion/per-batch jobs do.
 */
class ConsolidateThemesCommand extends Command
{
    private const CHUNK_SIZE = 150;

    private const REQUEST_TIMEOUT_SECONDS = 120;

    /**
     * @var string
     */
    protected $signature = 'themes:consolidate
        {--limit= : Max existing LLM themes to consider, highest observation count first (default: config(themes.consolidate_limit); 0 = all)}
        {--per-entity : Group the themes of each entity together instead of the global top-N, so synonyms of one entity never land in different chunks}
        {--by-name : No LLM: per entity, merge themes whose labels match once the entity name, its aliases and model-variant words (fe, plus, ultra...) are dropped ("harga s24 fe murah" -> "harga murah")}
        {--rebuild : Also run themes:rebuild-aggregates afterward so merged counts show up immediately}
        {--dry-run : Show what would be merged without writing anything}';

    /**
     * @var string
     */
    protected $description = 'Merge near-duplicate LLM-extracted theme labels (e.g. "baterai boros" / "baterai cepat habis") onto one canonical theme';

    public function handle(LlmClient $client): int
    {
        if (config('themes.extractor') !== 'llm') {
            $this->warn('THEMES_EXTRACTOR is not llm; nothing to consolidate.');

            return self::SUCCESS;
        }

        $limitOption = $this->option('limit');
        $limit = $limitOption !== null ? (int) $limitOption : (int) config('themes.consolidate_limit', 400);
        $dryRun = (bool) $this->option('dry-run');

        $merged = 0;
        $considered = 0;

        if ($this->option('by-name')) {
            [$merged, $considered] = $this->consolidateByName($dryRun);
        }

        foreach ($this->option('by-name') ? [] : $this->themeSets($limit) as $themes) {
            if ($themes->count() < 2) {
                continue;
            }

            $considered += $themes->count();

            try {
                $merged += $this->consolidate($client, $themes, $dryRun);
            } catch (HttpClientException $e) {
                $this->warn('Skipped a theme set after an LLM error: '.$e->getMessage());
            }
        }

        if ($considered === 0) {
            $this->info('Fewer than 2 LLM themes to compare; nothing to consolidate.');

            return self::SUCCESS;
        }

        $verb = $dryRun ? 'Would merge' : 'Merged';
        $this->info("{$verb} {$merged} duplicate theme(s) across {$considered} considered.");

        if (! $dryRun && $merged > 0 && $this->option('rebuild')) {
            $this->call('themes:rebuild-aggregates');
        }

        return self::SUCCESS;
    }

    /**
     * Deterministic pass, no LLM: within one entity, themes whose labels are equal once the entity's own
     * name/alias words and model-variant words are removed are the same theme ("harga s24 fe murah" and
     * "harga murah"). Needs a key of at least 2 words so a lone leftover word never merges unrelated themes.
     *
     * @return array{0: int, 1: int} merged and considered theme counts
     */
    private function consolidateByName(bool $dryRun): array
    {
        $merged = 0;
        $considered = 0;
        $variantWords = array_map('strval', (array) config('entity_matching.model_variant_suffixes', []));

        $entityIds = ThemeObservation::query()
            ->where('extractor', 'llm')
            ->groupBy('entity_id')
            ->havingRaw('count(*) >= ?', [(int) config('themes.min_entity_opinions', 30)])
            ->pluck('entity_id');

        foreach (Entity::query()->with('aliases')->whereIn('id', $entityIds)->get() as $entity) {
            $entityWords = collect([$entity->name, ...$entity->aliases->pluck('alias')->all()])
                ->flatMap(fn (string $text): array => explode(' ', TextNormalizer::normalize($text)))
                ->merge($variantWords)
                ->filter()
                ->unique()
                ->all();

            $themes = $this->llmThemes(fn ($q) => $q->where('entity_id', $entity->id))
                ->orderByDesc('observation_count')->orderBy('id')
                ->get(['id', 'slug', 'display_label']);
            $considered += $themes->count();

            $groups = $themes->groupBy(function (Theme $theme) use ($entityWords): string {
                $words = array_diff(explode(' ', TextNormalizer::normalize($theme->display_label)), $entityWords);

                return count($words) >= 2 ? implode(' ', $words) : 'skip:'.$theme->id;
            });

            foreach ($groups as $key => $members) {
                if ($members->count() < 2) {
                    continue;
                }

                $memberIds = array_values(array_map('intval', $members->pluck('id')->all()));

                if ($dryRun) {
                    $this->line("{$entity->name}: would merge [".implode(',', $memberIds)."] -> \"{$key}\"");
                    $merged += count($memberIds) - 1;

                    continue;
                }

                $exact = $members->first(fn (Theme $theme): bool => TextNormalizer::normalize($theme->display_label) === $key);
                $canonicalId = $exact !== null ? $exact->id : $this->resolveCanonical($themes, $memberIds, (string) $key);

                foreach ($memberIds as $memberId) {
                    if ($memberId !== $canonicalId) {
                        $this->mergeThemeInto($memberId, $canonicalId);
                        $merged++;
                    }
                }
            }
        }

        return [$merged, $considered];
    }

    /**
     * @return \Generator<int, Collection<int, Theme>>
     */
    private function themeSets(int $limit): \Generator
    {
        if (! $this->option('per-entity')) {
            $query = $this->llmThemes(fn ($q) => $q)->orderByDesc('observation_count')->orderBy('id');

            if ($limit > 0) {
                $query->limit($limit);
            }

            yield $query->get(['id', 'slug', 'display_label']);

            return;
        }

        $entityIds = ThemeObservation::query()
            ->where('extractor', 'llm')
            ->groupBy('entity_id')
            ->havingRaw('count(*) >= ?', [(int) config('themes.min_entity_opinions', 30)])
            ->pluck('entity_id');

        foreach ($entityIds as $entityId) {
            yield $this->llmThemes(fn ($q) => $q->where('entity_id', $entityId))
                ->orderByDesc('observation_count')->orderBy('id')
                ->get(['id', 'slug', 'display_label']);
        }
    }

    /**
     * @param  \Closure(Builder<ThemeObservation>): mixed  $scope
     * @return Builder<Theme>
     */
    private function llmThemes(\Closure $scope): Builder
    {
        return Theme::query()
            ->whereIn('id', ThemeObservation::query()->where('extractor', 'llm')->tap($scope)->select('theme_id'))
            ->withCount(['observations as observation_count' => fn ($q) => $q->where('extractor', 'llm')->tap($scope)]);
    }

    /**
     * Asks the LLM to group $themes and merges each group. Returns how many themes were merged away.
     *
     * @param  Collection<int, Theme>  $themes
     */
    private function consolidate(LlmClient $client, Collection $themes, bool $dryRun): int
    {
        $merged = 0;

        foreach ($themes->chunk(self::CHUNK_SIZE) as $chunk) {
            $response = $client->chat($this->messages($chunk), $this->schema(), self::REQUEST_TIMEOUT_SECONDS);

            foreach ((array) ($response['groups'] ?? []) as $group) {
                if (! is_array($group)) {
                    continue;
                }

                $memberIds = array_values(array_intersect(
                    array_map('intval', (array) ($group['member_ids'] ?? [])),
                    $chunk->pluck('id')->all()
                ));

                // A group can name a theme id that an EARLIER group in this same run
                // already merged away (the LLM can put one theme in two "synonym"
                // groups, and $chunk is a snapshot taken before any merge started) —
                // re-check against the live table so a stale id is dropped here rather
                // than surfacing as a foreign key violation deeper in mergeThemeInto().
                if ($memberIds !== []) {
                    $memberIds = array_values(array_map('intval', Theme::query()->whereIn('id', $memberIds)->pluck('id')->all()));
                }

                $memberIds = $this->dropOppositePolarity($memberIds, $chunk);
                $canonicalLabel = trim((string) ($group['canonical_label'] ?? ''));

                if (count($memberIds) < 2 || $canonicalLabel === '') {
                    continue;
                }

                if ($dryRun) {
                    $this->line('Would merge ['.implode(',', $memberIds).'] -> "'.$canonicalLabel.'"');
                    $merged += count($memberIds) - 1;

                    continue;
                }

                $canonicalId = $this->resolveCanonical($chunk, $memberIds, $canonicalLabel);

                foreach ($memberIds as $memberId) {
                    if ($memberId === $canonicalId) {
                        continue;
                    }

                    $this->mergeThemeInto($memberId, $canonicalId);
                    $merged++;
                }
            }
        }

        return $merged;
    }

    /**
     * The LLM sometimes groups opposites ("harga murah" with "harga mahal"). Keeps only the members whose
     * dominant sentiment does not oppose the largest member's; a mixed or neutral member fits either side.
     *
     * @param  list<int>  $memberIds
     * @param  Collection<int, Theme>  $chunk
     * @return list<int>
     */
    private function dropOppositePolarity(array $memberIds, Collection $chunk): array
    {
        if ($memberIds === []) {
            return [];
        }

        $side = [];
        $rows = ThemeObservation::query()->toBase()
            ->where('extractor', 'llm')
            ->whereIn('theme_id', $memberIds)
            ->selectRaw('theme_id, sentiment, count(*) as c')
            ->groupBy('theme_id', 'sentiment')
            ->get()
            ->groupBy('theme_id');

        foreach ($rows as $themeId => $themeRows) {
            $counts = $themeRows->pluck('c', 'sentiment');
            $total = (int) $counts->sum();
            $score = $total > 0 ? (((int) ($counts['positive'] ?? 0)) - ((int) ($counts['negative'] ?? 0))) / $total : 0;
            $side[(int) $themeId] = abs($score) > 0.3 ? ($score > 0 ? 1 : -1) : 0;
        }

        $anchorSide = 0;
        foreach ($chunk->whereIn('id', $memberIds)->sortByDesc('observation_count') as $theme) {
            if (($side[$theme->id] ?? 0) !== 0) {
                $anchorSide = $side[$theme->id];

                break;
            }
        }

        return array_values(array_filter(
            $memberIds,
            fn (int $id): bool => $anchorSide === 0 || ($side[$id] ?? 0) === 0 || $side[$id] === $anchorSide
        ));
    }

    /**
     * Renames the highest-observation-count member to the LLM's canonical label
     * (rather than creating a new theme row) and returns its id — every other
     * member in the group gets merged onto it.
     *
     * @param  Collection<int, Theme>  $chunk
     * @param  list<int>  $memberIds
     */
    private function resolveCanonical(Collection $chunk, array $memberIds, string $canonicalLabel): int
    {
        $best = $chunk->whereIn('id', $memberIds)->sortByDesc('observation_count')->first();
        $slug = Str::slug($canonicalLabel);

        if ($best === null) {
            return $memberIds[0];
        }

        $slugTaken = $slug !== '' && $slug !== $best->slug
            && Theme::query()->where('slug', $slug)->where('id', '!=', $best->id)->exists();

        if ($slug !== '' && $slug !== $best->slug && ! $slugTaken) {
            $best->update([
                'slug' => $slug,
                'display_label' => Str::ucfirst(mb_strtolower($canonicalLabel)),
                'canonical_key' => $slug,
            ]);
        }

        return $best->id;
    }

    /**
     * Reassigns every observation from $fromThemeId to $intoThemeId. A row that would
     * collide with the (entity_id, theme_id, source_item_id) unique constraint (both
     * themes already observed on the same opinion) is dropped rather than merged —
     * the canonical theme's own observation for that opinion already covers it.
     */
    private function mergeThemeInto(int $fromThemeId, int $intoThemeId): void
    {
        ThemeObservation::query()
            ->where('theme_id', $fromThemeId)
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows) use ($intoThemeId): void {
                foreach ($rows as $row) {
                    // A null source_item_id is never a duplicate of another null one — the
                    // (entity_id, theme_id, source_item_id) unique index treats NULLs as
                    // distinct (Postgres semantics), so only a real, matching source_item_id
                    // is a genuine collision worth dropping instead of moving.
                    $duplicate = $row->source_item_id !== null && ThemeObservation::query()
                        ->where('entity_id', $row->entity_id)
                        ->where('theme_id', $intoThemeId)
                        ->where('source_item_id', $row->source_item_id)
                        ->exists();

                    if ($duplicate) {
                        $row->delete();
                    } else {
                        $row->update(['theme_id' => $intoThemeId]);
                    }
                }
            });

        if (! ThemeObservation::query()->where('theme_id', $fromThemeId)->exists()) {
            Theme::query()->whereKey($fromThemeId)->delete();
        }
    }

    /**
     * @param  Collection<int, Theme>  $chunk
     * @return list<array{role: string, content: string}>
     */
    private function messages(Collection $chunk): array
    {
        $list = $chunk->map(fn (Theme $theme) => "{$theme->id}: {$theme->display_label} ({$theme->observation_count} opini)")->implode("\n");

        return [
            [
                'role' => 'system',
                'content' => 'You group Indonesian theme labels that describe the same underlying opinion into synonym '
                    .'groups, e.g. "baterai boros", "baterai cepat habis", "baterai drop" all belong in one group with '
                    .'canonical_label "baterai cepat habis". Only group labels that are genuinely the same concept and the '
                    .'same polarity — never group opposites (e.g. "kamera bagus" and "kamera buram" are opposite, not the '
                    .'same theme). Every group needs at least 2 member_ids. canonical_label should be the clearest, most '
                    .'natural Indonesian phrasing among the group — reuse one of the given labels verbatim when it already '
                    .'reads naturally, rather than inventing new wording. Omit any label that has no genuine synonym in this list.',
            ],
            [
                'role' => 'user',
                'content' => "Theme labels (id: label (count)):\n{$list}",
            ],
        ];
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    private function schema(): array
    {
        return [
            'name' => 'theme_consolidation',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'groups' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'canonical_label' => ['type' => 'string'],
                                'member_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            ],
                            'required' => ['canonical_label', 'member_ids'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['groups'],
                'additionalProperties' => false,
            ],
        ];
    }
}
