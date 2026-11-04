<?php

use App\Domains\Blog\Enums\BlogPostStatus;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Entities\Models\Entity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
    Storage::fake('media');
});

test('non-admin cannot access admin blog', function (): void {
    $this->actingAs($this->user)
        ->get('/admin/blog')
        ->assertForbidden();
});

test('unauthenticated users cannot access admin blog', function (): void {
    $this->get('/admin/blog')
        ->assertRedirect('/login');
});

test('admin can view blog index and filter by status', function (): void {
    BlogPost::factory()->draft()->create(['title' => 'Draft Artikel Satu']);
    BlogPost::factory()->published()->create(['title' => 'Artikel Terbit Satu']);

    $response = $this->actingAs($this->admin)
        ->get('/admin/blog?status=draft');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Blog/Index')
        ->has('posts.data', 1)
        ->where('currentStatus', 'draft')
        ->where('posts.data.0.title', 'Draft Artikel Satu')
    );
});

test('admin can search blog posts by title', function (): void {
    BlogPost::factory()->create(['title' => 'Analisis Brand Mobil']);
    BlogPost::factory()->create(['title' => 'Metodologi Indeks']);

    $response = $this->actingAs($this->admin)
        ->get('/admin/blog?q=Metodologi');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Blog/Index')
        ->has('posts.data', 1)
        ->where('posts.data.0.title', 'Metodologi Indeks')
    );
});

test('admin can create blog draft with auto slug', function (): void {
    $response = $this->actingAs($this->admin)
        ->post('/admin/blog', [
            'title' => 'Rangkuman Opini Publik Kuartal III',
            'excerpt' => 'Catatan ringkas analisis netizen indonesia.',
            'body' => '## Pendahuluan'."\n\n".'Netizen membicarakan berbagai topik menarik.',
        ]);

    $post = BlogPost::where('title', 'Rangkuman Opini Publik Kuartal III')->first();
    expect($post)->not->toBeNull()
        ->and($post->status)->toBe(BlogPostStatus::Draft)
        ->and($post->slug)->toBe('rangkuman-opini-publik-kuartal-iii');

    $response->assertRedirect(route('admin.blog.edit', $post));
});

test('admin can upload cover image on creation', function (): void {
    $image = UploadedFile::fake()->image('cover.jpg', 1280, 720);

    $this->actingAs($this->admin)
        ->post('/admin/blog', [
            'title' => 'Artikel Dengan Cover Bagus',
            'body' => 'Isi artikel lengkap.',
            'cover' => $image,
        ]);

    $post = BlogPost::where('title', 'Artikel Dengan Cover Bagus')->first();
    expect($post)->not->toBeNull()
        ->and($post->cover_image_path)->not->toBeNull();

    Storage::disk('media')->assertExists($post->cover_image_path);
});

test('publish enforces copy guard on title and excerpt', function (): void {
    $post = BlogPost::factory()->draft()->create([
        'title' => 'Brand Ponsel Terbaik di Indonesia',
        'body' => 'Ulasan lengkap performa ponsel.',
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/admin/blog/{$post->id}/publish");

    $response->assertSessionHasErrors(['title']);
    expect($post->fresh()->status)->toBe(BlogPostStatus::Draft);
});

test('article body is not restricted by copy guard for percentages and links', function (): void {
    $response = $this->actingAs($this->admin)
        ->post('/admin/blog', [
            'title' => 'Analisis Metodologi Sampling Opini',
            'excerpt' => 'Pembahasan cara menghitung sentimen.',
            'body' => 'Sekitar 45% netizen memberikan ulasan positif. Kunjungi https://example.com/sumber untuk data.',
        ]);

    $post = BlogPost::where('title', 'Analisis Metodologi Sampling Opini')->first();
    expect($post)->not->toBeNull();

    $pubResponse = $this->actingAs($this->admin)
        ->post("/admin/blog/{$post->id}/publish");

    $pubResponse->assertSessionHasNoErrors();
    expect($post->fresh()->status)->toBe(BlogPostStatus::Published);
});

test('slug is locked once published and cannot be modified', function (): void {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'slug-awal-terkunci',
        'title' => 'Judul Awal',
        'body' => 'Konten awal.',
    ]);

    $this->actingAs($this->admin)
        ->put("/admin/blog/{$post->id}", [
            'slug' => 'slug-baru-diubah',
            'title' => 'Judul Baru',
            'body' => 'Konten baru.',
        ]);

    expect($post->fresh()->slug)->toBe('slug-awal-terkunci');
});

test('admin can unpublish published post back to draft', function (): void {
    $post = BlogPost::factory()->published()->create();

    $response = $this->actingAs($this->admin)
        ->post("/admin/blog/{$post->id}/unpublish");

    $response->assertRedirect();
    expect($post->fresh()->status)->toBe(BlogPostStatus::Draft);
});

test('published post cannot be deleted directly without unpublishing first', function (): void {
    $post = BlogPost::factory()->published()->create();

    $response = $this->actingAs($this->admin)
        ->delete("/admin/blog/{$post->id}");

    $response->assertSessionHasErrors(['status']);
    expect(BlogPost::find($post->id))->not->toBeNull();
});

test('draft post can be deleted and cleans up cover image from disk', function (): void {
    $image = UploadedFile::fake()->image('cover.webp', 1200, 630);
    $path = 'blog/covers/draft-test.webp';
    Storage::disk('media')->put($path, $image->getContent());

    $post = BlogPost::factory()->draft()->create([
        'cover_image_path' => $path,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/admin/blog/{$post->id}");

    $response->assertRedirect(route('admin.blog.index'));
    expect(BlogPost::find($post->id))->toBeNull();
    Storage::disk('media')->assertMissing($path);
});

test('admin can delete cover from post', function (): void {
    $path = 'blog/covers/to-delete.webp';
    Storage::disk('media')->put($path, 'image-data');

    $post = BlogPost::factory()->draft()->create([
        'cover_image_path' => $path,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/blog/{$post->id}/cover");

    expect($post->fresh()->cover_image_path)->toBeNull();
    Storage::disk('media')->assertMissing($path);
});

test('server preview markdown strips script tags and unsafe links', function (): void {
    $response = $this->actingAs($this->admin)
        ->postJson('/admin/blog-preview', [
            'markdown' => 'Halo <script>alert("xss")</script> [klik](javascript:alert(1))',
        ]);

    $response->assertOk();
    $html = $response->json('html');
    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('javascript:alert');
});

test('replacing a cover stores the new object and deletes the old one', function (): void {
    $oldPath = 'blog/covers/old.webp';
    Storage::disk('media')->put($oldPath, 'old-image');
    $post = BlogPost::factory()->draft()->create(['cover_image_path' => $oldPath]);

    $this->actingAs($this->admin)
        ->put("/admin/blog/{$post->id}", [
            'title' => $post->title,
            'slug' => $post->slug,
            'body' => $post->body,
            'cover' => UploadedFile::fake()->image('new.png', 1200, 630),
        ])
        ->assertSessionHasNoErrors();

    $newPath = $post->fresh()->cover_image_path;
    expect($newPath)->toStartWith('blog/covers/')->toEndWith('.png')->not->toBe($oldPath);
    Storage::disk('media')->assertExists($newPath);
    Storage::disk('media')->assertMissing($oldPath);
});

test('saving without entity_ids unlinks every entity', function (): void {
    $post = BlogPost::factory()->draft()->create();
    $post->entities()->attach(Entity::factory()->create());

    $this->actingAs($this->admin)
        ->put("/admin/blog/{$post->id}", [
            'title' => $post->title,
            'slug' => $post->slug,
            'body' => $post->body,
        ])
        ->assertSessionHasNoErrors();

    expect($post->entities()->count())->toBe(0);
});

test('editing the body of a published post flushes the public page cache', function (): void {
    $post = BlogPost::factory()->published()->create();
    $epochBefore = Cache::get('page-cache:epoch', 0);

    $this->actingAs($this->admin)
        ->put("/admin/blog/{$post->id}", [
            'title' => $post->title,
            'body' => 'Isi yang sudah diperbarui.',
        ])
        ->assertSessionHasNoErrors();

    expect(Cache::get('page-cache:epoch', 0))->toBeGreaterThan($epochBefore);
});

test('saving a published post with an empty date keeps its published_at', function (): void {
    $post = BlogPost::factory()->published()->create();
    $publishedAt = $post->published_at;

    $this->actingAs($this->admin)
        ->put("/admin/blog/{$post->id}", [
            'title' => $post->title,
            'body' => $post->body,
            'published_at' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($post->fresh()->published_at?->equalTo($publishedAt))->toBeTrue();
    $this->get("/blog/{$post->slug}")->assertOk();
});
