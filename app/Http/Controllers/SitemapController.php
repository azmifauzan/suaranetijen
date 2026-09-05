<?php

namespace App\Http\Controllers;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate dynamic XML sitemap (docs/13).
     *
     * Only indexes:
     * - Active, searchable entities clearing the public-score threshold (opinion_count >= 30).
     * - Active categories (/category/{slug} and /top/{slug}).
     * - Public static pages (/search, /methodology, /sources, /about, /terms, /privacy).
     */
    public function index(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $minOpinions = (int) config('scoring.public_min_opinions', 30);

        // 1. Static URLs
        $urls = [
            [
                'loc' => "{$baseUrl}/",
                'changefreq' => 'hourly',
                'priority' => '1.0',
            ],
            [
                'loc' => "{$baseUrl}/search",
                'changefreq' => 'daily',
                'priority' => '0.9',
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
        $categories = Category::query()->active()->get(['id', 'slug', 'updated_at']);
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

        // 3. Eligible entities ONLY (docs/13, docs/17: entities below threshold are noindex and NOT in sitemap)
        $eligibleEntities = Entity::query()
            ->where('status', EntityStatus::Active)
            ->where('searchable', true)
            ->where(function ($query) use ($minOpinions) {
                // The public entity page prefers a 365-day snapshot whenever
                // one exists, so the sitemap must apply the same rule.
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

            $lastmod = ($snapshot?->updated_at ?? $entity->updated_at)?->toIso8601String();

            $urls[] = [
                'loc' => "{$baseUrl}/e/{$entity->slug}",
                'lastmod' => $lastmod,
                'changefreq' => 'daily',
                'priority' => '0.7',
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

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
