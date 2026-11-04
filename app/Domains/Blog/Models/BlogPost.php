<?php

namespace App\Domains\Blog\Models;

use App\Domains\Blog\Enums\BlogPostStatus;
use App\Domains\Entities\Models\Entity;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\CachePublicPages;
use App\Models\User;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $cover_image_path
 * @property BlogPostStatus $status
 * @property Carbon|null $published_at
 * @property int|null $author_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read Collection<int, Entity> $entities
 * @property-read string|null $cover_url
 * @property-read string $body_html
 * @property-read string $effective_excerpt
 */
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    protected $table = 'blog_posts';

    protected $attributes = [
        'status' => BlogPostStatus::Draft,
    ];

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'body',
        'cover_image_path',
        'status',
        'published_at',
        'author_id',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Any save of a published post can change public HTML (body, excerpt, cover, linked
        // entities) or the sitemap lastmod, so flush whenever the post is or just stopped being public.
        static::saved(function (self $post): void {
            if ($post->status === BlogPostStatus::Published || $post->wasChanged('status')) {
                self::flushPublicCaches();
            }
        });

        static::deleted(function (self $post): void {
            if ($post->cover_image_path) {
                Storage::disk('media')->delete($post->cover_image_path);
            }
            self::flushPublicCaches();
        });

        static::saving(function (self $post): void {
            if (empty($post->slug) && ! empty($post->title)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    public static function flushPublicCaches(): void
    {
        SitemapController::clearCache();
        CachePublicPages::flush();
    }

    /**
     * Render Markdown the one way both the public page and the admin preview use.
     */
    public static function renderMarkdown(string $markdown): string
    {
        return (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsToMany<Entity, $this>
     */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'blog_post_entity', 'blog_post_id', 'entity_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', BlogPostStatus::Published);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', BlogPostStatus::Draft);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', BlogPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return Storage::disk('media')->url($this->cover_image_path);
    }

    public function getBodyHtmlAttribute(): string
    {
        return self::renderMarkdown($this->body);
    }

    public function getEffectiveExcerptAttribute(): string
    {
        if (! empty($this->excerpt)) {
            return $this->excerpt;
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $this->body);
        foreach ($lines ?: [] as $line) {
            $trimmed = trim(strip_tags((string) Str::markdown($line)));
            if ($trimmed !== '') {
                return Str::limit($trimmed, 300);
            }
        }

        return '';
    }

    protected static function newFactory(): BlogPostFactory
    {
        return BlogPostFactory::new();
    }
}
