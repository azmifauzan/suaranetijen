# Meaningful Top Suara Netijen Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the 10-word keyword theme dictionary with LLM-extracted specific theme phrases
("baterai cepat habis", "cs lambat merespons") and add a grounded per-entity "Ringkasan Suara
Netijen" plus one explanatory sentence per top theme, so an entity page tells a visitor *what*
netizens actually say.

**Architecture:** Theme extraction stays a separate branch after sentiment classification
(`ClassifySentimentJob` → `ExtractThemesJob`). A new `LlmThemeExtractor` (behind
`config('themes.extractor') === 'llm'`) turns each opinion into up to 5 specific theme phrases,
each grounded by an evidence span that must appear verbatim in the opinion. A short
paraphrased `context` is stored on each `theme_observations` row. It is our own derived text, not
the raw payload, and it is the only text that survives the 72-hour raw TTL. A daily job then
builds an `entity_theme_summaries` row per eligible entity from the top themes and those
contexts. Aggregation only counts observations from the active extractor, so keyword-era noise
drops out without deleting anything.

**Tech Stack:** Laravel 12 / PHP 8.4, Pest, Inertia v3 + Vue 3, the existing `LlmClient`
(OpenAI-compatible, no new dependency), PostgreSQL + Redis/Horizon (`analysis` and `aggregate`
queues).

**Spec:** `docs/25-top-suara-netijen.md`. Its "Normalization and clustering" section already
allows an LLM and says themes are *discovered from data*, not a fixed list. This plan implements
that part. Task 7 adds the summary and notes to `docs/25`.

## Global Constraints

- Never show a numeric score per theme. Show only frequency ("N opini") (ADR-008, `docs/25`).
- No unqualified percentage claims in any user-visible or LLM-generated copy (`docs/25` copy rules).
- Never "terbaik di Indonesia" or similar superlatives (CLAUDE.md, Search and SEO).
- Raw third-party text is never persisted past the adapter TTL (`raw_ttl_hours` 72). `context`
  must be a paraphrase of at most 200 chars, with no names, usernames, `@` handles or URLs.
- The three metrics are never merged. The summary never mentions Sentimen score or Rating Netijen.
- No new Composer or npm dependencies. All LLM calls go through `App\Domains\Entities\Services\LlmClient`.
- Default `THEMES_EXTRACTOR=keyword`, so the test suite and local dev never call a real LLM.
- Run `vendor/bin/pint --dirty --format agent` and `composer test` before each commit that touches PHP.

## Review Focus

1. An LLM label or context that contains a person's handle/URL (`@budi`, `https://…`) must be dropped, never stored. Test in Task 2.
2. An LLM theme whose `evidence` is not in the opinion (hallucination) must be dropped. Test in Task 2.
3. An LLM outage or missing API key while `extractor=llm` must fail the job so it retries and lands in Horizon. It must never silently fall back to keyword observations, and sentiment must not be affected. Test in Task 3.
4. Retrying the same opinion must not duplicate theme observations. Test in Task 3.
5. An LLM summary with `%`, "terbaik"/"terburuk", or notes for theme ids it was not given must not overwrite the previous summary. Test in Task 5.

---

## File map

| File | Change |
|---|---|
| `database/migrations/2026_09_25_000001_add_extractor_and_context_to_theme_observations.php` | new: `extractor` (default `keyword`), `context` |
| `config/themes.php` | add `extractor` key |
| `app/Domains/Themes/Models/ThemeObservation.php` | fillable + phpdoc |
| `app/Domains/Themes/Services/ThemeAggregator.php` | filter by active extractor, delete stale daily/snapshot rows |
| `app/Domains/Themes/Services/LlmThemeExtractor.php` | new |
| `app/Domains/Themes/Jobs/ExtractThemesJob.php` | choose extractor, retries |
| `app/Domains/Themes/Jobs/UpsertThemeObservationJob.php` | persist `extractor`, `context` |
| `app/Domains/Themes/Commands/RebuildThemeAggregatesCommand.php` | new `themes:rebuild-aggregates` |
| `database/migrations/2026_09_25_000002_create_entity_theme_summaries_table.php` | new |
| `app/Domains/Themes/Models/EntityThemeSummary.php` | new |
| `app/Domains/Themes/Services/EntityThemeSummarizer.php` | new |
| `app/Domains/Themes/Jobs/SummarizeEntityThemesJob.php` | new |
| `app/Domains/Themes/Commands/SummarizeThemesCommand.php` | new `themes:summarize` |
| `routes/console.php` | schedule `themes:summarize` |
| `app/Domains/Themes/Services/TopThemesService.php` | expose `summary`, per-theme `note` |
| `resources/js/pages/Entities/Show.vue` | render summary + notes |
| `docs/25-top-suara-netijen.md`, `CLAUDE.md` | docs |

Tests: `tests/Feature/Themes/ThemeExtractorModeTest.php`, `LlmThemeExtractorTest.php`,
`RebuildThemeAggregatesTest.php`, `EntityThemeSummarizerTest.php`, plus additions to
`tests/Feature/Entities/EntityShowWithScoreAndThemesTest.php`.

Commands: `BackfillThemesCommand` has no explicit registration (auto-discovered), so new commands in `app/Domains/Themes/Commands/` need none. If `php artisan list themes` doesn't show a new command, register it where `withCommands()` is configured in `bootstrap/app.php`.

---

### Task 1: Extractor column, context column, extractor-aware aggregation

**Files:**
- Create: `database/migrations/2026_09_25_000001_add_extractor_and_context_to_theme_observations.php`
- Modify: `config/themes.php`, `app/Domains/Themes/Models/ThemeObservation.php`, `app/Domains/Themes/Services/ThemeAggregator.php`
- Test: `tests/Feature/Themes/ThemeExtractorModeTest.php`; shared helper `themeModeFixture()` goes in `tests/Pest.php` (used by Tasks 1, 3, 4, 5)

**Interfaces:**
- Produces: `themeModeFixture(): array{0: Entity, 1: Source, 2: Theme (keyword "Murah"), 3: Theme (llm "Baterai cepat habis")}` test helper. `theme_observations.extractor` (`'keyword'|'llm'`), `theme_observations.context` (`?string`, ≤200). `config('themes.extractor')`. `ThemeAggregator::aggregateDaily()` / `aggregateSnapshot()` count only rows where `extractor = config('themes.extractor')` and delete rows for themes no longer present.

- [ ] **Step 1: Write failing test**

Put `themeModeFixture()` (with the `use` imports it needs) at the bottom of `tests/Pest.php`; the two tests go in `ThemeExtractorModeTest.php`.

```php
<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\Source;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\CarbonImmutable;

function themeModeFixture(): array
{
    $category = Category::create(['name' => 'Smartphone', 'slug' => 'smartphone', 'status' => CategoryStatus::Active]);
    $entity = Entity::create([
        'category_id' => $category->id, 'type' => EntityType::Brand, 'name' => 'Samsung', 'slug' => 'samsung',
        'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true,
    ]);
    $source = Source::create([
        'key' => 'dwh', 'name' => 'DWH', 'adapter' => 'DiskusiWebHostingAdapter',
        'source_type' => SourceType::Forum, 'enabled' => true, 'priority' => 10,
        'health_state' => SourceHealthState::Healthy,
    ]);
    $keyword = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'price_affordable']);
    $llm = Theme::create(['slug' => 'baterai-cepat-habis', 'display_label' => 'Baterai cepat habis', 'canonical_key' => 'baterai-cepat-habis']);

    return [$entity, $source, $keyword, $llm];
}

it('only aggregates observations from the active extractor', function () {
    [$entity, $source, $keyword, $llm] = themeModeFixture();
    config(['themes.extractor' => 'llm']);

    foreach ([$keyword, $llm] as $theme) {
        ThemeObservation::create([
            'entity_id' => $entity->id, 'theme_id' => $theme->id, 'source_id' => $source->id,
            'sentiment' => SentimentClass::Negative,
            'extractor' => $theme->is($llm) ? 'llm' : 'keyword',
        ]);
    }

    $aggregator = app(ThemeAggregator::class);
    $aggregator->aggregateDaily($entity->id, CarbonImmutable::now());
    $aggregator->aggregateSnapshot($entity->id, Period::OneYear);

    expect(EntityThemeDaily::pluck('theme_id')->all())->toBe([$llm->id])
        ->and(EntityThemeSnapshot::pluck('theme_id')->all())->toBe([$llm->id]);
});

it('deletes stale daily and snapshot rows for themes that no longer qualify', function () {
    [$entity, $source, $keyword] = themeModeFixture();
    config(['themes.extractor' => 'llm']);

    EntityThemeDaily::create([
        'entity_id' => $entity->id, 'theme_id' => $keyword->id, 'date' => now()->format('Y-m-d'),
        'positive_count' => 5, 'neutral_count' => 0, 'negative_count' => 0, 'observation_count' => 5,
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $entity->id, 'theme_id' => $keyword->id, 'window' => Period::OneYear,
        'observation_count' => 5, 'positive_count' => 5, 'neutral_count' => 0, 'negative_count' => 0,
        'rank' => 1, 'calculated_at' => now(),
    ]);

    $aggregator = app(ThemeAggregator::class);
    $aggregator->aggregateDaily($entity->id, CarbonImmutable::now());
    $aggregator->aggregateSnapshot($entity->id, Period::OneYear);

    expect(EntityThemeDaily::count())->toBe(0)
        ->and(EntityThemeSnapshot::count())->toBe(0);
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Themes/ThemeExtractorModeTest.php`
Expected: FAIL (unknown column `extractor`).

- [ ] **Step 3: Migration**

`php artisan make:migration add_extractor_and_context_to_theme_observations --table=theme_observations --no-interaction`, then rename the file to the name in the file map (or keep the generated name) and write:

```php
public function up(): void
{
    Schema::table('theme_observations', function (Blueprint $table) {
        $table->string('extractor', 16)->default('keyword')->after('confidence');
        $table->string('context', 200)->nullable()->after('extractor');
        $table->index(['entity_id', 'extractor']);
    });
}

public function down(): void
{
    Schema::table('theme_observations', function (Blueprint $table) {
        $table->dropIndex(['entity_id', 'extractor']);
        $table->dropColumn(['extractor', 'context']);
    });
}
```

- [ ] **Step 4: Config + model**

Add to `config/themes.php`, before `empty_state_message`:

```php
    /*
    |--------------------------------------------------------------------------
    | Theme Extractor
    |--------------------------------------------------------------------------
    |
    | 'keyword' matches the seeded dictionary (ThemeExtractor). 'llm' extracts
    | specific theme phrases per opinion via LlmClient (LlmThemeExtractor).
    | Aggregation only counts observations produced by the active extractor.
    |
    */
    'extractor' => env('THEMES_EXTRACTOR', 'keyword'),
```

`ThemeObservation`: add `'extractor'` and `'context'` to `$fillable`, plus `@property string $extractor` and `@property string|null $context` to the phpdoc block.

- [ ] **Step 5: Aggregator**

In `ThemeAggregator::aggregateDaily()` add `->where('extractor', $this->activeExtractor())` to the `ThemeObservation` query. After the `foreach ($byTheme …)` loop, before `return $created;`:

```php
        EntityThemeDaily::query()
            ->where('entity_id', $entityId)
            ->whereDate('date', $dateStr)
            ->whereNotIn('theme_id', array_keys($byTheme))
            ->delete();
```

In `aggregateSnapshot()`, add `->where('extractor', $this->activeExtractor())` to the `ThemeObservation` fallback query. After the snapshot `foreach`:

```php
        EntityThemeSnapshot::query()
            ->where('entity_id', $entityId)
            ->where('window', $period)
            ->whereNotIn('theme_id', $rows->pluck('theme_id')->map(fn ($id) => (int) $id)->all())
            ->delete();
```

Add:

```php
    private function activeExtractor(): string
    {
        return (string) config('themes.extractor', 'keyword');
    }
```

Note: the daily branch reads `entity_theme_daily`, which is already filtered because it is built by `aggregateDaily()`. Existing daily rows from before the switch are cleared by Task 4's rebuild command.

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact tests/Feature/Themes`
Expected: PASS (new and existing theme tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations config/themes.php app/Domains/Themes tests/Feature/Themes/ThemeExtractorModeTest.php
git commit -m "feat(themes): track extractor per observation and aggregate only the active one"
```

---

### Task 2: `LlmThemeExtractor`

**Files:**
- Create: `app/Domains/Themes/Services/LlmThemeExtractor.php`
- Test: `tests/Feature/Themes/LlmThemeExtractorTest.php`

**Interfaces:**
- Consumes: `LlmClient::chat(array $messages, ?array $jsonSchema): array`, `ThemeNormalizer::resolveTheme(string): ?Theme`, `ThemeNormalizer::normalize(string): string`, `TextNormalizer::normalize(string): string`.
- Produces: `LlmThemeExtractor::extract(int $entityId, string $entityName, string $text): list<array{theme: Theme, sentiment: SentimentClass, confidence: float, context: string|null}>`

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Services\LlmThemeExtractor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakeThemeLlm(array $themes): void
{
    LlmSetting::create([
        'base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k',
        'max_tokens' => 800, 'temperature' => 0.1, 'timeout_seconds' => 20,
    ]);
    Http::preventStrayRequests();
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['themes' => $themes])]]],
    ])]);
}

const OPINION = 'Samsung A55 kameranya bagus banget, tapi baterainya cepat habis sejak update kemarin.';

it('creates specific themes grounded in the opinion', function () {
    fakeThemeLlm([
        ['label' => 'kamera bagus', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus banget', 'context' => 'Pengguna puas dengan hasil kamera A55.'],
        ['label' => 'baterai cepat habis', 'sentiment' => 'negative', 'evidence' => 'baterainya cepat habis', 'context' => 'Baterai terasa boros setelah pembaruan sistem.'],
    ]);

    $result = app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION);

    expect($result)->toHaveCount(2)
        ->and($result[1]['theme']->display_label)->toBe('Baterai cepat habis')
        ->and($result[1]['sentiment'])->toBe(SentimentClass::Negative)
        ->and($result[1]['context'])->toBe('Baterai terasa boros setelah pembaruan sistem.')
        ->and(Theme::count())->toBe(2);
});

it('drops themes whose evidence is not in the opinion', function () {
    fakeThemeLlm([
        ['label' => 'layar retak', 'sentiment' => 'negative', 'evidence' => 'layarnya retak sendiri', 'context' => 'x'],
    ]);

    expect(app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION))->toBe([])
        ->and(Theme::count())->toBe(0);
});

it('drops labels with handles or urls and nulls contexts that contain them', function () {
    fakeThemeLlm([
        ['label' => '@budi kamera', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus', 'context' => 'ok'],
        ['label' => 'kamera bagus', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus', 'context' => 'kata @budi di https://x.com'],
    ]);

    $result = app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION);

    expect($result)->toHaveCount(1)
        ->and($result[0]['theme']->display_label)->toBe('Kamera bagus')
        ->and($result[0]['context'])->toBeNull();
});

it('reuses an existing theme instead of creating a duplicate', function () {
    $existing = Theme::create(['slug' => 'kamera-bagus', 'display_label' => 'Kamera bagus', 'canonical_key' => 'kamera-bagus']);
    fakeThemeLlm([
        ['label' => 'Kamera Bagus', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus', 'context' => 'ok'],
    ]);

    $result = app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION);

    expect($result[0]['theme']->id)->toBe($existing->id)
        ->and(Theme::count())->toBe(1);
});

it('truncates very long opinions before sending', function () {
    fakeThemeLlm([]);

    app(LlmThemeExtractor::class)->extract(1, 'Samsung', str_repeat('a ', 10_000));

    Http::assertSent(fn (Request $request) => mb_strlen($request['messages'][1]['content']) < 5_000);
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Themes/LlmThemeExtractorTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Domains\Themes\Services;

use App\Domains\Entities\Services\LlmClient;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeAlias;
use App\Domains\Themes\Models\ThemeObservation;
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
        $query = fn (?int $scopeEntityId) => ThemeObservation::query()
            ->join('themes', 'themes.id', '=', 'theme_observations.theme_id')
            ->where('theme_observations.extractor', 'llm')
            ->when($scopeEntityId !== null, fn ($q) => $q->where('theme_observations.entity_id', $scopeEntityId))
            ->groupBy('themes.display_label')
            ->orderByRaw('count(*) desc')
            ->limit(self::KNOWN_LABELS_PER_SCOPE)
            ->pluck('themes.display_label');

        return $query($entityId)->merge($query(null))->unique()->values()->all();
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
```

Check `SentimentClass` values are `positive|neutral|negative` (`app/Domains/Sentiment/Enums/SentimentClass.php`). If they differ, fix the test fixtures, not the enum.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Themes/LlmThemeExtractorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Themes/Services/LlmThemeExtractor.php tests/Feature/Themes/LlmThemeExtractorTest.php
git commit -m "feat(themes): add grounded LLM theme extractor"
```

---

### Task 3: Wire the extractor into the jobs

**Files:**
- Modify: `app/Domains/Themes/Jobs/ExtractThemesJob.php`, `app/Domains/Themes/Jobs/UpsertThemeObservationJob.php`
- Test: add to `tests/Feature/Themes/ThemeExtractorModeTest.php`

**Interfaces:**
- Consumes: `LlmThemeExtractor::extract()` (Task 2), `ThemeExtractor::extract()` (existing).
- Produces: `UpsertThemeObservationJob::__construct(..., ?CarbonInterface $publishedAt = null, string $extractor = 'keyword', ?string $context = null)`. `ExtractThemesJob` has `$tries = 3` and `backoff(): [30, 120]`.

- [ ] **Step 1: Write failing tests** (append to `ThemeExtractorModeTest.php`)

```php
use App\Domains\Themes\Jobs\ExtractThemesJob;
use App\Domains\Themes\Jobs\UpsertThemeObservationJob;
use App\Domains\Themes\Services\LlmThemeExtractor;
use App\Domains\Themes\Services\ThemeExtractor;
use Illuminate\Support\Facades\Queue;

it('uses the llm extractor and passes extractor + context when configured', function () {
    [$entity, $source, , $llmTheme] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    Queue::fake();

    $this->mock(LlmThemeExtractor::class)
        ->shouldReceive('extract')->once()->with($entity->id, 'Samsung', 'teks')
        ->andReturn([['theme' => $llmTheme, 'sentiment' => SentimentClass::Negative, 'confidence' => 0.8, 'context' => 'Baterai boros.']]);

    (new ExtractThemesJob(entityId: $entity->id, sourceId: $source->id, sourceItemId: null, text: 'teks'))
        ->handle(app(ThemeExtractor::class), app(LlmThemeExtractor::class));

    Queue::assertPushed(UpsertThemeObservationJob::class, fn ($job) => $job->extractor === 'llm'
        && $job->context === 'Baterai boros.' && $job->themeId === $llmTheme->id);
});

it('lets an llm failure bubble up so the job retries instead of falling back', function () {
    [$entity, $source] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    Queue::fake();

    $this->mock(LlmThemeExtractor::class)
        ->shouldReceive('extract')->andThrow(new RuntimeException('llm down'));

    expect(fn () => (new ExtractThemesJob(entityId: $entity->id, sourceId: $source->id, sourceItemId: null, text: 'teks'))
        ->handle(app(ThemeExtractor::class), app(LlmThemeExtractor::class)))
        ->toThrow(RuntimeException::class);

    Queue::assertNotPushed(UpsertThemeObservationJob::class);
});

it('does not duplicate observations when the same item is upserted twice', function () {
    [$entity, $source, , $llmTheme] = themeModeFixture();
    Queue::fake();
    $item = \App\Domains\Sources\Models\SourceItem::factory()->create(['source_id' => $source->id]); // SourceItemFactory exists

    foreach ([1, 2] as $attempt) {
        (new UpsertThemeObservationJob(
            entityId: $entity->id, themeId: $llmTheme->id, sourceId: $source->id, sourceItemId: $item->id,
            sourceDocumentHash: null, sentiment: SentimentClass::Negative, extractor: 'llm', context: 'Baterai boros.'
        ))->handle();
    }

    expect(ThemeObservation::where('extractor', 'llm')->count())->toBe(1);
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Themes/ThemeExtractorModeTest.php`
Expected: FAIL (unknown named parameter `extractor`, and `handle()` has the wrong arity).

- [ ] **Step 3: Implement `UpsertThemeObservationJob`**

Add two constructor params at the end:

```php
        public ?CarbonInterface $publishedAt = null,
        public string $extractor = 'keyword',
        public ?string $context = null,
```

Add `'extractor' => $this->extractor, 'context' => $this->context,` to both the `updateOrCreate` values array and the `create` array.

- [ ] **Step 4: Implement `ExtractThemesJob`**

```php
    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(ThemeExtractor $keywordExtractor, LlmThemeExtractor $llmExtractor): void
    {
        $useLlm = config('themes.extractor') === 'llm';

        if ($useLlm) {
            $entityName = Entity::query()->whereKey($this->entityId)->value('name');
            if (! is_string($entityName)) {
                return;
            }

            $extracted = $llmExtractor->extract($this->entityId, $entityName, $this->text);
        } else {
            $extracted = array_map(
                fn (array $item) => [...$item, 'context' => null],
                $keywordExtractor->extract($this->text, $this->contextSentiment)
            );
        }

        foreach ($extracted as $item) {
            UpsertThemeObservationJob::dispatch(
                entityId: $this->entityId,
                themeId: $item['theme']->id,
                sourceId: $this->sourceId,
                sourceItemId: $this->sourceItemId,
                sourceDocumentHash: $this->sourceDocumentHash,
                sentiment: $item['sentiment'],
                confidence: $item['confidence'],
                publishedAt: $this->publishedAt,
                extractor: $useLlm ? 'llm' : 'keyword',
                context: $item['context'],
            );
        }
    }
```

Add imports for `App\Domains\Entities\Models\Entity` and `App\Domains\Themes\Services\LlmThemeExtractor`. Update any existing test that calls `->handle($extractor)` with one argument (`grep -rn "ExtractThemesJob" tests`) to pass both extractors.

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact tests/Feature/Themes tests/Feature/ClassifySentimentJobTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Themes/Jobs tests/Feature/Themes
git commit -m "feat(themes): route theme extraction through the configured extractor"
```

---

### Task 4: `themes:rebuild-aggregates`

Rebuilds `entity_theme_daily` and `entity_theme_snapshots` from observations of the active extractor. Run it once after switching `THEMES_EXTRACTOR=llm`, so keyword-era counts ("Murah 144") disappear from pages.

**Files:**
- Create: `app/Domains/Themes/Commands/RebuildThemeAggregatesCommand.php`
- Test: `tests/Feature/Themes/RebuildThemeAggregatesTest.php`

**Interfaces:**
- Consumes: `ThemeAggregator::aggregateDaily(int, CarbonInterface)`, `ThemeAggregator::refreshAllSnapshots(int)` (Task 1 behavior).

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\ThemeObservation;

it('rebuilds aggregates from only the active extractor', function () {
    [$entity, $source, $keyword, $llm] = themeModeFixture();
    config(['themes.extractor' => 'llm']);

    EntityThemeDaily::create([
        'entity_id' => $entity->id, 'theme_id' => $keyword->id, 'date' => now()->format('Y-m-d'),
        'positive_count' => 144, 'neutral_count' => 0, 'negative_count' => 0, 'observation_count' => 144,
    ]);
    ThemeObservation::create([
        'entity_id' => $entity->id, 'theme_id' => $llm->id, 'source_id' => $source->id,
        'sentiment' => SentimentClass::Negative, 'extractor' => 'llm',
    ]);

    $this->artisan('themes:rebuild-aggregates')->assertSuccessful();

    expect(EntityThemeDaily::pluck('theme_id')->all())->toBe([$llm->id])
        ->and(EntityThemeSnapshot::where('window', Period::OneYear)->pluck('theme_id')->all())->toBe([$llm->id]);
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Themes/RebuildThemeAggregatesTest.php`
Expected: FAIL (command not found).

- [ ] **Step 3: Implement**

```php
<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RebuildThemeAggregatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'themes:rebuild-aggregates';

    /**
     * @var string
     */
    protected $description = 'Wipe and rebuild entity_theme_daily/entity_theme_snapshots from observations of the active extractor (run once after switching THEMES_EXTRACTOR)';

    public function handle(ThemeAggregator $aggregator): int
    {
        EntityThemeDaily::query()->delete();
        EntityThemeSnapshot::query()->delete();

        $days = ThemeObservation::query()
            ->where('extractor', (string) config('themes.extractor', 'keyword'))
            ->selectRaw('entity_id, date(created_at) as day')
            ->distinct()
            ->get();

        foreach ($days as $row) {
            $aggregator->aggregateDaily((int) $row->entity_id, CarbonImmutable::parse((string) $row->day));
        }

        $entityIds = $days->pluck('entity_id')->unique();
        foreach ($entityIds as $entityId) {
            $aggregator->refreshAllSnapshots((int) $entityId);
        }

        $this->info("Rebuilt theme aggregates for {$entityIds->count()} entities ({$days->count()} entity-days).");

        return self::SUCCESS;
    }
}
```

Confirm it appears in `php artisan list themes` (see the file map note).

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/Themes`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Themes/Commands tests
git commit -m "feat(themes): add themes:rebuild-aggregates for extractor switch"
```

---

### Task 5: Ringkasan Suara Netijen (entity summary + per-theme notes)

**Files:**
- Create: `database/migrations/2026_09_25_000002_create_entity_theme_summaries_table.php`, `app/Domains/Themes/Models/EntityThemeSummary.php`, `app/Domains/Themes/Services/EntityThemeSummarizer.php`, `app/Domains/Themes/Jobs/SummarizeEntityThemesJob.php`, `app/Domains/Themes/Commands/SummarizeThemesCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Themes/EntityThemeSummarizerTest.php`

**Interfaces:**
- Consumes: `TopThemesService::getTopThemesForEntity(Entity, Period::OneYear)` (existing shape: `has_enough_data`, `opinion_count`, `top_themes[{id, display_label, observation_count, positive_count, neutral_count, negative_count}]`), `LlmClient::chat()`.
- Produces: `EntityThemeSummary` (`entity_id` unique, `summary` text, `theme_notes` array<int,string> keyed by theme id, `opinion_count` int, `generated_at` datetime). `EntityThemeSummarizer::summarize(Entity): ?EntityThemeSummary`. Artisan command `themes:summarize`.

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\EntityThemeSummary;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\EntityThemeSummarizer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function summaryFixture(): array
{
    [$entity, $source, , $llm] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    SentimentSnapshot::create([
        'entity_id' => $entity->id, 'period' => Period::OneYear->value, 'positive_count' => 20,
        'neutral_count' => 5, 'negative_count' => 15, 'opinion_count' => 40, 'score' => 56.25,
        'sentiment_model_version' => 'v1', 'score_formula_version' => 'v1', 'calculated_at' => now(),
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $entity->id, 'theme_id' => $llm->id, 'window' => Period::OneYear,
        'observation_count' => 12, 'positive_count' => 1, 'neutral_count' => 0, 'negative_count' => 11,
        'rank' => 1, 'calculated_at' => now(),
    ]);
    ThemeObservation::create([
        'entity_id' => $entity->id, 'theme_id' => $llm->id, 'source_id' => $source->id,
        'sentiment' => SentimentClass::Negative, 'extractor' => 'llm',
        'context' => 'Baterai terasa boros setelah pembaruan sistem.',
    ]);
    LlmSetting::create([
        'base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k',
        'max_tokens' => 800, 'temperature' => 0.2, 'timeout_seconds' => 30,
    ]);

    return [$entity, $llm];
}

function fakeSummaryLlm(array $payload): void
{
    Http::preventStrayRequests();
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode($payload)]]],
    ])]);
}

it('stores a grounded summary and notes for provided themes only', function () {
    [$entity, $llm] = summaryFixture();
    fakeSummaryLlm([
        'summary' => 'Netizen paling sering mengeluhkan baterai yang cepat habis setelah pembaruan sistem.',
        'theme_notes' => [
            ['theme_id' => $llm->id, 'note' => 'Keluhan baterai boros muncul setelah update.'],
            ['theme_id' => 99999, 'note' => 'Tema karangan.'],
        ],
    ]);

    $summary = app(EntityThemeSummarizer::class)->summarize($entity);

    expect($summary->summary)->toContain('baterai')
        ->and($summary->theme_notes)->toBe([$llm->id => 'Keluhan baterai boros muncul setelah update.'])
        ->and($summary->opinion_count)->toBe(40);
    Http::assertSent(fn (Request $r) => str_contains($r['messages'][1]['content'], 'Baterai terasa boros'));
});

it('keeps the previous summary when the new one breaks copy rules', function (string $bad) {
    [$entity] = summaryFixture();
    $previous = EntityThemeSummary::create([
        'entity_id' => $entity->id, 'summary' => 'Ringkasan lama.', 'theme_notes' => [],
        'opinion_count' => 1, 'generated_at' => now()->subDays(10),
    ]);
    fakeSummaryLlm(['summary' => $bad, 'theme_notes' => []]);

    app(EntityThemeSummarizer::class)->summarize($entity);

    expect($previous->fresh()->summary)->toBe('Ringkasan lama.');
})->with([
    '73% netizen bilang baterai boros.',
    'Samsung adalah HP terbaik di Indonesia.',
    '',
]);

it('skips regeneration when the summary is fresh and opinion count barely moved', function () {
    [$entity] = summaryFixture();
    EntityThemeSummary::create([
        'entity_id' => $entity->id, 'summary' => 'Masih segar.', 'theme_notes' => [],
        'opinion_count' => 38, 'generated_at' => now()->subDay(),
    ]);
    Http::preventStrayRequests();
    Http::fake();

    app(EntityThemeSummarizer::class)->summarize($entity);

    Http::assertNothingSent();
});

it('returns null below the theme threshold', function () {
    [$entity] = summaryFixture();
    SentimentSnapshot::query()->update(['opinion_count' => 5]);
    Http::preventStrayRequests();
    Http::fake();

    expect(app(EntityThemeSummarizer::class)->summarize($entity))->toBeNull();
});
```

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Themes/EntityThemeSummarizerTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Migration + model**

`php artisan make:migration create_entity_theme_summaries_table --no-interaction`:

```php
public function up(): void
{
    Schema::create('entity_theme_summaries', function (Blueprint $table) {
        $table->id();
        $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
        $table->text('summary');
        $table->json('theme_notes');
        $table->unsignedInteger('opinion_count');
        $table->timestamp('generated_at');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('entity_theme_summaries');
}
```

`php artisan make:model EntityThemeSummary --no-interaction`, then move the file to `app/Domains/Themes/Models/EntityThemeSummary.php` (same namespace pattern as `EntityThemeSnapshot`):

```php
<?php

namespace App\Domains\Themes\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * LLM-written, grounded summary of an entity's top themes (365d window). Derived
 * text only — built from theme counts and paraphrased contexts, never raw payloads.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $summary
 * @property array<int, string> $theme_notes
 * @property int $opinion_count
 * @property Carbon $generated_at
 */
class EntityThemeSummary extends Model
{
    protected $fillable = ['entity_id', 'summary', 'theme_notes', 'opinion_count', 'generated_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme_notes' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 4: Summarizer**

```php
<?php

namespace App\Domains\Themes\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\LlmClient;
use App\Domains\Sentiment\Enums\Period;
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
        if ($existing !== null
            && $existing->generated_at->gt(now()->subDays(self::FRESH_DAYS))
            && abs($data['opinion_count'] - $existing->opinion_count) < self::MIN_NEW_OPINIONS) {
            return $existing;
        }

        $themeIds = array_map(fn (array $t) => $t['id'], $data['top_themes']);
        $response = $this->client->chat($this->messages($entity, $data['top_themes']), $this->schema());

        $summary = trim((string) ($response['summary'] ?? ''));
        if (! $this->isAllowedCopy($summary, self::MAX_SUMMARY_CHARS)) {
            Log::warning('themes.summary_rejected', ['entity_id' => $entity->id]);

            return $existing;
        }

        $notes = [];
        foreach ((array) ($response['theme_notes'] ?? []) as $note) {
            $themeId = (int) (is_array($note) ? ($note['theme_id'] ?? 0) : 0);
            $text = trim((string) (is_array($note) ? ($note['note'] ?? '') : ''));
            if (in_array($themeId, $themeIds, true) && $this->isAllowedCopy($text, self::MAX_NOTE_CHARS)) {
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
     * docs/25 copy rules + CLAUDE.md SEO rules: no percentages, no superlatives, no handles/links.
     */
    private function isAllowedCopy(string $text, int $maxChars): bool
    {
        return $text !== ''
            && mb_strlen($text) <= $maxChars
            && ! str_contains($text, '%')
            && ! preg_match('/\b(terbaik|terburuk)\b|@\w|https?:\/\//iu', $text);
    }

    /**
     * @param  array<int, array{id: int, display_label: string, observation_count: int, positive_count: int, negative_count: int}>  $topThemes
     * @return list<array{role: string, content: string}>
     */
    private function messages(Entity $entity, array $topThemes): array
    {
        $themeIds = array_map(fn (array $t) => $t['id'], $topThemes);

        $contexts = ThemeObservation::query()
            ->where('entity_id', $entity->id)
            ->whereIn('theme_id', $themeIds)
            ->where('extractor', 'llm')
            ->whereNotNull('context')
            ->latest('id')
            ->limit(count($themeIds) * self::CONTEXTS_PER_THEME * 4)
            ->get(['theme_id', 'context'])
            ->groupBy('theme_id');

        $lines = array_map(function (array $t) use ($contexts): string {
            $examples = $contexts->get($t['id'])?->take(self::CONTEXTS_PER_THEME)->pluck('context')->implode(' | ') ?? '';

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
```

- [ ] **Step 5: Job + command + schedule**

`app/Domains/Themes/Jobs/SummarizeEntityThemesJob.php`:

```php
<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Themes\Services\EntityThemeSummarizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SummarizeEntityThemesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $entityId)
    {
        $this->onQueue('aggregate');
    }

    public function handle(EntityThemeSummarizer $summarizer): void
    {
        $entity = Entity::query()->find($this->entityId);
        if ($entity !== null) {
            $summarizer->summarize($entity);
        }
    }
}
```

`app/Domains/Themes/Commands/SummarizeThemesCommand.php`:

```php
<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Themes\Jobs\SummarizeEntityThemesJob;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use Illuminate\Console\Command;

class SummarizeThemesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'themes:summarize';

    /**
     * @var string
     */
    protected $description = 'Queue Ringkasan Suara Netijen regeneration for every entity with theme snapshots (skips fresh ones)';

    public function handle(): int
    {
        if (config('themes.extractor') !== 'llm') {
            $this->warn('THEMES_EXTRACTOR is not llm; summaries need LLM-extracted contexts. Skipping.');

            return self::SUCCESS;
        }

        $entityIds = EntityThemeSnapshot::query()->distinct()->pluck('entity_id');
        $entityIds->each(fn (int $id) => SummarizeEntityThemesJob::dispatch($id));

        $this->info("Queued {$entityIds->count()} summary job(s).");

        return self::SUCCESS;
    }
}
```

`routes/console.php`, next to the other schedules:

```php
Schedule::command('themes:summarize')->dailyAt('03:30')->withoutOverlapping();
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact tests/Feature/Themes`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Domains/Themes routes/console.php tests/Feature/Themes
git commit -m "feat(themes): add grounded Ringkasan Suara Netijen per entity"
```

---

### Task 6: Show summary and theme notes on the entity page

Before starting: CLAUDE.md asks to check with the user whether antislop runs during or after UI/copy work. Read `.ai/rules/js.md` and activate `inertia-vue-development` + `tailwindcss-development`.

**Files:**
- Modify: `app/Domains/Themes/Services/TopThemesService.php`, `resources/js/pages/Entities/Show.vue`
- Test: `tests/Feature/Entities/EntityShowWithScoreAndThemesTest.php`

**Interfaces:**
- Consumes: `EntityThemeSummary` (Task 5).
- Produces: `themes.summary: {text: string, opinion_count: int, generated_at: string (ISO)} | null`. Every `themes.top_themes[]` item gets `note: string|null`. Both only appear when `period === Period::OneYear`. Otherwise `summary` is `null` and notes are `null`.

- [ ] **Step 1: Write failing tests** (append to `EntityShowWithScoreAndThemesTest.php`, reuse that file's entity/theme setup. Extract it into a local helper if needed)

```php
test('entity page exposes the theme summary and per-theme notes for the default period', function () {
    // ...same Category/Entity/SentimentSnapshot/Theme/EntityThemeDaily setup as the test above...
    \App\Domains\Themes\Models\EntityThemeSummary::create([
        'entity_id' => $entity->id,
        'summary' => 'Netizen banyak memuji kecepatan server.',
        'theme_notes' => [$themeCepat->id => 'Halaman dan panel terasa responsif.'],
        'opinion_count' => 100,
        'generated_at' => now(),
    ]);

    $this->get("/e/{$entity->slug}")->assertInertia(fn ($page) => $page
        ->where('themes.summary.text', 'Netizen banyak memuji kecepatan server.')
        ->where('themes.summary.opinion_count', 100)
        ->where('themes.top_themes.0.note', 'Halaman dan panel terasa responsif.')
        ->where('themes.top_themes.1.note', null));
});

test('theme summary is hidden for non-default periods', function () {
    // ...same setup, plus a SentimentSnapshot + EntityThemeDaily valid for 30d, and the summary row...

    $this->get("/e/{$entity->slug}?period=30d")->assertInertia(fn ($page) => $page
        ->where('themes.summary', null));
});
```

Check how the controller reads the period query param (`grep -n "period" app/Domains/Entities/Controllers/EntityShowController.php`) and use the same parameter name in the second test.

- [ ] **Step 2: Run to confirm failure**

Run: `php artisan test --compact tests/Feature/Entities/EntityShowWithScoreAndThemesTest.php`
Expected: FAIL (`themes.summary` missing).

- [ ] **Step 3: Implement in `TopThemesService::getTopThemesForEntity()`**

- Add `'summary' => null` to both early-return arrays.
- Before the final return:

```php
        $summaryRow = $period === Period::OneYear
            ? EntityThemeSummary::query()->where('entity_id', $entity->id)->first()
            : null;
        $notes = $summaryRow?->theme_notes ?? [];

        $topThemes = array_map(fn (array $row) => [...$row, 'note' => $notes[$row['id']] ?? null], $topThemes);
```

- Add `'summary' => $summaryRow === null ? null : ['text' => $summaryRow->summary, 'opinion_count' => $summaryRow->opinion_count, 'generated_at' => $summaryRow->generated_at->toIso8601String()],` to the final return array.
- Update the PHPDoc return shape: add `summary: array{text: string, opinion_count: int, generated_at: string}|null` and `note: string|null` inside the `top_themes` item shape.

- [ ] **Step 4: Implement in `Show.vue`**

TypeScript: add `note?: string | null` to `ThemeItem`, and `summary: { text: string; opinion_count: number; generated_at: string } | null` to `ThemesData`.

Inside `<div v-if="themes.has_enough_data" class="mt-6 space-y-6">`, before the "Top 5 Tema" block:

```vue
                    <div
                        v-if="themes.summary"
                        class="rounded-xl border border-neutral-200 bg-neutral-50 p-4"
                    >
                        <h3 class="text-sm font-semibold text-neutral-900">
                            Ringkasan Suara Netijen
                        </h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-neutral-700">
                            {{ themes.summary.text }}
                        </p>
                        <p class="mt-2 text-xs text-neutral-500">
                            Diringkas otomatis dari tema
                            {{ themes.summary.opinion_count }} opini netizen,
                            diperbarui
                            {{
                                new Date(
                                    themes.summary.generated_at,
                                ).toLocaleDateString('id-ID', {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                })
                            }}. Bisa kurang tepat.
                        </p>
                    </div>
```

In the Top 5 row, the row `div` is `flex items-center justify-between`. Change it to `flex items-start justify-between gap-3`. Wrap the label `span` and a new note line in a `div`:

```vue
                                    <div class="min-w-0">
                                        <span
                                            class="text-sm font-semibold text-neutral-800"
                                        >
                                            {{ theme.display_label }}
                                        </span>
                                        <p
                                            v-if="theme.note"
                                            class="mt-0.5 text-xs leading-snug text-neutral-600"
                                        >
                                            {{ theme.note }}
                                        </p>
                                    </div>
```

Add `shrink-0` to the count `span`. Update the card subtitle copy to: "Hal yang paling sering dibahas netizen tentang entitas ini, diurutkan berdasarkan jumlah opini."

- [ ] **Step 5: Run tests + build**

Run: `php artisan test --compact tests/Feature/Entities tests/Feature/Themes && npm run build`
Expected: PASS, build succeeds. Visually check `/e/<slug>` at 360px and at desktop width (local `composer run dev`).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Themes/Services/TopThemesService.php resources/js/pages/Entities/Show.vue tests/Feature/Entities
git commit -m "feat(entities): show Ringkasan Suara Netijen and theme notes"
```

---

### Task 7: Docs, full gate, staging rollout

**Files:**
- Modify: `docs/25-top-suara-netijen.md`, `CLAUDE.md`

- [ ] **Step 1: `docs/25`.** Add a section "LLM extraction and Ringkasan (25 Sep 2026)" describing:
  - specific phrases with evidence grounding;
  - `context` as the only persisted text (paraphrase, ≤200 chars, no identifiers);
  - aggregation per active extractor;
  - the daily summary with its copy-rule validation;
  - the "Diringkas otomatis … Bisa kurang tepat" label.

  Also state that summary and notes are explanatory text, not a score, so ADR-008 is unchanged.
- [ ] **Step 2: `CLAUDE.md`.** Add a short dated entry in the same style as the other sections, plus a row in the implementation boundary table.
- [ ] **Step 3: Full gate.** Run `composer test` (Pest + Pint + phpstan per repo setup) and `npm run build`. All must pass.
- [ ] **Step 4: Commit**

```bash
git add docs/25-top-suara-netijen.md CLAUDE.md
git commit -m "docs(themes): document LLM theme extraction and Ringkasan Suara Netijen"
```

- [ ] **Step 5: Staging rollout (operator-confirmed, not automated).** Follow the documented redeploy sequence:
  1. Build and push the image.
  2. `horizon:terminate` on the workers.
  3. Pull and `--force-recreate` the main host and all 3 worker hosts. Worker hosts need `THEMES_EXTRACTOR=llm` in their `.env` too, because they run `ExtractThemesJob`.
  4. `docker exec nginx-proxy nginx -s reload`.
  5. `php artisan migrate --force`.
  6. Set `THEMES_EXTRACTOR=llm` in the main host `.env` and recreate.
  7. `php artisan themes:backfill`. This only reaches opinions whose raw payload is still inside the 72h TTL.
  8. Once the `analysis` queue drains, run `php artisan themes:rebuild-aggregates`.
  9. `php artisan themes:summarize`.
  10. Check `/e/samsung`: specific themes are shown, the summary is present, and there are no new `failed_jobs` from `ExtractThemesJob`.

---

## Self-review notes

- Spec coverage: "themes discovered from data" is Tasks 2–3. "LLM fallback for clustering" is the known-label reuse in Task 2. Frequency-only display is unchanged, since notes are text and not scores. The threshold is reused (Task 5 depends on `has_enough_data`). The copy rules are enforced in code (Task 5 `isAllowedCopy`).
- Known ceilings, deliberately accepted:
  - Clustering is "reuse a known label" only. Near-duplicate themes are possible ("baterai boros" vs "baterai cepat habis"). They get split counts and never merge. Upgrade path: an admin merge UI or an embedding pass once duplicates show up in data.
  - History before rollout is lost beyond the 72h raw TTL. Pages start from the rollout date.
  - `aggregateDaily` buckets by observation `created_at`, not `published_at`. That is existing behavior and unchanged here.
- Cost: one LLM call per relevant opinion (`analysis` queue) plus one per eligible entity per day at most (skipped when fresh). Watch the `analysis` queue depth after rollout. The LLM timeout is 30s and there are 7 analysis workers across hosts.
