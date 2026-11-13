# Covers and publish recipe (data recap posts)

## 1. Cover card
- One HTML file per cover in a scratch directory outside the repo. Body 1600×900, plain background, brand word top-left, kicker, headline, and one data element: a big number, a stacked bar, or horizontal bars with `width = value / max * 100%`.
- The chart is the cover. Use only numbers that appear in the article. No generated illustrations.
- Footer names the period.

## 2. Render (Playwright MCP)
`browser_run_code_unsafe` has no `require`, so it cannot read or write files. Write the HTML with Bash, then run:

```js
async (page) => {
  await page.setViewportSize({ width: 1600, height: 900 });
  await page.goto('file:///ABS/scratch/covers/rekap.html');
  await page.screenshot({ path: '/ABS/scratch/covers/rekap.png', type: 'png' });
  return 'ok';
}
```

Check the size from the PNG header: `python3 -c "import struct,sys; d=open(sys.argv[1],'rb').read(24); print(struct.unpack('>II', d[16:24]))" /ABS/scratch/covers/rekap.png` should print `(1600, 900)`.

## 3. Upload
Run from the local repo, whose `.env` holds the media bucket keys:

```bash
php artisan tinker --execute 'echo Storage::disk("media")->putFileAs("blog/covers", new Illuminate\Http\File("/ABS/scratch/covers/rekap.png"), Illuminate\Support\Str::uuid().".png", "public");'
```

Keep the returned path as `cover_image_path`. Curl the public URL: expect 200 and `image/png`.

## 4. Create posts on staging
Write a PHP file in scratch. Base64 the JSON payload so quoting cannot break:

```php
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Enums\BlogPostStatus;
use App\Domains\Entities\Models\Entity;

$posts = json_decode(base64_decode('BASE64_OF_JSON'), true);
foreach ($posts as $i => $p) {
    $post = BlogPost::firstOrNew(['slug' => $p['slug']]);
    $post->fill([
        'title' => $p['title'],
        'excerpt' => $p['excerpt'],
        'body' => $p['body'],
        'cover_image_path' => $p['cover_image_path'],
        'status' => BlogPostStatus::Published,
    ]);
    $post->published_at ??= now()->subMinutes(10 - $i * 3);
    $post->save();
    $ids = Entity::whereIn('slug', $p['entities'])->pluck('id')->all();
    $post->entities()->sync($ids);
    echo $post->slug.' entities='.count($ids).'/'.count($p['entities'])."\n";
}
```

`??=` keeps the original `published_at` on a rerun. Pipe the file through the ssh helper; do not inline PHP in the shell string:

```bash
timeout 180 ./ssh-main.sh "docker exec -i suaranetijen-app sh -c 'php artisan tinker --execute \"\$(cat)\"'" < create-posts.php
```

## 5. Verify live
```bash
B=https://DOMAIN
curl -s -o /dev/null -w '%{http_code}\n' $B/blog/SLUG
curl -s $B/blog/SLUG | grep -oE '<h1[^>]*>[^<]*'
curl -s $B/blog/SLUG | grep -oE 'property="og:image" content="[^"]*'
curl -s $B/sitemap.xml | grep -c SLUG
```

Collect `href="/e/..."`, `href="/blog/..."`, and `/methodology` from the article HTML, curl each, and expect 200. For the 390px check, use a fresh logged-out context and confirm `scrollWidth > clientWidth` is false.
