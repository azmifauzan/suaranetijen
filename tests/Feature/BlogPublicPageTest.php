<?php

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    Cache::flush();
    SitemapController::clearCache();
});

test('it returns 200 for /blog and lists published posts', function (): void {
    BlogPost::factory()->published()->create([
        'title' => 'Analisis Sentimen Otomotif 2026',
        'slug' => 'analisis-sentimen-otomotif-2026',
        'published_at' => now()->subDay(),
    ]);

    // Draft post should NOT be listed
    BlogPost::factory()->draft()->create([
        'title' => 'Draft Tersembunyi',
        'slug' => 'draft-tersembunyi',
    ]);

    // Future scheduled post should NOT be listed
    BlogPost::factory()->future()->create([
        'title' => 'Artikel Masa Depan',
        'slug' => 'artikel-masa-depan',
    ]);

    $response = $this->get('/blog');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Blog/Index')
        ->has('posts.data', 1)
        ->where('posts.data.0.slug', 'analisis-sentimen-otomotif-2026')
        ->where('posts.data.0.title', 'Analisis Sentimen Otomotif 2026')
    );
});

test('it returns 200 for published blog show page', function (): void {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'metodologi-sentimen-lengkap',
        'title' => 'Metodologi Sentimen Lengkap SuaraNetijen',
        'body' => '## Rangkuman'."\n\n".'Penjelasan detail algoritma kami.',
    ]);

    $response = $this->get('/blog/metodologi-sentimen-lengkap');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Blog/Show')
        ->where('post.slug', 'metodologi-sentimen-lengkap')
        ->where('post.title', 'Metodologi Sentimen Lengkap SuaraNetijen')
        ->has('post.body_html')
    );
});

test('it returns 404 for draft blog post', function (): void {
    $post = BlogPost::factory()->draft()->create([
        'slug' => 'draft-rahasia',
    ]);

    $this->get('/blog/draft-rahasia')->assertNotFound();
});

test('it returns 404 for future-scheduled blog post', function (): void {
    $post = BlogPost::factory()->future()->create([
        'slug' => 'artikel-jadwal-besok',
    ]);

    $this->get('/blog/artikel-jadwal-besok')->assertNotFound();
});

test('it does not contain AggregateRating in rendered page', function (): void {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'tanpa-aggregate-rating',
    ]);

    $response = $this->get('/blog/tanpa-aggregate-rating');

    $response->assertOk();
    $response->assertDontSee('AggregateRating');
});

test('it renders linked entities with live sentiment score if eligible', function (): void {
    $category = Category::factory()->create(['name' => 'Smartphone']);

    $eligibleEntity = Entity::factory()->create([
        'name' => 'Brand Cepat',
        'slug' => 'brand-cepat',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    SentimentSnapshot::create([
        'entity_id' => $eligibleEntity->id,
        'period' => Period::OneYear->value,
        'opinion_count' => 50,
        'positive_count' => 40,
        'neutral_count' => 5,
        'negative_count' => 5,
        'score' => 85.0,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $ineligibleEntity = Entity::factory()->create([
        'name' => 'Brand Baru',
        'slug' => 'brand-baru',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    SentimentSnapshot::create([
        'entity_id' => $ineligibleEntity->id,
        'period' => Period::OneYear->value,
        'opinion_count' => 10,
        'positive_count' => 8,
        'neutral_count' => 1,
        'negative_count' => 1,
        'score' => null,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $post = BlogPost::factory()->published()->create([
        'slug' => 'ulasan-gadget-terbaru',
    ]);
    $post->entities()->attach([$eligibleEntity->id, $ineligibleEntity->id]);

    $response = $this->get('/blog/ulasan-gadget-terbaru');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Blog/Show')
        ->has('linkedEntities', 2)
        ->where('linkedEntities.0.name', 'Brand Cepat')
        ->where('linkedEntities.0.score', fn ($score) => (float) $score === 85.0)
        ->where('linkedEntities.0.is_public_score_eligible', true)
        ->where('linkedEntities.1.name', 'Brand Baru')
        ->where('linkedEntities.1.score', null)
        ->where('linkedEntities.1.is_public_score_eligible', false)
    );
});

test('it strips raw html script tags in markdown body', function (): void {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'artikel-xss-test',
        'body' => 'Teks aman <script>alert("bahaya")</script> akhir.',
    ]);

    expect($post->body_html)->not->toContain('<script>')
        ->and($post->body_html)->toContain('Teks aman');
});

test('sitemap includes /blog and published posts, but omits drafts and future posts', function (): void {
    // 1. Published post (live)
    BlogPost::factory()->published()->create([
        'slug' => 'artikel-terbit-sitemap',
    ]);

    // 2. Draft post
    BlogPost::factory()->draft()->create([
        'slug' => 'artikel-draft-sitemap',
    ]);

    // 3. Future scheduled post
    BlogPost::factory()->future()->create([
        'slug' => 'artikel-future-sitemap',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('/blog')
        ->and($content)->toContain('/blog/artikel-terbit-sitemap')
        ->and($content)->not->toContain('/blog/artikel-draft-sitemap')
        ->and($content)->not->toContain('/blog/artikel-future-sitemap');
});

test('saving or deleting a post clears sitemap cache', function (): void {
    Cache::put(SitemapController::CACHE_KEY, 'cached-sitemap-xml', now()->addHour());

    $post = BlogPost::factory()->published()->create([
        'slug' => 'post-cache-test',
    ]);

    expect(Cache::has(SitemapController::CACHE_KEY))->toBeFalse();

    Cache::put(SitemapController::CACHE_KEY, 'cached-sitemap-xml', now()->addHour());

    $post->delete();

    expect(Cache::has(SitemapController::CACHE_KEY))->toBeFalse();
});

test('it renders h1 in real ssr output for blog show page', function (): void {
    $script = <<<'NODE'
import('./bootstrap/ssr/app.js').then(async (m) => {
    const res = await m.default({
        component: 'Blog/Show',
        props: {
            post: {
                id: 1,
                title: 'Verifikasi H1 SSR Blog',
                slug: 'verifikasi-h1-ssr-blog',
                body_html: '<p>Konten SSR.</p>',
                excerpt: 'Ringkasan SSR.',
                cover_url: null,
                published_at: '03 Nov 2026',
                published_at_iso: '2026-11-03T16:00:00+07:00',
                updated_at_iso: '2026-11-03T16:00:00+07:00',
                byline: 'Tim SuaraNetijen',
            },
            linkedEntities: [],
            relatedPosts: [],
            seo: { site_name: 'SuaraNetijen', site_url: 'https://suaranetijen.com' },
        },
        url: '/blog/verifikasi-h1-ssr-blog',
        version: '',
    });
    if (res.body.includes('<h1') && res.body.includes('Verifikasi H1 SSR Blog')) {
        process.stdout.write('SSR_H1_PASS');
    }
    process.exit(0);
});
NODE;

    $process = new Process(['node', '--input-type=module', '-e', $script]);
    $process->run();

    expect($process->getOutput())->toContain('SSR_H1_PASS');
});

test('it hides linked entities that are not active', function (): void {
    $post = BlogPost::factory()->published()->create(['slug' => 'artikel-entitas']);
    $post->entities()->attach([
        Entity::factory()->create(['name' => 'Entitas Aktif', 'status' => EntityStatus::Active])->id,
        Entity::factory()->create(['name' => 'Entitas Nonaktif', 'status' => EntityStatus::Disabled])->id,
    ]);

    $this->get('/blog/artikel-entitas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('linkedEntities', 1)
            ->where('linkedEntities.0.name', 'Entitas Aktif')
        );
});
