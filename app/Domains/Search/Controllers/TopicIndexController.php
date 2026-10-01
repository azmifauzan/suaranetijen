<?php

namespace App\Domains\Search\Controllers;

use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\TopicEntityList;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TopicIndexController extends Controller
{
    public function __construct(
        protected TopicEntityList $topicEntityList
    ) {}

    public function index(): Response
    {
        $publishedTopics = SearchLandingPage::query()
            ->published()
            ->with(['category.parent', 'themes'])
            ->latest('published_at')
            ->get();

        $indexableTopics = $publishedTopics->filter(fn (SearchLandingPage $t) => $this->topicEntityList->isIndexable($t));

        $grouped = [];

        foreach ($indexableTopics as $topic) {
            $grouped[$this->groupName($topic)][] = [
                'id' => $topic->id,
                'slug' => $topic->slug,
                'keyword' => $topic->keyword,
                'title' => $topic->title ?: $topic->keyword,
                'category_name' => $topic->category?->name,
            ];
        }

        return Inertia::render('Topics/Index', [
            'groupedTopics' => $grouped,
            'totalTopics' => $indexableTopics->count(),
            'isIndexable' => $indexableTopics->isNotEmpty(),
        ]);
    }

    private function groupName(SearchLandingPage $topic): string
    {
        $category = $topic->category;

        if ($category === null) {
            return 'Topik Lainnya';
        }

        return $category->parent === null ? $category->name : $category->parent->name;
    }
}
