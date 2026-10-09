<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityReviewVideo;
use App\Domains\Entities\Services\ReviewVideoLookup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->product = Entity::factory()->product()->create(['name' => 'Dreame X60 Ultra']);
    Cache::flush();
});

function fakeOembed(string $title = 'Judul dari YouTube', int $status = 200): void
{
    Http::fake(['www.youtube.com/oembed*' => Http::response($status === 200 ? ['title' => $title] : [], $status)]);
}

it('reads the video id from every common YouTube link form', function (string $input, ?string $id) {
    expect(app(ReviewVideoLookup::class)->parseId($input))->toBe($id);
})->with([
    'watch url' => ['https://www.youtube.com/watch?v=NBfx6HKwlcw&t=10s', 'NBfx6HKwlcw'],
    'mobile url' => ['https://m.youtube.com/watch?v=NBfx6HKwlcw', 'NBfx6HKwlcw'],
    'short link' => ['https://youtu.be/NBfx6HKwlcw?si=abc', 'NBfx6HKwlcw'],
    'embed url' => ['https://www.youtube.com/embed/NBfx6HKwlcw', 'NBfx6HKwlcw'],
    'shorts url' => ['https://youtube.com/shorts/NBfx6HKwlcw', 'NBfx6HKwlcw'],
    'no scheme' => ['youtube.com/watch?v=NBfx6HKwlcw', 'NBfx6HKwlcw'],
    'bare id' => ['NBfx6HKwlcw', 'NBfx6HKwlcw'],
    'other site' => ['https://vimeo.com/watch?v=NBfx6HKwlcw', null],
    'lookalike host' => ['https://youtube.com.evil.test/watch?v=NBfx6HKwlcw', null],
    'too short id' => ['https://youtu.be/short', null],
    'array in query' => ['https://www.youtube.com/watch?v[]=NBfx6HKwlcw', null],
    'not a url' => ['review bagus', null],
]);

it('lets an admin add a video by link and takes the title from YouTube', function () {
    fakeOembed('Review Dreame X60 Ultra lengkap');

    $this->actingAs($this->admin)
        ->post("/admin/entities/{$this->product->id}/review-videos", ['url' => 'https://youtu.be/NBfx6HKwlcw'])
        ->assertRedirect();

    $video = EntityReviewVideo::sole();
    expect($video->only(['entity_id', 'youtube_id', 'title', 'source', 'hidden_at']))
        ->toBe(['entity_id' => $this->product->id, 'youtube_id' => 'NBfx6HKwlcw', 'title' => 'Review Dreame X60 Ultra lengkap', 'source' => 'manual', 'hidden_at' => null]);
});

it('prefers the title the admin typed', function () {
    fakeOembed('Judul panjang dari YouTube');

    $this->actingAs($this->admin)
        ->post("/admin/entities/{$this->product->id}/review-videos", ['url' => 'NBfx6HKwlcw', 'title' => 'Judul pilihan admin'])
        ->assertRedirect();

    expect(EntityReviewVideo::sole()->title)->toBe('Judul pilihan admin');
});

it('rejects a link that is not YouTube or that YouTube cannot embed, and stores nothing', function (string $url, int $oembedStatus) {
    fakeOembed('x', $oembedStatus);

    $this->actingAs($this->admin)
        ->post("/admin/entities/{$this->product->id}/review-videos", ['url' => $url])
        ->assertSessionHasErrors('url');

    expect(EntityReviewVideo::count())->toBe(0);
})->with([
    'not YouTube' => ['https://vimeo.com/12345', 200],
    'private or not embeddable' => ['https://youtu.be/NBfx6HKwlcw', 401],
    'missing video' => ['https://youtu.be/NBfx6HKwlcw', 404],
]);

it('requires a link when adding', function () {
    $this->actingAs($this->admin)
        ->post("/admin/entities/{$this->product->id}/review-videos", ['url' => ''])
        ->assertSessionHasErrors('url');
});

it('only admins can manage review videos', function () {
    fakeOembed();
    $video = EntityReviewVideo::factory()->create(['entity_id' => $this->product->id]);

    foreach ([
        ['post', "/admin/entities/{$this->product->id}/review-videos", ['url' => 'NBfx6HKwlcw']],
        ['put', "/admin/entities/{$this->product->id}/review-videos/{$video->id}", ['title' => 'x']],
        ['delete', "/admin/entities/{$this->product->id}/review-videos/{$video->id}", []],
        ['post', "/admin/entities/{$this->product->id}/review-videos/{$video->id}/restore", []],
    ] as [$method, $url, $data]) {
        $this->actingAs(User::factory()->create())->{$method}($url, $data)->assertForbidden();
    }

    expect($video->fresh()->title)->toBe($video->title);
});

it('does not attach videos to entities that are not products', function () {
    fakeOembed();
    $brand = Entity::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/entities/{$brand->id}/review-videos", ['url' => 'NBfx6HKwlcw'])
        ->assertNotFound();
});

it('turns an automatic or hidden video into a manual visible one when the admin adds the same link', function () {
    fakeOembed('Judul baru');
    EntityReviewVideo::factory()->create([
        'entity_id' => $this->product->id, 'youtube_id' => 'NBfx6HKwlcw', 'title' => 'Judul otomatis', 'hidden_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/entities/{$this->product->id}/review-videos", ['url' => 'NBfx6HKwlcw'])
        ->assertRedirect();

    $video = EntityReviewVideo::sole();
    expect($video->source)->toBe('manual')->and($video->hidden_at)->toBeNull()->and($video->title)->toBe('Judul baru');
});

it('edits the title of an automatic video but never its video id', function () {
    fakeOembed();
    $video = EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'youtube_id' => 'AUTOvideo01']);

    $this->actingAs($this->admin)
        ->put("/admin/entities/{$this->product->id}/review-videos/{$video->id}", ['title' => 'Judul rapi', 'url' => 'NBfx6HKwlcw'])
        ->assertRedirect();

    expect($video->fresh()->only(['title', 'youtube_id', 'source']))
        ->toBe(['title' => 'Judul rapi', 'youtube_id' => 'AUTOvideo01', 'source' => 'auto']);
});

it('changes the link of a manual video and refuses a link already listed', function () {
    fakeOembed('Judul baru dari YouTube');
    $manual = EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'youtube_id' => 'MANUALvid01', 'source' => 'manual']);
    EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'youtube_id' => 'OTHERvideo01']);

    $this->actingAs($this->admin)
        ->put("/admin/entities/{$this->product->id}/review-videos/{$manual->id}", ['url' => 'OTHERvideo01'])
        ->assertSessionHasErrors('url');
    expect($manual->fresh()->youtube_id)->toBe('MANUALvid01');

    $this->actingAs($this->admin)
        ->put("/admin/entities/{$this->product->id}/review-videos/{$manual->id}", ['url' => 'https://youtu.be/NEWvideo001'])
        ->assertRedirect();
    expect($manual->fresh()->only(['youtube_id', 'title']))->toBe(['youtube_id' => 'NEWvideo001', 'title' => 'Judul baru dari YouTube']);
});

it('deletes a manual video but only hides an automatic one', function () {
    $manual = EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'source' => 'manual']);
    $auto = EntityReviewVideo::factory()->create(['entity_id' => $this->product->id]);

    $this->actingAs($this->admin)->delete("/admin/entities/{$this->product->id}/review-videos/{$manual->id}")->assertRedirect();
    $this->actingAs($this->admin)->delete("/admin/entities/{$this->product->id}/review-videos/{$auto->id}")->assertRedirect();

    expect(EntityReviewVideo::find($manual->id))->toBeNull()
        ->and($auto->fresh()->hidden_at)->not->toBeNull();

    $this->actingAs($this->admin)->post("/admin/entities/{$this->product->id}/review-videos/{$auto->id}/restore")->assertRedirect();
    expect($auto->fresh()->hidden_at)->toBeNull();
});

it('cannot touch a video through another product', function () {
    $other = Entity::factory()->product()->create();
    $video = EntityReviewVideo::factory()->create(['entity_id' => $other->id]);

    $this->actingAs($this->admin)->delete("/admin/entities/{$this->product->id}/review-videos/{$video->id}")->assertNotFound();
    $this->actingAs($this->admin)->put("/admin/entities/{$this->product->id}/review-videos/{$video->id}", ['title' => 'x'])->assertNotFound();

    expect($video->fresh()->hidden_at)->toBeNull();
});

it('does not bring a hidden automatic video back when the daily search finds it again', function () {
    config(['sources.youtube.api_key' => 'k', 'sources.youtube.api_url' => 'https://yt.test/v3']);
    $hidden = EntityReviewVideo::factory()->create([
        'entity_id' => $this->product->id, 'youtube_id' => 'HIDDENvid01', 'title' => 'Review Dreame X60 Ultra', 'hidden_at' => now(),
    ]);
    EntityReviewVideo::query()->where('id', $hidden->id)->update(['source' => 'auto']);
    // Hidden automatic videos still count as "searched", so this product is not searched again.
    Http::fake(['yt.test/v3/search*' => Http::response(['items' => [[
        'id' => ['videoId' => 'HIDDENvid01'],
        'snippet' => ['title' => 'Review Dreame X60 Ultra', 'publishedAt' => '2026-09-01T10:00:00Z'],
    ]]])]);

    $this->artisan('entities:fetch-review-videos')->assertSuccessful();

    expect(EntityReviewVideo::count())->toBe(1)->and($hidden->fresh()->hidden_at)->not->toBeNull();
    Http::assertNothingSent();
});

it('still searches a product that only has manual videos, and keeps the manual copy of a found video', function () {
    config(['sources.youtube.api_key' => 'k', 'sources.youtube.api_url' => 'https://yt.test/v3']);
    EntityReviewVideo::factory()->create([
        'entity_id' => $this->product->id, 'youtube_id' => 'SAMEvideo001', 'title' => 'Judul admin', 'source' => 'manual',
    ]);
    Http::fake(['yt.test/v3/search*' => Http::response(['items' => [
        ['id' => ['videoId' => 'SAMEvideo001'], 'snippet' => ['title' => 'Review Dreame X60 Ultra', 'publishedAt' => '2026-09-01T10:00:00Z']],
        ['id' => ['videoId' => 'FOUNDvideo01'], 'snippet' => ['title' => 'Dreame X60 Ultra review jujur', 'publishedAt' => '2026-09-02T10:00:00Z']],
    ]])]);

    $this->artisan('entities:fetch-review-videos')->assertSuccessful();

    expect(EntityReviewVideo::count())->toBe(2)
        ->and(EntityReviewVideo::where('youtube_id', 'SAMEvideo001')->sole()->only(['title', 'source']))->toBe(['title' => 'Judul admin', 'source' => 'manual'])
        ->and(EntityReviewVideo::where('youtube_id', 'FOUNDvideo01')->sole()->source)->toBe('auto');
});

it('shows visible videos on the public page with manual ones first, and none that are hidden', function () {
    EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'youtube_id' => 'AUTOnew0001', 'published_at' => now()]);
    EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'youtube_id' => 'HIDDENvid02', 'hidden_at' => now()]);
    EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'youtube_id' => 'MANUALold01', 'source' => 'manual', 'published_at' => now()->subYear()]);

    $this->get("/e/{$this->product->slug}")->assertInertia(fn (Assert $page) => $page
        ->has('reviewVideos', 2)
        ->where('reviewVideos.0.youtube_id', 'MANUALold01')
        ->where('reviewVideos.1.youtube_id', 'AUTOnew0001'));
});

it('lists every video, hidden ones included, on the admin edit page', function () {
    EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'youtube_id' => 'AUTOvideo002', 'published_at' => now()]);
    EntityReviewVideo::factory()->create(['entity_id' => $this->product->id, 'youtube_id' => 'HIDDENvid03', 'published_at' => now()->subDay(), 'hidden_at' => now()]);

    $this->actingAs($this->admin)->get("/admin/entities/{$this->product->id}/edit")->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Entities/Form')
        ->has('review_videos', 2)
        ->where('review_videos.0.is_hidden', false)
        ->where('review_videos.1.is_hidden', true)
        ->where('review_videos.1.source', 'auto'));
});
