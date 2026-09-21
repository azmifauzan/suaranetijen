<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Services\HostResolver;
use Illuminate\Support\Facades\Http;

function fakePublicResolver(string $ip = '93.184.216.34'): void
{
    app()->instance(HostResolver::class, new class($ip) extends HostResolver
    {
        public function __construct(private readonly string $ip) {}

        public function resolve(string $host): string
        {
            return $this->ip;
        }
    });
}

test('it fetches a URL, extracts its title and description, and returns matching entity candidates', function () {
    fakePublicResolver();
    $entity = Entity::factory()->create([
        'name' => 'Samsung',
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    Http::fake([
        'https://samsung.com/*' => Http::response(
            '<html><head><title>Samsung Indonesia</title>'
            .'<meta property="og:description" content="Inovasi &amp; teknologi dari Samsung.">'
            .'</head></html>',
            200
        ),
    ]);

    $response = $this->postJson(route('api.sponsor.preview'), ['url' => 'https://samsung.com/']);

    $response->assertOk()
        ->assertJsonPath('preview.title', 'Samsung Indonesia')
        ->assertJsonPath('preview.url', 'https://samsung.com/')
        ->assertJsonPath('preview.description', 'Inovasi & teknologi dari Samsung.');

    expect(collect($response->json('candidates'))->pluck('id'))->toContain($entity->id);
});

test('it falls back to the plain meta description when there is no og:description', function () {
    fakePublicResolver();

    Http::fake([
        'https://plain-meta.example/*' => Http::response(
            '<html><head><title>Brand Punya Meta</title>'
            .'<meta name="description" content="Deskripsi meta biasa.">'
            .'</head></html>',
            200
        ),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://plain-meta.example/'])
        ->assertOk()
        ->assertJsonPath('preview.description', 'Deskripsi meta biasa.');
});

test('it returns a null description when the page has none', function () {
    fakePublicResolver();

    Http::fake([
        'https://no-desc.example/*' => Http::response('<html><head><title>Tanpa Deskripsi</title></head></html>', 200),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://no-desc.example/'])
        ->assertOk()
        ->assertJsonPath('preview.description', null);
});

test('it reports no candidates instead of guessing when nothing matches', function () {
    fakePublicResolver();

    Http::fake([
        'https://totally-unknown-brand.example/*' => Http::response('<html><head><title>Zzyxx Qwerty Nonexistent Brand</title></head></html>', 200),
    ]);

    $response = $this->postJson(route('api.sponsor.preview'), ['url' => 'https://totally-unknown-brand.example/']);

    $response->assertOk()
        ->assertJsonPath('candidates', []);
});

test('it rejects a URL that resolves to a private/internal IP (SSRF guard)', function () {
    fakePublicResolver('10.0.0.5');

    $response = $this->postJson(route('api.sponsor.preview'), ['url' => 'https://internal.example/']);

    $response->assertUnprocessable()
        ->assertJsonPath('error', 'URL tidak dapat diakses.');
});

test('it rejects a non-http(s) URL', function () {
    $response = $this->postJson(route('api.sponsor.preview'), ['url' => 'ftp://example.com/file']);

    $response->assertUnprocessable();
});

test('it reports an error when the URL is unreachable', function () {
    fakePublicResolver();

    Http::fake([
        'https://down.example/*' => Http::response('', 500),
    ]);

    $response = $this->postJson(route('api.sponsor.preview'), ['url' => 'https://down.example/']);

    $response->assertUnprocessable();
});

test('it reports an error when the page has no title', function () {
    fakePublicResolver();

    Http::fake([
        'https://no-title.example/*' => Http::response('<html><body>No title here</body></html>', 200),
    ]);

    $response = $this->postJson(route('api.sponsor.preview'), ['url' => 'https://no-title.example/']);

    $response->assertUnprocessable()
        ->assertJsonPath('error', 'Tidak dapat menemukan judul halaman.');
});
