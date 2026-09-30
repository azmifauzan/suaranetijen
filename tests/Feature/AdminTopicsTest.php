<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Themes\Models\Theme;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
});

test('non-admin cannot access admin topics', function () {
    $this->actingAs($this->user)
        ->get('/admin/topics')
        ->assertForbidden();
});

test('unauthenticated users cannot access admin topics', function () {
    $this->get('/admin/topics')
        ->assertRedirect('/login');
});

test('admin can view topics index and tabs', function () {
    SearchLandingPage::factory()->candidate()->create(['keyword' => 'kandidat 1']);
    SearchLandingPage::factory()->draft()->create(['keyword' => 'draft 1']);

    $response = $this->actingAs($this->admin)
        ->get('/admin/topics?status=candidate');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Topics/Index')
        ->has('topics.data', 1)
        ->where('currentStatus', 'candidate')
    );
});

test('admin can create manual topic', function () {
    $category = Category::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post('/admin/topics', [
            'keyword' => 'vps murah indonesia',
            'category_id' => $category->id,
            'title' => 'Rekomendasi VPS Murah Indonesia',
            'meta_description' => 'Opini netizen mengenai penyedia VPS murah di Indonesia.',
            'intro' => 'Netizen banyak membagikan panduan memilih VPS murah yang handal.',
        ]);

    $topic = SearchLandingPage::where('keyword', 'vps murah indonesia')->first();
    expect($topic)->not->toBeNull()
        ->and($topic->status)->toBe(SearchLandingPageStatus::Draft)
        ->and($topic->category_id)->toBe($category->id);

    $response->assertRedirect(route('admin.topics.edit', $topic));
});

test('publish requires category, at least one theme, title, meta, and intro', function () {
    $topic = SearchLandingPage::factory()->draft()->create([
        'category_id' => null,
        'title' => null,
        'meta_description' => null,
        'intro' => null,
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/admin/topics/{$topic->id}/publish");

    $response->assertSessionHasErrors(['category_id']);
});

test('publish rejects Tokoh Publik category', function () {
    $tokohPublik = Category::factory()->create(['slug' => 'tokoh-publik', 'name' => 'Tokoh Publik']);
    $politisi = Category::factory()->create(['slug' => 'politisi', 'name' => 'Politisi', 'parent_id' => $tokohPublik->id]);

    $theme = Theme::create(['slug' => 'kredibel', 'display_label' => 'Kredibel', 'canonical_key' => 'kredibel']);

    $topic = SearchLandingPage::factory()->draft()->create([
        'category_id' => $politisi->id,
        'title' => 'Politisi Pilihan Netizen',
        'meta_description' => 'Rangkuman pandangan publik.',
        'intro' => 'Pembahasan seputar tokoh politik nasional.',
    ]);
    $topic->themes()->attach($theme->id);

    $response = $this->actingAs($this->admin)
        ->post("/admin/topics/{$topic->id}/publish");

    $response->assertSessionHasErrors(['category_id']);
    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Draft);
});

test('publish enforces copy guard as validation errors', function () {
    $category = Category::factory()->create();
    $theme = Theme::create(['slug' => 'cepat', 'display_label' => 'Cepat', 'canonical_key' => 'cepat']);

    $topic = SearchLandingPage::factory()->draft()->create([
        'category_id' => $category->id,
        'title' => 'Hosting Terbaik di Indonesia', // Melanggar copy guard: terbaik
        'meta_description' => 'Diskon 50% untuk pelanggan baru.', // Melanggar: %
        'intro' => 'Cek web kami di https://example.com.', // Melanggar: url
    ]);
    $topic->themes()->attach($theme->id);

    $response = $this->actingAs($this->admin)
        ->post("/admin/topics/{$topic->id}/publish");

    $response->assertSessionHasErrors(['title', 'meta_description', 'intro']);
    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Draft);
});

test('admin can successfully publish valid topic', function () {
    $category = Category::factory()->create();
    $theme = Theme::create(['slug' => 'cepat', 'display_label' => 'Cepat', 'canonical_key' => 'cepat']);

    $topic = SearchLandingPage::factory()->draft()->create([
        'category_id' => $category->id,
        'title' => 'Pilihan VPS Cepat Netizen',
        'meta_description' => 'Kumpulan layanan VPS yang sering dibicarakan netizen karena kecepatannya.',
        'intro' => 'Kecepatan akses server menjadi pertimbangan utama netizen dalam menentukan layanan.',
    ]);
    $topic->themes()->attach($theme->id);

    $response = $this->actingAs($this->admin)
        ->post("/admin/topics/{$topic->id}/publish");

    $response->assertSessionHasNoErrors();
    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Published)
        ->and($topic->fresh()->published_at)->not->toBeNull();
});

test('slug is locked once published and cannot be modified', function () {
    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
        'slug' => 'vps-cepat',
        'keyword' => 'vps cepat',
    ]);

    // Try to update slug
    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'keyword' => 'vps cepat diubah',
            'slug' => 'vps-cepat-baru',
            'category_id' => $category->id,
        ]);

    $fresh = $topic->fresh();
    expect($fresh->slug)->toBe('vps-cepat') // Still locked!
        ->and($fresh->keyword)->toBe('vps cepat diubah');
});

test('unpublishing reverts to draft and makes public URL 404', function () {
    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
        'slug' => 'vps-murah',
    ]);

    // Public URL 200
    $this->get('/topik/vps-murah')->assertOk();

    // Unpublish
    $this->actingAs($this->admin)
        ->post("/admin/topics/{$topic->id}/unpublish");

    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Draft)
        ->and($topic->fresh()->published_at)->toBeNull();

    // Public URL now 404
    $this->get('/topik/vps-murah')->assertNotFound();
});

test('publish is refused for rejected and already published topics', function () {
    $category = Category::factory()->create();
    $theme = Theme::create(['slug' => 'murah', 'display_label' => 'murah', 'canonical_key' => 'murah']);

    $rejected = SearchLandingPage::factory()->rejected()->create(['category_id' => $category->id]);
    $rejected->themes()->attach($theme->id);

    $this->actingAs($this->admin)
        ->post("/admin/topics/{$rejected->id}/publish")
        ->assertSessionHasErrors('status');

    expect($rejected->fresh()->status)->toBe(SearchLandingPageStatus::Rejected);

    $published = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
        'published_at' => now()->subMonth(),
    ]);
    $published->themes()->attach($theme->id);
    $originalPublishedAt = $published->published_at->timestamp;

    $this->actingAs($this->admin)->post("/admin/topics/{$published->id}/publish");

    expect($published->fresh()->published_at->timestamp)->toBe($originalPublishedAt);
});

test('slug must be url-safe', function () {
    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->draft()->create(['category_id' => $category->id, 'slug' => 'aman']);

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'keyword' => 'vps cepat',
            'slug' => 'Vps Cepat!',
            'category_id' => $category->id,
        ])
        ->assertSessionHasErrors('slug');

    expect($topic->fresh()->slug)->toBe('aman');
});

test('copy guard also applies when editing, so a published topic cannot be edited into a superlative', function () {
    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->published()->create(['category_id' => $category->id]);
    $originalTitle = $topic->title;

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'keyword' => $topic->keyword,
            'category_id' => $category->id,
            'title' => 'VPS Terbaik di Indonesia',
        ])
        ->assertSessionHasErrors('title');

    expect($topic->fresh()->title)->toBe($originalTitle);
});

test('regenerate is refused for a published topic and leaves it untouched', function () {
    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->published()->create(['category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->post("/admin/topics/{$topic->id}/regenerate")
        ->assertSessionHasErrors('status');

    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Published);
});
