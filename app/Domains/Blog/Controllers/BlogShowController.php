<?php

namespace App\Domains\Blog\Controllers;

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Services\ScoreCalculator;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class BlogShowController extends Controller
{
    /**
     * Display single blog post.
     */
    public function show(string $slug): Response
    {
        /** @var BlogPost $post */
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->live()
            ->with([
                // A disabled entity 404s on /e/{slug}, so never render a card linking to it.
                'entities' => fn ($query) => $query->active(),
                'entities.category',
                'entities.sentimentSnapshots' => function ($query): void {
                    $query->whereIn('period', [Period::OneYear->value, Period::All->value]);
                },
            ])
            ->firstOrFail();

        $linkedEntities = $post->entities->map(function (Entity $entity) {
            $snapshot = $entity->sentimentSnapshots->firstWhere('period', Period::OneYear->value)
                ?? $entity->sentimentSnapshots->firstWhere('period', Period::All->value);

            $opinionCount = $snapshot ? (int) $snapshot->opinion_count : 0;
            $isEligible = $snapshot !== null && ScoreCalculator::isPublicScoreEligible($opinionCount);
            $score = ($isEligible && $snapshot->score !== null) ? (float) $snapshot->score : null;

            return [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'category_name' => $entity->category->name,
                'score' => $score,
                'opinion_count' => $opinionCount,
                'is_public_score_eligible' => $isEligible,
            ];
        });

        $relatedPosts = BlogPost::query()
            ->live()
            ->where('id', '!=', $post->id)
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (BlogPost $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'excerpt' => $p->effective_excerpt,
                'cover_url' => $p->cover_url,
                'published_at' => $p->published_at?->format('d M Y') ?? '',
            ]);

        return Inertia::render('Blog/Show', [
            'post' => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->effective_excerpt,
                'body_html' => $post->body_html,
                'cover_url' => $post->cover_url,
                'published_at' => $post->published_at?->format('d M Y') ?? '',
                'published_at_iso' => $post->published_at?->toIso8601String() ?? '',
                'updated_at_iso' => $post->updated_at?->toIso8601String() ?? '',
                'byline' => 'Tim SuaraNetijen',
            ],
            'linkedEntities' => $linkedEntities,
            'relatedPosts' => $relatedPosts,
        ]);
    }
}
