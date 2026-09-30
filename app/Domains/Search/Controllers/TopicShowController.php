<?php

namespace App\Domains\Search\Controllers;

use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\TopicEntityList;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TopicShowController extends Controller
{
    public function __construct(
        protected TopicEntityList $topicEntityList
    ) {}

    public function show(string $slug): Response
    {
        /** @var SearchLandingPage $topic */
        $topic = SearchLandingPage::query()
            ->where('slug', $slug)
            ->where('status', SearchLandingPageStatus::Published)
            ->with(['category.parent', 'themes'])
            ->firstOrFail();

        $listData = $this->topicEntityList->get($topic);

        $relatedTopics = [];
        if ($topic->category_id) {
            $relatedTopics = SearchLandingPage::query()
                ->published()
                ->where('category_id', $topic->category_id)
                ->where('id', '!=', $topic->id)
                ->latest('published_at')
                ->limit(6)
                ->get(['id', 'slug', 'keyword', 'title'])
                ->map(fn (SearchLandingPage $t) => [
                    'id' => $t->id,
                    'slug' => $t->slug,
                    'keyword' => $t->keyword,
                    'title' => $t->title ?: $t->keyword,
                ])
                ->all();
        }

        return Inertia::render('Topics/Show', [
            'topic' => [
                'id' => $topic->id,
                'slug' => $topic->slug,
                'keyword' => $topic->keyword,
                'title' => $topic->title ?: "Opini Netizen tentang {$topic->keyword}",
                'meta_description' => $topic->meta_description ?: "Daftar entitas terkait {$topic->keyword} berdasarkan frekuensi suara netizen.",
                'intro' => $topic->intro,
                'updated_at' => $topic->updated_at?->format('d M Y') ?? '',
                'category' => $topic->category ? [
                    'id' => $topic->category->id,
                    'name' => $topic->category->name,
                    'slug' => $topic->category->slug,
                ] : null,
                'themes' => $topic->themes->map(fn ($t) => [
                    'id' => $t->id,
                    'display_label' => $t->display_label,
                ]),
            ],
            'entities' => $listData['entities'],
            'isIndexable' => $listData['is_indexable'],
            'window' => $listData['window'],
            'relatedTopics' => $relatedTopics,
        ]);
    }
}
