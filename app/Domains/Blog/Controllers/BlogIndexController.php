<?php

namespace App\Domains\Blog\Controllers;

use App\Domains\Blog\Models\BlogPost;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class BlogIndexController extends Controller
{
    /**
     * Display listing of published blog posts.
     */
    public function index(): Response
    {
        $posts = BlogPost::query()
            ->live()
            ->latest('published_at')
            ->paginate(12)
            ->through(fn (BlogPost $post) => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->effective_excerpt,
                'cover_url' => $post->cover_url,
                'published_at' => $post->published_at?->format('d M Y') ?? '',
                'byline' => 'Tim SuaraNetijen',
            ]);

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
        ]);
    }
}
