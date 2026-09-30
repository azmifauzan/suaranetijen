<?php

namespace App\Domains\Search\Services;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Services\LlmClient;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Services\PublicCopyGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TopicDraftWriter
{
    private const MAX_CANDIDATE_THEMES = 30;

    private const MAX_TITLE_CHARS = 60;

    private const MAX_META_CHARS = 155;

    private const MAX_INTRO_CHARS = 1000;

    public function __construct(
        protected LlmClient $client
    ) {}

    /**
     * Draft title, meta, intro, category, and themes for a topic candidate using LLM.
     */
    public function draft(SearchLandingPage $topic): SearchLandingPageStatus
    {
        // A live page is never rewritten or rejected by the LLM; unpublish it first.
        if ($topic->status === SearchLandingPageStatus::Published) {
            return $topic->status;
        }

        // 1. Gather valid categories (leaf categories excluding Tokoh Publik)
        $categories = Category::query()
            ->active()
            ->whereDoesntHave('children')
            ->excludePublicFigure()
            ->get(['id', 'name', 'slug']);

        if ($categories->isEmpty()) {
            return $topic->status;
        }

        $validCategoryIds = $categories->pluck('id')->all();

        // 2. Gather candidate themes (up to 30)
        $candidateThemes = $this->resolveCandidateThemes($topic);
        $validThemeIds = $candidateThemes->pluck('id')->all();

        // 3. Prepare LLM prompt and schema
        $messages = $this->buildMessages($topic, $categories, $candidateThemes);
        $schema = $this->buildSchema();

        try {
            $response = $this->client->chat($messages, $schema);
        } catch (Throwable $e) {
            Log::warning('landing_pages.llm_chat_failed', [
                'topic_id' => $topic->id,
                'keyword' => $topic->keyword,
                'error' => $e->getMessage(),
            ]);

            return $topic->status;
        }

        // 4. Check relevance
        $isRelevant = (bool) ($response['is_relevant'] ?? false);
        if (! $isRelevant) {
            $topic->update([
                'status' => SearchLandingPageStatus::Rejected,
                'llm_drafted_at' => now(),
            ]);

            return SearchLandingPageStatus::Rejected;
        }

        // 5. Validate category_id
        $categoryId = (int) ($response['category_id'] ?? 0);
        if (! in_array($categoryId, $validCategoryIds, true)) {
            Log::warning('landing_pages.invalid_category_from_llm', [
                'topic_id' => $topic->id,
                'category_id' => $categoryId,
            ]);

            return $topic->status;
        }

        // 6. Validate theme_ids
        $themeIds = array_map('intval', (array) ($response['theme_ids'] ?? []));
        $validSelectedThemeIds = array_intersect($themeIds, $validThemeIds);

        if (empty($validSelectedThemeIds)) {
            Log::warning('landing_pages.invalid_themes_from_llm', [
                'topic_id' => $topic->id,
                'theme_ids' => $themeIds,
            ]);

            return $topic->status;
        }

        // 7. Validate text fields and copy guard
        $title = trim((string) ($response['title'] ?? ''));
        $metaDescription = trim((string) ($response['meta_description'] ?? ''));
        $intro = trim((string) ($response['intro'] ?? ''));

        if (! PublicCopyGuard::isAllowed($title, self::MAX_TITLE_CHARS)
            || ! PublicCopyGuard::isAllowed($metaDescription, self::MAX_META_CHARS)
            || ! PublicCopyGuard::isAllowed($intro, self::MAX_INTRO_CHARS)) {
            Log::warning('landing_pages.copy_guard_failed', [
                'topic_id' => $topic->id,
                'title' => $title,
                'meta' => $metaDescription,
            ]);

            return $topic->status;
        }

        // 8. Save draft
        $topic->update([
            'category_id' => $categoryId,
            'title' => $title,
            'meta_description' => $metaDescription,
            'intro' => $intro,
            'status' => SearchLandingPageStatus::Draft,
            'llm_drafted_at' => now(),
        ]);

        $topic->themes()->sync($validSelectedThemeIds);

        return SearchLandingPageStatus::Draft;
    }

    /**
     * Themes already attached to the topic always come first, then keyword matches
     * ranked by how often netizens mention them, capped at MAX_CANDIDATE_THEMES.
     *
     * @return Collection<int, Theme>
     */
    private function resolveCandidateThemes(SearchLandingPage $topic): Collection
    {
        $preset = $topic->themes()->get();

        $tokens = array_filter(
            explode(' ', TextNormalizer::normalize($topic->keyword)),
            fn (string $w) => mb_strlen($w) >= 2
        );

        $query = Theme::query()
            ->withSum('snapshots as mention_total', 'observation_count')
            ->whereNotIn('id', $preset->pluck('id'))
            ->orderByDesc('mention_total');

        if (! empty($tokens)) {
            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('display_label', 'like', "%{$token}%")
                        ->orWhere('canonical_key', 'like', "%{$token}%");
                }
            });
        }

        $matched = $query->limit(max(1, self::MAX_CANDIDATE_THEMES - $preset->count()))->get();

        if ($preset->isEmpty() && $matched->isEmpty()) {
            $matched = Theme::query()
                ->withSum('snapshots as mention_total', 'observation_count')
                ->orderByDesc('mention_total')
                ->limit(self::MAX_CANDIDATE_THEMES)
                ->get();
        }

        return $preset->concat($matched)->values();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Theme>  $themes
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(
        SearchLandingPage $topic,
        Collection $categories,
        Collection $themes
    ): array {
        $categoryLines = $categories->map(fn (Category $c) => "- [id: {$c->id}] {$c->name}")->implode("\n");
        $themeLines = $themes->map(fn (Theme $t) => "- [id: {$t->id}] {$t->display_label}")->implode("\n");

        return [
            [
                'role' => 'system',
                'content' => 'You are an SEO topic curator for SuaraNetijen, an Indonesian consumer sentiment platform. '
                    .'Evaluate if the keyword is a valid topic for consumer brands/products/services. '
                    .'STRICT RULES: '
                    .'1. is_relevant: false if keyword is political figure, personal name, porn/gambling spam, vulgar, or meaningless. '
                    .'2. category_id: MUST BE picked from the provided Available Categories list by ID. '
                    .'3. theme_ids: MUST BE non-empty subset of the provided Available Themes list by ID. '
                    .'4. title: Max 60 chars. No superlatives like "terbaik" or "terburuk". No percent signs. Descriptive in Indonesian. '
                    .'5. meta_description: Max 155 chars. Summary of what netizens discuss, no superlatives, no percent signs. '
                    .'6. intro: 2-3 short paragraphs (max 1000 chars total) introducing the topic neutrally. No percentages, no superlatives, no handles, no URLs.',
            ],
            [
                'role' => 'user',
                'content' => "Topic Keyword: {$topic->keyword}\n\n"
                    ."Available Categories:\n{$categoryLines}\n\n"
                    ."Available Themes:\n{$themeLines}",
            ],
        ];
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    private function buildSchema(): array
    {
        return [
            'name' => 'topic_landing_page_draft',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'is_relevant' => ['type' => 'boolean'],
                    'category_id' => ['type' => 'integer'],
                    'theme_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                    'title' => ['type' => 'string'],
                    'meta_description' => ['type' => 'string'],
                    'intro' => ['type' => 'string'],
                ],
                'required' => ['is_relevant', 'category_id', 'theme_ids', 'title', 'meta_description', 'intro'],
                'additionalProperties' => false,
            ],
        ];
    }
}
