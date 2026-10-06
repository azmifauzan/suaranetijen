<?php

namespace App\Http\Controllers;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\EntityComparison;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\TopicEntityList;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public const CACHE_KEY = 'seo:sitemap_xml';

    /**
     * Clear the cached XML sitemap.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Generate dynamic XML sitemap (docs/13, docs/31).
     *
     * Only indexes:
     * - Active, searchable entities clearing the public-score threshold (opinion_count >= 30).
     * - Active categories (/category/{slug} and /top/{slug}).
     * - Public static trust pages (/methodology, /sources, /about, /terms, /privacy).
     * Excludes /search per docs/31 item 0.7.
     */
    public function index(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addDay(), fn () => $this->buildSitemapXml());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    /**
     * Build the raw sitemap XML string.
     */
    public function buildSitemapXml(): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $minOpinions = (int) config('scoring.public_min_opinions', 30);

        // 1. Static URLs (Excludes /search per docs/31 Fase 0 item 0.7)
        $urls = [
            [
                'loc' => "{$baseUrl}/",
                'changefreq' => 'hourly',
                'priority' => '1.0',
            ],
            [
                'loc' => "{$baseUrl}/methodology",
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ],
            [
                'loc' => "{$baseUrl}/sources",
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'loc' => "{$baseUrl}/about",
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ],
            [
                'loc' => "{$baseUrl}/terms",
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ],
            [
                'loc' => "{$baseUrl}/privacy",
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ],
        ];

        // 2. Active Categories & Top Lists
        $categories = Category::query()
            ->active()
            ->where(fn ($query) => $query
                ->whereHas('entities', fn ($q) => $q->where('status', EntityStatus::Active))
                ->orWhereHas('children.entities', fn ($q) => $q->where('status', EntityStatus::Active)))
            ->get(['id', 'slug', 'updated_at']);

        foreach ($categories as $category) {
            $lastmod = $category->updated_at?->toIso8601String();

            $urls[] = [
                'loc' => "{$baseUrl}/category/{$category->slug}",
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
            $urls[] = [
                'loc' => "{$baseUrl}/top/{$category->slug}",
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
        }

        // 3. Eligible entities ONLY (docs/13, docs/17, docs/31)
        $eligibleEntities = Entity::query()
            ->where('status', EntityStatus::Active)
            ->where('searchable', true)
            ->where(function ($query) use ($minOpinions) {
                $query->whereHas('sentimentSnapshots', function ($snapshotQuery) use ($minOpinions) {
                    $snapshotQuery->where('period', Period::OneYear->value)
                        ->where('opinion_count', '>=', $minOpinions)
                        ->whereNotNull('score');
                })->orWhere(function ($fallbackQuery) use ($minOpinions) {
                    $fallbackQuery
                        ->whereDoesntHave('sentimentSnapshots', function ($snapshotQuery) {
                            $snapshotQuery->where('period', Period::OneYear->value);
                        })
                        ->whereHas('sentimentSnapshots', function ($snapshotQuery) use ($minOpinions) {
                            $snapshotQuery->where('period', Period::All->value)
                                ->where('opinion_count', '>=', $minOpinions)
                                ->whereNotNull('score');
                        });
                });
            })
            ->with(['sentimentSnapshots' => function ($query) {
                $query->whereIn('period', [Period::OneYear->value, Period::All->value]);
            }])
            ->get(['id', 'slug', 'updated_at']);

        foreach ($eligibleEntities as $entity) {
            $snapshot = $entity->sentimentSnapshots->firstWhere('period', Period::OneYear->value)
                ?? $entity->sentimentSnapshots->firstWhere('period', Period::All->value);

            // Follow meaningful snapshot calculation timestamp (docs/13, docs/31)
            $lastmod = ($snapshot->calculated_at ?? $snapshot->updated_at ?? $entity->updated_at)?->toIso8601String();

            $urls[] = [
                'loc' => "{$baseUrl}/e/{$entity->slug}",
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];
        }

        // 4. Topic Landing Pages (docs/28)
        $topicEntityList = app(TopicEntityList::class);
        $topicUrls = [];

        $publishedTopics = SearchLandingPage::query()
            ->published()
            ->with(['themes', 'category.parent'])
            ->get();

        foreach ($publishedTopics as $topic) {
            if ($topicEntityList->isIndexable($topic)) {
                $latestCalculatedAt = EntityThemeSnapshot::query()
                    ->whereIn('theme_id', $topic->themes->pluck('id'))
                    ->max('calculated_at');

                $lastmod = ($latestCalculatedAt ? Carbon::parse($latestCalculatedAt) : $topic->updated_at)?->toIso8601String();

                $topicUrls[] = [
                    'loc' => "{$baseUrl}/topik/{$topic->slug}",
                    'lastmod' => $lastmod,
                    'changefreq' => 'daily',
                    'priority' => '0.7',
                ];
            }
        }

        if ($topicUrls !== []) {
            $urls[] = [
                'loc' => "{$baseUrl}/topik",
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
            array_push($urls, ...$topicUrls);
        }

        // 5. Curated comparison pages (docs/31 Fase 3)
        foreach (app(EntityComparison::class)->indexablePages() as $page) {
            $urls[] = [
                'loc' => "{$baseUrl}/banding/{$page['pair']}",
                'lastmod' => $page['lastmod'],
                'changefreq' => 'daily',
                'priority' => '0.6',
            ];
        }

        // Build XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($entry['loc'])."</loc>\n";
            if (! empty($entry['lastmod'])) {
                $xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            }
            $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$entry['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
