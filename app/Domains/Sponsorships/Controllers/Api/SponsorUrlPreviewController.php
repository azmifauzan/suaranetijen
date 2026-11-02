<?php

namespace App\Domains\Sponsorships\Controllers\Api;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Services\SearchService;
use App\Domains\Sponsorships\Services\FetchUrlPreview;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Step 1 of the sponsor checkout flow (docs/26): the user pastes a URL. An entity whose own
 * website shares that domain is the only automatic match; otherwise the page title becomes a
 * suggested name for a new entity, and title-search hits are offered as optional suggestions.
 * A site that can't be fetched (bot block, timeout) still returns 200 with `preview: null`, so
 * the user can type the name by hand instead of hitting a dead end. Only an invalid or
 * non-public URL (SSRF guard) is rejected.
 */
class SponsorUrlPreviewController extends Controller
{
    public function __invoke(Request $request, FetchUrlPreview $fetcher, SearchService $searchService): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $url = (string) $request->input('url');

        try {
            $fetcher->assertPublicHttpUrl($url);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $preview = null;
        $fetchError = null;

        try {
            $preview = $fetcher->handle($url);
        } catch (RuntimeException $e) {
            $fetchError = $e->getMessage();
        }

        $domainMatch = $this->findByDomain($url) ?? ($preview ? $this->findByDomain($preview['url']) : null);
        $suggestedName = $preview ? $this->cleanTitle($preview['title']) : $this->nameFromHost($url);

        $candidates = [];
        if (! $domainMatch) {
            $candidates = collect($searchService->search(
                query: $suggestedName,
                category: null,
                limit: 5,
                userId: $request->user()?->id,
                sessionId: null,
                logQuery: false
            )['data'])
                // Name-based tiers only (exact/alias/prefix/trigram): category-context and
                // descriptor hits are how "DSFA Homemade Bandung" used to surface Band-Aid.
                ->filter(fn (array $row): bool => $row['priority_rank'] <= 4 && $row['type'] !== EntityType::Person->value)
                ->values()
                ->all();
        }

        return response()->json([
            'preview' => $preview,
            'fetch_error' => $fetchError,
            'suggested_name' => $suggestedName,
            'domain_match' => $domainMatch ? [
                'id' => $domainMatch->id,
                'name' => $domainMatch->name,
                'type_label' => $domainMatch->type->label(),
                'category_name' => $domainMatch->category->name,
                'website_url' => $domainMatch->website_url,
            ] : null,
            'candidates' => $candidates,
        ]);
    }

    private function findByDomain(string $url): ?Entity
    {
        $host = $this->bareHost($url);
        if ($host === null) {
            return null;
        }

        // ponytail: LIKE prefilter + exact host compare in PHP; a stored normalized-host column
        // is the upgrade if entity count makes this scan slow.
        return Entity::query()
            ->with('category')
            ->where('status', EntityStatus::Active)
            ->where('type', '!=', EntityType::Person)
            ->where('website_url', 'like', '%'.$host.'%')
            ->get()
            ->first(fn (Entity $entity): bool => $this->bareHost((string) $entity->website_url) === $host);
    }

    private function bareHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? Str::of($host)->lower()->replaceStart('www.', '')->toString() : null;
    }

    /**
     * "DSFA Homemade Bandung | Cheesecuit, Takoyaki" -> "DSFA Homemade Bandung".
     */
    private function cleanTitle(string $title): string
    {
        $first = trim((preg_split('/\s+[|\-–—:·]\s+|\s*\|\s*/u', $title) ?: [$title])[0]);

        return $first !== '' ? Str::limit($first, 120, '') : $title;
    }

    private function nameFromHost(string $url): string
    {
        $host = (string) $this->bareHost($url);

        return Str::of($host)->before('.')->replace('-', ' ')->title()->toString();
    }
}
