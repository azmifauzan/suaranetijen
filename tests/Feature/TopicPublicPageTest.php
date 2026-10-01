<?php

use App\Domains\Search\Models\SearchLandingPage;

test('public topic pages do not expose unverified generated copy', function () {
    $topic = SearchLandingPage::factory()->published()->create([
        'intro' => 'Teks intro yang belum didukung bukti.',
        'meta_description' => 'Deskripsi yang belum diverifikasi.',
    ]);

    $this->get("/topik/{$topic->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Topics/Show')
            ->missing('topic.intro')
            ->missing('topic.meta_description')
        );
});

test('public search does not expose generated topic descriptions', function () {
    SearchLandingPage::factory()->published()->create([
        'keyword' => 'desain mobil',
        'normalized_keyword' => 'desain mobil',
        'meta_description' => 'Netizen ramai membahas detail yang menarik.',
    ]);

    $this->get('/search?q=desain%20mobil')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->missing('matchingTopic.meta_description')
        );
});
