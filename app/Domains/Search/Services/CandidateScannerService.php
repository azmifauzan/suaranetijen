<?php

namespace App\Domains\Search\Services;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Enums\SearchLandingPageSource;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Themes\Models\Theme;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CandidateScannerService
{
    /**
     * Scan candidate topics from search queries and category x theme combinations.
     *
     * @return array{search_queries: int, category_themes: int, errors: list<string>}
     */
    public function scan(): array
    {
        $newSearchQueryCount = 0;
        $newCatThemeCount = 0;
        $errors = [];

        // 1. Scan Search Queries
        try {
            $newSearchQueryCount = $this->scanSearchQueries();
        } catch (Throwable $e) {
            Log::error('landing_pages.scan_search_queries_failed', ['error' => $e->getMessage()]);
            $errors[] = "Search query scan failed: {$e->getMessage()}";
        }

        // 2. Scan Category x Theme
        try {
            $newCatThemeCount = $this->scanCategoryThemes();
        } catch (Throwable $e) {
            Log::error('landing_pages.scan_category_themes_failed', ['error' => $e->getMessage()]);
            $errors[] = "Category theme scan failed: {$e->getMessage()}";
        }

        return [
            'search_queries' => $newSearchQueryCount,
            'category_themes' => $newCatThemeCount,
            'errors' => $errors,
        ];
    }

    /**
     * Scan search_queries table for candidates.
     */
    public function scanSearchQueries(): int
    {
        $minSignals = (int) config('landing_pages.search_query_min_signals', 5);
        $windowDays = (int) config('landing_pages.search_query_window_days', 30);
        $since = Carbon::now()->subDays($windowDays);

        $driver = DB::connection()->getDriverName();
        $userSessionExpr = $driver === 'pgsql'
            ? 'COALESCE(user_id::text, session_id)'
            : 'COALESCE(CAST(user_id AS TEXT), session_id)';

        $rows = DB::table('search_queries')
            ->where('created_at', '>=', $since)
            ->where('result_count', '>', 0)
            ->whereNotNull('normalized_query')
            ->where('normalized_query', '!=', '')
            ->groupBy('normalized_query')
            ->select([
                'normalized_query',
                DB::raw('MIN(query) as raw_query'),
                DB::raw("COUNT(DISTINCT {$userSessionExpr}) as signal_count"),
            ])
            ->havingRaw("COUNT(DISTINCT {$userSessionExpr}) >= ?", [$minSignals])
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        // Load existing entities, aliases, and category names to skip exact matches
        $exactMatches = $this->getExactNamesToSkip();
        $publicFigureTerms = $this->getPublicFigureTerms();

        $count = 0;
        foreach ($rows as $row) {
            $norm = TextNormalizer::normalize((string) $row->normalized_query);
            $rawQuery = trim((string) $row->raw_query);

            if (mb_strlen($norm) <= 1) {
                continue;
            }

            if ($exactMatches->contains($norm)) {
                continue;
            }

            if (! $this->isSafe($rawQuery) || $this->mentionsAny($norm, $publicFigureTerms)) {
                continue;
            }

            // Dedup across all statuses
            if (SearchLandingPage::where('normalized_keyword', $norm)->exists()) {
                continue;
            }

            $slug = $this->generateUniqueSlug($rawQuery);

            SearchLandingPage::create([
                'slug' => $slug,
                'keyword' => $rawQuery,
                'normalized_keyword' => $norm,
                'category_id' => null,
                'source' => SearchLandingPageSource::SearchQuery,
                'status' => SearchLandingPageStatus::Candidate,
                'candidate_signal' => (int) $row->signal_count,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Scan Category x Theme combinations.
     */
    public function scanCategoryThemes(): int
    {
        $minEntities = (int) config('landing_pages.category_theme_min_entities', 5);
        $minObs = (int) config('landing_pages.category_theme_min_observations', 3);

        // Leaf categories (active, no children, excluding Tokoh Publik)
        $leafCategories = Category::query()
            ->active()
            ->whereDoesntHave('children')
            ->excludePublicFigure()
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        if ($leafCategories->isEmpty()) {
            return 0;
        }

        $leafCategoryIds = $leafCategories->keys()->all();

        $rows = DB::table('entity_theme_snapshots')
            ->join('entities', 'entities.id', '=', 'entity_theme_snapshots.entity_id')
            ->where('entity_theme_snapshots.window', Period::OneYear->value)
            ->where('entity_theme_snapshots.observation_count', '>=', $minObs)
            ->where('entities.status', EntityStatus::Active->value)
            ->where('entities.searchable', true)
            ->whereIn('entities.category_id', $leafCategoryIds)
            ->groupBy('entities.category_id', 'entity_theme_snapshots.theme_id')
            ->select([
                'entities.category_id',
                'entity_theme_snapshots.theme_id',
                DB::raw('COUNT(DISTINCT entities.id) as entity_count'),
            ])
            ->havingRaw('COUNT(DISTINCT entities.id) >= ?', [$minEntities])
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $themeIds = $rows->pluck('theme_id')->unique()->all();
        $themes = Theme::whereIn('id', $themeIds)->get(['id', 'display_label', 'slug'])->keyBy('id');

        $count = 0;
        foreach ($rows as $row) {
            $cat = $leafCategories->get($row->category_id);
            $theme = $themes->get($row->theme_id);

            if (! $cat || ! $theme) {
                continue;
            }

            $rawKeyword = "{$cat->name} {$theme->display_label}";
            $norm = TextNormalizer::normalize($rawKeyword);

            if (mb_strlen($norm) <= 1 || ! $this->isSafe($rawKeyword)) {
                continue;
            }

            if (SearchLandingPage::where('normalized_keyword', $norm)->exists()) {
                continue;
            }

            $slug = $this->generateUniqueSlug($rawKeyword);

            $landingPage = SearchLandingPage::create([
                'slug' => $slug,
                'keyword' => $rawKeyword,
                'normalized_keyword' => $norm,
                'category_id' => $cat->id,
                'source' => SearchLandingPageSource::CategoryTheme,
                'status' => SearchLandingPageStatus::Candidate,
                'candidate_signal' => (int) $row->entity_count,
            ]);

            $landingPage->themes()->attach($theme->id);

            $count++;
        }

        return $count;
    }

    /**
     * Check deterministic safety filter: blocklist words, phone, email, URL.
     */
    public function isSafe(string $text): bool
    {
        $blocklist = (array) config('landing_pages.blocklist', []);

        foreach ($blocklist as $word) {
            if ($word !== '' && preg_match('/\b'.preg_quote($word, '/').'\b/iu', $text)) {
                return false;
            }
        }

        // Phone numbers (e.g. +628123456789 or 08123456789)
        if (preg_match('/(\+?62|08)\d{8,13}/', $text)) {
            return false;
        }

        // Email address
        if (preg_match('/\S+@\S+\.\S+/', $text)) {
            return false;
        }

        // URL
        if (preg_match('/https?:\/\//i', $text)) {
            return false;
        }

        return true;
    }

    /**
     * Generate unique slug by appending suffix on collision.
     */
    public function generateUniqueSlug(string $keyword): string
    {
        $base = Str::slug($keyword);
        if ($base === '') {
            $base = 'topik-'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $counter = 2;

        while (SearchLandingPage::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Collection of exact normalized entity names, aliases, and category names.
     *
     * @return Collection<int, string>
     */
    private function getExactNamesToSkip(): Collection
    {
        $entityNames = Entity::where('status', EntityStatus::Active)
            ->pluck('name')
            ->map(fn (string $name) => TextNormalizer::normalize($name));

        $aliases = EntityAlias::pluck('normalized_alias');

        $categories = Category::pluck('name')
            ->map(fn (string $name) => TextNormalizer::normalize($name));

        return $entityNames->concat($aliases)->concat($categories)->filter()->unique()->values();
    }

    /**
     * Normalized names and aliases of person entities. A query that mentions one
     * ("<nama> korupsi") reads as a claim by the site, so it never becomes a topic.
     *
     * @return array<int, string>
     */
    private function getPublicFigureTerms(): array
    {
        $names = Entity::where('type', EntityType::Person)
            ->pluck('name')
            ->map(fn (string $name) => TextNormalizer::normalize($name));

        $aliases = EntityAlias::whereIn('entity_id', Entity::where('type', EntityType::Person)->select('id'))
            ->pluck('normalized_alias');

        return $names->concat($aliases)
            ->map(fn ($term) => (string) $term)
            ->filter(fn (string $term) => mb_strlen($term) > 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function mentionsAny(string $normalized, array $terms): bool
    {
        foreach ($terms as $term) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u', $normalized)) {
                return true;
            }
        }

        return false;
    }
}
