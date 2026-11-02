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

test('an unreachable site still returns a name suggested from its domain instead of a dead end', function () {
    fakePublicResolver();

    Http::fake([
        'https://www.dsfa-homemade.example/*' => Http::response('', 403),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://www.dsfa-homemade.example/'])
        ->assertOk()
        ->assertJsonPath('preview', null)
        ->assertJsonPath('fetch_error', 'URL tidak dapat diakses (status 403).')
        ->assertJsonPath('suggested_name', 'Dsfa Homemade');
});

test('a page without a title is reported as a fetch error, not a rejected URL', function () {
    fakePublicResolver();

    Http::fake([
        'https://no-title.example/*' => Http::response('<html><body>No title here</body></html>', 200),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://no-title.example/'])
        ->assertOk()
        ->assertJsonPath('fetch_error', 'Tidak dapat menemukan judul halaman.');
});

test('it follows a redirect to the final page', function () {
    fakePublicResolver();

    Http::fake([
        'https://dana.example/' => Http::response('', 301, ['Location' => 'https://www.dana.example/']),
        'https://www.dana.example/' => Http::response('<html><head><title>DANA</title></head></html>', 200),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://dana.example/'])
        ->assertOk()
        ->assertJsonPath('preview.title', 'DANA')
        ->assertJsonPath('preview.url', 'https://www.dana.example/');
});

test('it never follows a redirect into a private network', function () {
    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function resolve(string $host): string
        {
            return $host === 'internal.example' ? '10.0.0.5' : '93.184.216.34';
        }
    });

    Http::fake([
        'https://public.example/' => Http::response('', 302, ['Location' => 'https://internal.example/admin']),
        'https://internal.example/*' => Http::response('<html><head><title>Secret</title></head></html>', 200),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://public.example/'])
        ->assertOk()
        ->assertJsonPath('preview', null)
        ->assertJsonPath('fetch_error', 'URL tidak dapat diakses.');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'internal.example'));
});

test('an entity registered with the same domain is matched directly and title search is skipped', function () {
    fakePublicResolver();
    $entity = Entity::factory()->create([
        'name' => 'DSFA Homemade',
        'website_url' => 'https://www.dsfahomemade.example',
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    Http::fake([
        'https://dsfahomemade.example/*' => Http::response('<html><head><title>DSFA Homemade Bandung | Cheesecuit, Takoyaki</title></head></html>', 200),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://dsfahomemade.example/'])
        ->assertOk()
        ->assertJsonPath('domain_match.id', $entity->id)
        ->assertJsonPath('candidates', []);
});

test('a page title is trimmed to its brand part for the suggested name', function () {
    fakePublicResolver();

    Http::fake([
        'https://dsfa.example/*' => Http::response('<html><head><title>DSFA Homemade Bandung | Cheesecuit, Takoyaki, Puding &amp; Jus</title></head></html>', 200),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://dsfa.example/'])
        ->assertOk()
        ->assertJsonPath('domain_match', null)
        ->assertJsonPath('suggested_name', 'DSFA Homemade Bandung');
});

test('a weak category-context hit is not offered as an existing-entity suggestion', function () {
    fakePublicResolver();
    Entity::factory()->create([
        'name' => 'Band-Aid',
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    Http::fake([
        'https://dsfa.example/*' => Http::response('<html><head><title>DSFA Homemade Bandung | Takoyaki</title></head></html>', 200),
    ]);

    $this->postJson(route('api.sponsor.preview'), ['url' => 'https://dsfa.example/'])
        ->assertOk()
        ->assertJsonPath('candidates', []);
});
