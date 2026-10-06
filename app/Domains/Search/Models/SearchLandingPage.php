<?php

namespace App\Domains\Search\Models;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Services\HomepageCategoryBlockService;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Enums\SearchLandingPageSource;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Themes\Models\Theme;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\CachePublicPages;
use Database\Factories\SearchLandingPageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $keyword
 * @property string $normalized_keyword
 * @property int|null $category_id
 * @property string|null $title
 * @property string|null $meta_description
 * @property string|null $intro
 * @property SearchLandingPageStatus $status
 * @property SearchLandingPageSource $source
 * @property int $candidate_signal
 * @property Carbon|null $llm_drafted_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 * @property-read Collection<int, Theme> $themes
 */
class SearchLandingPage extends Model
{
    /** @use HasFactory<SearchLandingPageFactory> */
    use HasFactory;

    protected $table = 'search_landing_pages';

    protected $attributes = [
        'status' => SearchLandingPageStatus::Candidate,
        'candidate_signal' => 0,
    ];

    protected $fillable = [
        'slug',
        'keyword',
        'normalized_keyword',
        'category_id',
        'title',
        'meta_description',
        'intro',
        'status',
        'source',
        'candidate_signal',
        'llm_drafted_at',
        'published_at',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (self $landingPage): void {
            if ($landingPage->wasChanged(['status', 'category_id', 'candidate_signal', 'title', 'keyword'])) {
                HomepageCategoryBlockService::clearCache();
                SitemapController::clearCache();
                CachePublicPages::flush();
            }
        });

        static::deleted(function (): void {
            HomepageCategoryBlockService::clearCache();
            SitemapController::clearCache();
            CachePublicPages::flush();
        });

        static::saving(function (self $landingPage): void {
            if (empty($landingPage->normalized_keyword) && ! empty($landingPage->keyword)) {
                $landingPage->normalized_keyword = TextNormalizer::normalize($landingPage->keyword);
            }

            if (empty($landingPage->slug) && ! empty($landingPage->keyword)) {
                $landingPage->slug = Str::slug($landingPage->keyword);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SearchLandingPageStatus::class,
            'source' => SearchLandingPageSource::class,
            'candidate_signal' => 'integer',
            'llm_drafted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Theme, $this>
     */
    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class, 'search_landing_page_themes', 'search_landing_page_id', 'theme_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', SearchLandingPageStatus::Published);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCandidate(Builder $query): Builder
    {
        return $query->where('status', SearchLandingPageStatus::Candidate);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', SearchLandingPageStatus::Draft);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', SearchLandingPageStatus::Rejected);
    }

    protected static function newFactory(): SearchLandingPageFactory
    {
        return SearchLandingPageFactory::new();
    }
}
